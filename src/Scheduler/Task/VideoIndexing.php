<?php

namespace App\Scheduler\Task;

use App\Service\CameraManager;
use App\Service\VideoFileManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Scheduler\Attribute\AsPeriodicTask;

/**
 * This scheduler indexes new videos
 */
#[AsPeriodicTask(frequency: 60)]
class VideoIndexing
{
    public function __construct(private readonly CameraManager $cameraManager, private readonly VideoFileManager $videoFileManager, private readonly LoggerInterface $logger)
    {
    }

    public function __invoke(): void
    {
        $this->logger->info('Starte Video-Cleanup Task...');
        $cameras = $this->cameraManager->getCameras();
        foreach ($cameras as $camera) {
            $this->logger->info('Starte indexing for camera ' . $camera->getTitle());
            //indexing is implemented in the videoFileManager, we just trigger a select to index new files. This is all we need to do.
            $this->videoFileManager->getVideos($camera);
        }
    }

}