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

        $load = sys_getloadavg();
        if (!is_array($load) || count($load) < 2) {
            $this->logger->warning('System load could not be determined. Thumbnail generation skipped.');
            return;
        }

        $oneMinuteLoad = $load[0];
        $fiveMinuteLoad = $load[1];
        if ($oneMinuteLoad >= 2 || $fiveMinuteLoad >= 3) {
            $this->logger->info(sprintf('Thumbnail generation skipped due to high load (1m: %.2f, 5m: %.2f).', $oneMinuteLoad, $fiveMinuteLoad));
            return;
        }

        $generatedThumbnails = $this->videoFileManager->generateMissingThumbnails();
        $this->logger->info('Generated ' . $generatedThumbnails . ' thumbnails in background task.');
    }

}
