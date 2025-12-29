<?php

namespace App\Scheduler\Task;

use App\Entity\Camera;
use App\Entity\Video;
use App\Repository\VideoRepository;
use App\Service\CameraManager;
use App\Service\VideoFileManager;
use Symfony\Component\Scheduler\Attribute\AsPeriodicTask;

/**
 * This scheduler deletes old unprotected videos if the drive is almost full.
 */
#[AsPeriodicTask(frequency: 120)]
class VideoCleanup
{
    public function __construct(private readonly CameraManager $cameraManager, private readonly VideoFileManager $videoFileManager, private readonly VideoRepository $videoRepository)
    {
    }

    public function __invoke(): void
    {
        $cameras = $this->cameraManager->getCameras();
        //We don't know if cameras share the same file system, so we check all.
        //And to not delete always from the same camera, we add some randomness to it
        shuffle($cameras);
        foreach ($cameras as $camera) {
            $this->deleteTooOldVideos($camera, 0);
            if ($this->isCameraFolderFull($camera)) {
                $deleted = $this->deleteOldestVideos($camera, 1);
                if ($deleted > 0) {
                    $this->__invoke();
                }
            }
        }
    }

    private function isCameraFolderFull(Camera $camera): bool
    {
        $folder = $camera->getVideoFolder();
        if (!is_dir($folder)) {
            // looks like the folder dosen't exist
            return false;
        }

        $freeBytes = disk_free_space($folder);
        $thresholdBytes = $camera->getKeepFreeSpace() * 1048576; // MB in Bytes
        return $freeBytes < $thresholdBytes;
    }

    /**
     * Delete the oldest videos until enough space is free
     *
     * @param Camera $camera
     * @param int $limit
     * @return int
     */
    private function deleteOldestVideos(Camera $camera, int $limit = 0): int
    {
        $videos = $this->videoRepository->findDeletableVideosByCamera($camera);
        $i = 0;
        foreach ($videos as $video) {
            if ($video instanceof Video && $this->videoFileManager->deleteVideo($video->getUid())) {
                $i++;
            }

            if ($limit > 0 && $limit <= $i) {
                return $i;
            }
        }

        return $i;
    }

    /**
     * @param Camera $camera
     * @param int $limit
     * @return int
     */
    private function deleteTooOldVideos(Camera $camera, int $limit = 0): int
    {
        if ($camera->getMaxAge() == 0) {
            return 0;
        }

        $AgeLimit = new \DateInterval($camera->getMaxAge() . ' h');
        $maxAge = new \DateTime();
        $maxAge->sub($AgeLimit);

        $videos = $this->videoRepository->findDeletableVideosByCameraAndAge($camera, $maxAge);
        $i = 0;
        foreach ($videos as $video) {
            if ($video instanceof Video && $this->videoFileManager->deleteVideo($video->getUid())) {
                $i++;
            }

            if ($limit > 0 && $limit <= $i) {
                return $i;
            }
        }

        return $i;
    }
}