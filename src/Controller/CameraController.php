<?php

namespace App\Controller;

use App\Entity\Camera;
use App\Enum\CameraType;
use App\Service\CameraManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

class CameraController extends AbstractController
{
    private readonly Request $request;

    public function __construct(private readonly CameraManager $cameraManager)
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
        return $response;
    }
}