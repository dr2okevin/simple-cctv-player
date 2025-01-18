<?php

namespace App\Controller;

use App\Entity\Video;
use App\Service\CameraManager;
use App\Service\VideoFileManager;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class VideoController
{
    private CameraManager $cameraManager;

    private VideoFileManager $videoFileManager;

    public function __construct(CameraManager $cameraManager, VideoFileManager $videoFileManager){
        $this->videoFileManager = $videoFileManager;
        $this->cameraManager = $cameraManager;
    }

    #[Route('/listVideos', name: 'list_videos')]
    public function listVideos(): Response
    {
        $cameras = $this->cameraManager->getCameras();
        $videos = [];
        foreach ($cameras as $camera){
            $videos = array_merge($videos, $this->videoFileManager->getVideos($camera));
        }
        return new Response('test');
    }

    #[Route('/video/{uid}', name: 'show_video')]
    public function showVideo(string $uid, VideoFileManager $videoFileManager): Response
    {
        return new Response('video test');
    }
}