<?php

namespace App\Controller;

use App\Entity\Video;
use App\Service\CameraManager;
use App\Service\VideoFileManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

class VideoController extends AbstractController
{
    private CameraManager $cameraManager;

    private VideoFileManager $videoFileManager;

    public function __construct(CameraManager $cameraManager, VideoFileManager $videoFileManager)
    {
        $this->videoFileManager = $videoFileManager;
        $this->cameraManager = $cameraManager;
    }

    #[Route('/listVideos', name: 'list_videos')]
    public function listVideos(): Response
    {
        $cameras = $this->cameraManager->getCameras();
        $videos = [];
        foreach ($cameras as $camera) {
            $videos = array_merge($videos, $this->videoFileManager->getVideos($camera));
        }
        return $this->render('video/list.videos.html.twig', [
            'videos' => $videos,
        ]);
    }

    #[Route('/video/{uid}', name: 'show_video')]
    public function showVideo(string $uid): Response
    {
        return new Response('video test');
    }


    #[Route('/video/{uid}/stream', name: 'video_stream')]
    public function streamVideo(string $uid): Response
    {
        $video = $this->videoFileManager->findVideoByUid($uid);

        if (!$video) {
            throw $this->createNotFoundException('Video not found');
        }
        $filePath = $video->getPath();

        if (!file_exists($filePath)) {
            throw $this->createNotFoundException('File not found');
        }

        $response = new StreamedResponse(function () use ($filePath) {
            $stream = fopen($filePath, 'rb');
            while (!feof($stream)) {
                echo fread($stream, 1024 * 8);
                flush();
            }
            fclose($stream);
        });
        $response->headers->set('Content-Disposition', 'inline; filename="' . basename($filePath) . '"');

        return $response;
    }

    #[Route('/video/{uid}/lock', name: 'video_lock')]
    public function lockVideo(Video $video): Response
    {
        $video->setIsProtected(true);
        return new Response('video test');
    }

    #[Route('/video/{uid}/lock', name: 'video_unlock')]
    public function unlockVideo(Video $video): Response
    {
        $video->setIsProtected(false);
        return new Response('video test');
    }
}