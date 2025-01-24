<?php

namespace App\Controller;

use App\Entity\Video;
use App\Repository\VideoRepository;
use App\Service\CameraManager;
use App\Service\VideoFileManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

class VideoController extends AbstractController
{
    private CameraManager $cameraManager;

    private VideoFileManager $videoFileManager;

    private Request $request;

    private VideoRepository $videoRepository;

    public function __construct(CameraManager $cameraManager, VideoFileManager $videoFileManager, VideoRepository $videoRepository)
    {
        $this->videoFileManager = $videoFileManager;
        $this->cameraManager = $cameraManager;
        $this->videoRepository = $videoRepository;
        $this->request = Request::createFromGlobals();
    }

    #[Route('/listVideos', name: 'list_videos')]
    public function listVideos(): Response
    {

        foreach ($this->request->request->all('protected') as $videoUid => $protected) {
            $video = $this->videoRepository->findOneByUid($videoUid);
            if ($video instanceof Video) {
                if ($protected === 'true' || $protected === '1' || $protected === true) {
                    $video->setIsProtected(true);
                } elseif ($protected === 'false' || $protected === '0' || $protected === false) {
                    $video->setIsProtected(false);
                }
                $this->videoRepository->save($video);
            }
        }
        if(isset($this->request->request->all()['submission_method']) && $this->request->request->all()['submission_method'] == 'js'){
            return new Response(null, 204);
        }

        $cameras = $this->cameraManager->getCameras();
        $videos = [];
        foreach ($cameras as $camera) {
            $videos = array_merge($videos, $this->videoFileManager->getVideos($camera));
        }
        return $this->render('video/list.html.twig', [
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

    #[Route('/video/{uid}/download', name: 'video_download')]
    public function downloadVideo(string $uid): Response
    {
        //@todo
        return $this->streamVideo($uid);
    }

    #[Route('/video/{uid}/lock', name: 'video_lock')]
    public function lockVideo(Video $video): Response
    {
        $video->setIsProtected(true);
        return new Response('video locked');
    }

    #[Route('/video/{uid}/lock', name: 'video_unlock')]
    public function unlockVideo(Video $video): Response
    {
        $video->setIsProtected(false);
        return new Response('video unlocked');
    }

    #[Route('/video/{uid}/thumbnail', name: 'video_thumbnail')]
    public function videoThumbnail(string $uid): Response
    {
        $thumbnail = $this->videoFileManager->getThumbnail($uid);
        if ($thumbnail === null) {
            throw $this->createNotFoundException('File not found');
        }
        $response = $this->file($thumbnail, basename($thumbnail), ResponseHeaderBag::DISPOSITION_ATTACHMENT);
        $response->headers->set('Cache-Control', 'max-age=31536000, immutable');
        return $response;
    }
}