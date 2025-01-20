<?php
namespace App\Scheduler\Task;

use App\Entity\Camera;
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

    public function __construct(CameraManager $cameraManager, VideoFileManager $videoFileManager){
        $this->cameraManager = $cameraManager;
        $this->videoFileManager = $videoFileManager;
    }

    public function __invoke()
    {
        $cameras = $this->cameraManager->getCameras();
        //We don't know if cameras share the same file system, so we check all.
        //And to not delete always from the same camera, we add some randomness to it
        shuffle($cameras);
        foreach ($cameras as $camera){
            if($this->cameraFolderIsFull($camera))
            {
                $this->deleteOldestVideo($camera);
            }
        }
        //@todo
    }

    private function cameraFolderIsFull(Camera $camera): bool
    {
        //@todo
        return false;
    }

    private function deleteOldestVideo(Camera $camera): void
    {
        //@todo
    }
}