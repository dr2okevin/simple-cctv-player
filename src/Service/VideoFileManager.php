<?php

namespace App\Service;

use App\Entity\Camera;
use App\Entity\Video;
use App\Enum\CameraType;
use App\Repository\VideoRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\KernelInterface;

class VideoFileManager implements VideoFileManagerInterface
{
    /** @var string[] $videoExtensions */
    protected array $videoExtensions = ['mp4', 'mkv', 'avi'];

    protected string $thumbnailExtension = 'jpg';

    public function __construct(protected VideoRepository $videoRepository, protected KernelInterface $kernel, private readonly LoggerInterface $logger)
    {
    }

    /**
     * @inheritDoc
     * @return Camera[]
     */
    public function getVideos(Camera $camera): array
    {
        //first we want to get all files in the folder
        $folder = $camera->getVideoFolder();
        /** @var string[]|false $files */
        $files = scandir($folder);
        if ($files === false) {
            throw new \Exception('Could not read folder: ' . $folder);
        }

        //get all video IDs from the database
        $existingUids = $this->videoRepository->findAllUidsByCamera($camera);

        //now we want to convert only the video files to objects
        $videoObjects = [];
        foreach ($files as $file) {
            $filenameArray = pathinfo($file);
            if (isset($filenameArray['extension']) && in_array($filenameArray['extension'], $this->videoExtensions)) {
                $fullPath = $folder . '/' . $file;
                clearstatcache(false, $fullPath);
                if (time() - filemtime($fullPath) < 50) {
                    $this->logger->debug('video ' . $fullPath . ' is too new, might be still in write process. Ignore for now to avoid wrong meta data');
                    continue;
                }
                $uid = Video::calculateUid($fullPath);
                if (in_array($uid, $existingUids)) {
                    $existingVideoObject = $this->videoRepository->findOneByUid($uid);
                    if (isset($existingVideoObject) && $existingVideoObject instanceof Video) {
                        //We found a video object in the Database
                        $videoObjects[] = $existingVideoObject;
                        continue;
                    }
                }

                //Must be a new file, so create a new object
                $videoObject = new Video($fullPath, $filenameArray['filename'], $camera->getType());
                $videoObject->setSize($this->calculateVideoSize($videoObject));
                $videoObject->setRecordTime($this->calculateRecordTime($videoObject));
                $videoObject->setDuration($this->calculateDuration($videoObject));
                $this->videoRepository->save($videoObject);
                $videoObjects[] = $videoObject;
            }
        }

        //add camera object in case some function needs that info
        foreach ($videoObjects as $videoObject) {
            $videoObject->setCamera($camera);
        }

        return $videoObjects;
    }

    public function protectVideo(Video $video): void
    {
        $video->setIsProtected(true);
    }

    public function unprotectVideo(Video $video): void
    {
        $video->setIsProtected(false);
    }

    public function calculateVideoSize(Video $video): int
    {
        $size = filesize($video->getPath());
        if ($size !== false) {
            return $size;
        }

        return 0;
    }

    public function calculateRecordTime(Video $video): \DateTime
    {
        $path = $video->getPath();
        $filename = pathinfo($path, PATHINFO_FILENAME);
        if ($video->getCameraType() == CameraType::Unifi) {
            $regex = '/^(?\'year\'\d\d\d\d)(?\'month\'\d\d)(?\'day\'\d\d)\.(?\'hour\'\d\d)(?\'minute\'\d\d)(?\'second\'\d\d)/m';
            preg_match($regex, $filename, $matches);
        } elseif ($video->getCameraType() == CameraType::Reolink) {
            $regex = '/(?\'year\'\d\d\d\d)(?\'month\'\d\d)(?\'day\'\d\d)(?\'hour\'\d\d)(?\'minute\'\d\d)(?\'second\'\d\d)/m';
            preg_match($regex, $filename, $matches);
        } elseif ($video->getCameraType() == CameraType::Annke) {
            $regex = '/(?\'year\'\d\d\d\d)(?\'month\'\d\d)(?\'day\'\d\d)(?\'hour\'\d\d)(?\'minute\'\d\d)(?\'second\'\d\d)/m';
            preg_match($regex, $filename, $matches);
        }

        if (isset($matches) && $matches !== []) {
            $timeString = sprintf(
                '%s-%s-%s %s:%s:%s',
                $matches['year'],
                $matches['month'],
                $matches['day'],
                $matches['hour'],
                $matches['minute'],
                $matches['second']
            );
            return new \DateTime($timeString);
        }

        //Fallback if regex didn't work
        $fileTime = filemtime($path);
        return new \DateTime('@' . $fileTime);
    }

    public function calculateDuration(Video $video): int
    {
        $path = $video->getPath();
        if (file_exists($path)) {
            $command = 'ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 ' . escapeshellarg($path);
            $duration = exec($command);
            if ($duration !== false && is_numeric($duration)) {
                return (int)round($duration, 0);
            }
            $this->logger->error('Command' . $command . 'returned' . (string)$duration);
        }

        return 0;
    }

    public function findVideoByUid(string $uid): ?Video
    {
        return $this->videoRepository->findOneByUid($uid);
    }

