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
    private readonly Request $request;

    public function __construct(private readonly CameraManager $cameraManager, private readonly VideoFileManager $videoFileManager, private readonly VideoRepository $videoRepository)
    {
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

        foreach (array_keys($this->request->request->all('delete')) as $videoUid) {
            $video = $this->videoRepository->findOneByUid($videoUid);
            if ($video instanceof Video) {
                if ($video->isProtected()) {
                    return new Response(null, Response::HTTP_FORBIDDEN);
                } elseif ($this->videoFileManager->deleteVideo($videoUid)) {
                    return new Response(null, Response::HTTP_NO_CONTENT);
                } else {
                    return new Response(null, Response::HTTP_INTERNAL_SERVER_ERROR);
                }
            } else {
                return new Response(null, Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }

        if (isset($this->request->request->all()['submission_method']) && $this->request->request->all()['submission_method'] == 'js') {
            return new Response(null, Response::HTTP_NO_CONTENT);
        }

        $cameras = $this->cameraManager->getCameras();
        $page = max(1, $this->request->query->getInt('page', 1));
        $perPage = 10;

        $paginator = $this->videoRepository->findAllPaginated($page, $perPage);

        $total = count($paginator);
        $pages = (int)max(1, ceil($total / $perPage));

        // redirect to last page if page number is too big
        if ($page > $pages && $pages > 0) {
            return $this->redirectToRoute('list_videos', ['page' => $pages]);
        }

        foreach ($paginator as $video) {
            foreach ($cameras as $camera) {
                // check if video path fits to camera path
                if (str_starts_with($video->getPath(), $camera->getVideoFolder())) {
                    $video->setCamera($camera);
                    break;
                }
            }
        }

        return $this->render('video/list.html.twig', [
            'videos' => $paginator,
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
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

        if (!$video instanceof \App\Entity\Video) {
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
        $video = $this->videoFileManager->findVideoByUid($uid);

        if (!$video instanceof \App\Entity\Video) {
            throw $this->createNotFoundException('Video not found');
        }

        $filePath = $video->getPath();

        if (!file_exists($filePath)) {
            throw $this->createNotFoundException('File not found');
        }

        $response = $this->file($filePath, basename($filePath), ResponseHeaderBag::DISPOSITION_ATTACHMENT);
        $response->headers->set('Cache-Control', 'max-age=31536000, immutable');
        return $response;
    }

//    #[Route('/video/{uid}/lock', name: 'video_lock')]
//    public function lockVideo(Video $video): Response
//    {
//        $video->setIsProtected(true);
//        return new Response('video locked');
//    }
//
//    #[Route('/video/{uid}/unlock', name: 'video_unlock')]
//    public function unlockVideo(Video $video): Response
//    {
//        $video->setIsProtected(false);
//        return new Response('video unlocked');
//    }

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