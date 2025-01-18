<?php

namespace App\Service;

use App\Entity\Camera;
use App\Entity\Video;

class VideoFileManager implements VideoFileManagerInterface
{

    /**
     * @inheritDoc
     */
    public function getVideos(Camera $camera): array
    {
        // TODO: Implement listVideos() method.
    }

    public function getVideoDetails(Video $video): array
    {
        // TODO: Implement getVideoDetails() method.
    }

    public function protectVideo(Video $video)
    {
        $video->setIsProtected(true);
    }

    public function unprotectVideo(Video $video)
    {
        $video->setIsProtected(false);
    }

    public function calculateVideoSize(Video $video): void
    {
        $size = filesize($video->getPath());
        if ($size !== false) {
            $video->setSize($size);
        }
    }

    public function calculateRecordTime(): void
    {

    }
}