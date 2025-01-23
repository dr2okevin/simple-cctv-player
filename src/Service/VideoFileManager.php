<?php

namespace App\Service;

use App\Entity\Camera;
use App\Entity\Video;
use App\Enum\CameraType;
use App\Repository\VideoRepository;
use Symfony\Component\HttpKernel\KernelInterface;

class VideoFileManager implements VideoFileManagerInterface
{
    /** @var string[] $videoExtensions */
    protected array $videoExtensions = ['mp4', 'mkv', 'avi'];

    protected string $thumbnailExtension = 'jpg';

    protected VideoRepository $videoRepository;

    protected KernelInterface $kernel;

    public function __construct(VideoRepository $videoRepository, KernelInterface $kernel)
    {
        $this->videoRepository = $videoRepository;
        $this->kernel = $kernel;
    }

    /**
     * @inheritDoc
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
        //now we want to convert only the video files to objects
        $videoObjects = [];
        foreach ($files as $file) {
            $filenameArray = pathinfo($file);
            if (isset($filenameArray['extension']) && in_array($filenameArray['extension'], $this->videoExtensions)) {
                $fullPath = $folder . '/' . $file;
                $uid = Video::calculateUid($fullPath);
                $existingVideoObject = $this->videoRepository->findOneByUid($uid);
                if (isset($existingVideoObject) && $existingVideoObject instanceof Video) {
                    //We found a video object in the Database
                    $videoObjects[] = $existingVideoObject;
                    continue;
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
        return $videoObjects;
    }

    public function protectVideo(Video $video)
    {
        $video->setIsProtected(true);
    }

    public function unprotectVideo(Video $video)
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
        }
        if (isset($matches) && !empty($matches)) {
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
        $fileTime = filectime($path);
        return new \DateTime(@$fileTime);
    }

    public function calculateDuration(Video $video): int
    {
        $path = $video->getPath();
        if (file_exists($path)) {
            $command = 'ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 ' . escapeshellarg($path);
            $duration = exec($command);
            if ($duration !== false && is_numeric($duration)) {
                return round($duration, 0);
            }
        }
        return 0;
    }

    public function findVideoByUid(string $uid): ?Video
    {
        return $this->videoRepository->findOneByUid($uid);
    }

    public function getThumbnail(string $uid): ?string
    {
        $projectDir = $this->kernel->getProjectDir();
        $thumbnailDir = $projectDir . '/var/thumbnails/';
        if (!is_dir($thumbnailDir)) {
            mkdir($thumbnailDir, 0774, true);
        }
        $path = $thumbnailDir . $uid . '.' . $this->thumbnailExtension;
        if (!file_exists($path)) {
            $video = $this->videoRepository->findOneByUid($uid);
            if (!$video instanceof Video) {
                return null;
            }
            $command = 'ffmpeg -i ' . escapeshellarg(realpath($video->getPath())) . ' -vf thumbnail=n=100 -frames:v 1 ' . escapeshellarg($path);
            exec($command, $output, $returnVar);
            if ($returnVar !== 0) {
                return null;
            }

        }
        return $path;
    }

    public function deleteVideo(string $uid): bool
    {
        //@todo
        //check if video even exist
        //check if video is protected (don't delete then)
        //delete video file
        //delete thumbnail
        //delete db entry
        return false;
    }
}