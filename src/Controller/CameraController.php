<?php

namespace App\Controller;

use App\Service\CameraManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

class CameraController extends AbstractController
{
    public function __construct(private readonly CameraManager $cameraManager)
    {
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