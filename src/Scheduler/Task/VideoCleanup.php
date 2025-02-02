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
#[AsPeriodicTask(frequency: 600)]
class VideoCleanup
{
    private CameraManager $cameraManager;

    private VideoFileManager $videoFileManager;

    private VideoRepository $videoRepository;

    public function __construct(CameraManager $cameraManager, VideoFileManager $videoFileManager, VideoRepository $videoRepository)
    {
        $this->cameraManager = $cameraManager;
        $this->videoFileManager = $videoFileManager;
        $this->videoRepository = $videoRepository;
    }

    public function __invoke()
    {
        $cameras = $this->cameraManager->getCameras();
        //We don't know if cameras share the same file system, so we check all.
        //And to not delete always from the same camera, we add some randomness to it
        shuffle($cameras);
        foreach ($cameras as $camera) {
            if ($this->isCameraFolderFull($camera)) {
                $this->deleteOldestVideo($camera);
            }
        }
        //@todo
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

    private function deleteOldestVideo(Camera $camera): bool
    {
        $videos = $this->videoRepository->findDeletableVideosByCamera($camera);
        foreach ($videos as $video) {
            if ($video instanceof Video) {
                $this->videoFileManager->deleteVideo($video->getUid());
                return true;
            }
        }
        return false;
    }
}