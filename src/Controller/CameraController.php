<?php

namespace App\Controller;

use App\Entity\Camera;
use App\Enum\CameraType;
use App\Repository\VideoRepository;
use App\Service\CameraManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

class CameraController extends AbstractController
{
    private readonly Request $request;

    public function __construct(private readonly CameraManager $cameraManager, private readonly VideoRepository $videoRepository)
    {
        $this->request = Request::createFromGlobals();
    }

    #[Route('/listCameras', name: 'list_cameras')]
    public function listCameras(): Response
    {
        $cameras = $this->cameraManager->getCameras();
        return $this->render('camera/list.html.twig', [
            'cameras' => $cameras,
        ]);
    }

    #[Route('/camera/{uid}', name: 'show_camera')]
    public function showCamera(int $uid): Response
    {
        $cameras = $this->cameraManager->getCameras();
        if (!isset($cameras[$uid])) {
            throw $this->createNotFoundException('Camera not found');
        }

        $camera = $cameras[$uid];

        if (isset($this->request->request->all()['camera'])) {
            $camera->setTitle($this->request->request->all('camera')['title']);
            $camera->setVideoFolder($this->request->request->all('camera')['videoFolder']);
            $camera->setType(CameraType::from($this->request->request->all('camera')['type']));
            $camera->setLiveUri($this->request->request->all('camera')['liveUri']);
            $camera->setKeepFreeSpace($this->request->request->all('camera')['keepFreeSpace']);
            $camera->setMaxAge($this->request->request->all('camera')['maxAge']);
            $this->cameraManager->updateCamera($camera);
        }

        return $this->render('camera/show.html.twig', [
            'camera' => $camera,
        ]);
    }

    #[Route('/camera/{uid}/preview', name: 'show_camera_preview')]
    public function showCameraPreview(int $uid): Response
    {
        $cameras = $this->cameraManager->getCameras();
        if (!isset($cameras[$uid])) {
            throw $this->createNotFoundException('Camera not found');
        }

        $camera = $cameras[$uid];
        $imagePath = $this->cameraManager->getPreview($camera);
        if ($imagePath === null) {
            //@todo maybe a 500 would be better
            throw $this->createNotFoundException('Image could not be generated');
        }

        $response = $this->file($imagePath, basename($imagePath), ResponseHeaderBag::DISPOSITION_INLINE);
        $response->headers->addCacheControlDirective('no-cache', true);
        return $response;
    }

    #[Route('/camera/{uid}/stream', name: 'show_camera_stream')]
    public function streamCamera(int $uid): StreamedResponse|Response
    {
        $cameras = $this->cameraManager->getCameras();
        if (!isset($cameras[$uid])) {
            throw $this->createNotFoundException('Camera not found');
        }

        $camera = $cameras[$uid];
        return $this->cameraManager->liveMp4($camera);
    }

    #[Route('/api/camera/{uid}/getLastRecordingTime', name: 'api_get_last_recording_time')]
    public function apiGetLastRecordingTime(int $uid): JsonResponse
    {
        $cameras = $this->cameraManager->getCameras();
        if (!isset($cameras[$uid])) {
            throw $this->createNotFoundException('Camera not found');
        }

        $camera = $cameras[$uid];

        $latestVideo = $this->videoRepository->findLatestVideoByCamera($camera);
        $response = $this->json($latestVideo->getRecordTime());
        $response->headers->addCacheControlDirective('no-cache', true);
        return $response;
    }

    #[Route('/api/camera/{uid}/enableSirene', name: 'api_enable_sirene')]
    public function apiEnableSirene(int $uid): JsonResponse
    {
        $cameras = $this->cameraManager->getCameras();
        if (!isset($cameras[$uid])) {
            throw $this->createNotFoundException('Camera not found');
        }

        $camera = $cameras[$uid];
        $api = $camera->getCameraApi();
        if ($api) {
            $response = $this->json($api->enableSiren());
        } else {
            $response = $this->json(false)->setStatusCode(500);
        }
        $response->headers->addCacheControlDirective('no-cache', true);
        return $response;
    }

    #[Route('/api/camera/{uid}/disableSirene', name: 'api_disable_sirene')]
    public function apiDisableSirene(int $uid): JsonResponse
    {
        $cameras = $this->cameraManager->getCameras();
        if (!isset($cameras[$uid])) {
            throw $this->createNotFoundException('Camera not found');
        }

        $camera = $cameras[$uid];
        $api = $camera->getCameraApi();
        if ($api) {
            $response = $this->json($api->disableSiren());
        } else {
            $response = $this->json(false)->setStatusCode(500);
        }
        $response->headers->addCacheControlDirective('no-cache', true);
        return $response;
    }
}