    public function getThumbnailPath(string $uid): string
    {
        $projectDir = $this->kernel->getProjectDir();
        $thumbnailDir = $projectDir . '/var/thumbnails/';
        if (!is_dir($thumbnailDir)) {
            mkdir($thumbnailDir, 0774, true);
        }

        $path = $thumbnailDir . $uid . '.' . $this->thumbnailExtension;
        return $path;
    }

    public function getThumbnail(string $uid): ?string
    {
        $path = $this->getThumbnailPath($uid);
        if (!file_exists($path)) {
            return null;
        }

        return $path;
    }

    public function generateThumbnail(string $uid): bool
    {
        $video = $this->videoRepository->findOneByUid($uid);
        if (!$video instanceof Video) {
            return false;
        }

        return $this->generateThumbnailForVideo($video);
    }

    public function generateMissingThumbnails(int $limit = 5): int
    {
        if ($limit <= 0) {
            return 0;
        }

        $offset = 0;
        $batchSize = 50;
        $generated = 0;

        while ($generated < $limit) {
            $videos = $this->videoRepository->findBy([], ['recordTime' => 'DESC'], $batchSize, $offset);
            if ($videos === []) {
                break;
            }

            foreach ($videos as $video) {
                if (!$video instanceof Video) {
                    continue;
                }

                if (!$this->isThumbnailGenerationLoadAllowed()) {
                    $this->logger->info('Thumbnail generation stopped due to high system load.');
                    return $generated;
                }

                if (file_exists($this->getThumbnailPath($video->getUid()))) {
                    continue;
                }

                if ($this->generateThumbnailForVideo($video)) {
                    ++$generated;
                }

                if ($generated >= $limit) {
                    break;
                }
            }

            $offset += $batchSize;
        }

        return $generated;
    }

    private function generateThumbnailForVideo(Video $video): bool
    {
        $path = $this->getThumbnailPath($video->getUid());
        if (file_exists($path)) {
            return true;
        }

        $generationLock = $this->acquireThumbnailGenerationLock();
        if ($generationLock === null) {
            $this->logger->info('No free thumbnail generation slot available. Skipping for now.');
            return false;
        }

        $realPath = realpath($video->getPath());
        if ($realPath === false) {
            $this->releaseThumbnailGenerationLock($generationLock);
            $this->logger->warning('Could not resolve real path for video ' . $video->getUid());
            return false;
        }

        $halfVideoTime = (int)round($video->getDuration() / 2, 0);
        $command = 'ffmpeg -i ' . escapeshellarg($realPath) . ' -ss ' . $halfVideoTime . ' -frames:v 1 ' . escapeshellarg($path);
        exec($command, $output, $returnVar);
        $this->releaseThumbnailGenerationLock($generationLock);
        if ($returnVar !== 0) {
            $this->logger->warning('Thumbnail generation failed for video ' . $video->getUid());
            return false;
        }

        return true;
    }

    private function isThumbnailGenerationLoadAllowed(): bool
    {
        $load = sys_getloadavg();
        if (!is_array($load) || count($load) < 2) {
            return false;
        }

        return $load[0] < 2 && $load[1] < 3;
    }

    private function acquireThumbnailGenerationLock(): mixed
    {
        $lockDir = $this->kernel->getProjectDir() . '/var/thumbnail_generation_locks/';
        if (!is_dir($lockDir)) {
            mkdir($lockDir, 0774, true);
        }

        for ($slot = 0; $slot < 2; ++$slot) {
            $lockFile = $lockDir . 'slot_' . $slot . '.lock';
            $lockHandle = fopen($lockFile, 'c');
            if ($lockHandle === false) {
                continue;
            }

            if (flock($lockHandle, LOCK_EX | LOCK_NB)) {
                return $lockHandle;
            }

            fclose($lockHandle);
        }

        return null;
    }

    private function releaseThumbnailGenerationLock(mixed $lockHandle): void
    {
        if (!is_resource($lockHandle)) {
            return;
        }

        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
    }

    public function deleteVideo(string $uid): bool
    {
        //Abort if the Video doesn't exists.
        $video = $this->findVideoByUid($uid);
        if (!$video instanceof Video) {
            $this->logger->warning('video with the uid ' . $uid . ' can\'t be deleted because it dosen\'t exist');
            return false;
        }

        //Abort if the video is protected
        if ($video->isProtected()) {
            $this->logger->warning('video with the uid ' . $uid . ' can\'t be deleted because it is protected');
            return false;
        }

        //Delete the original file
        $videoPath = $video->getPath();
        if (file_exists($videoPath) && !unlink($videoPath)) {
            $this->logger->error('video with the path ' . $videoPath . ' can\'t be deleted');
            return false;
        }

        //Delte Thumbnail
        $thumbnailPath = $this->getThumbnailPath($uid);
        if (file_exists($thumbnailPath) && !unlink($thumbnailPath)) {
            $this->logger->error('thumbnail with the path ' . $thumbnailPath . ' can\'t be deleted');
            return false;
        }

        $this->videoRepository->remove($video);
        $this->logger->info('Video ' . $uid . ' has been deleted');
        return true;
    }
}
