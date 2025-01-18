<?php

namespace App\Service;

use App\Entity\Camera;
use App\Entity\Video;

interface VideoFileManagerInterface
{
    /**
     * @var Camera $camera
     * @return Video[]
     */
    public function getVideos(Camera $camera): array;

    public function protectVideo(Video $video);
}