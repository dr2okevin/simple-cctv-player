<?php
namespace App\Scheduler\Task;

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
        //@todo
    }
}