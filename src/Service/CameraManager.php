<?php

namespace App\Service;

use App\Entity\Camera;
use App\Enum\CameraType;
use Symfony\Component\HttpKernel\KernelInterface;

class CameraManager implements CameraManagerInterface
{
    private string $configPath;

    protected KernelInterface $kernel;

    public function __construct(string $configPath, KernelInterface $kernel)
    {
        $this->configPath = $configPath;
        $this->kernel = $kernel;
    }

    /**
     * @inheritDoc
     */
    public function getCameras(): array
    {
        if (!file_exists($this->configPath) || !is_readable($this->configPath)) {
            throw new \Exception('Config file not found or not readable: ' . $this->configPath);
        }

        $cameraArrays = json_decode(file_get_contents($this->configPath), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception(sprintf('Invalid JSON in %s: %s', $this->configPath, json_last_error_msg()));
        }

        $cameraObjects = [];
        /**
         * @var array{title: string, videoFolder: string, cameraType: string, liveUri: ?string} $cameraArray
         */
        foreach ($cameraArrays['cameras'] as $uid => $cameraArray) {
            $camera = new Camera();
            $camera->setUid($uid);
            $camera->setTitle($cameraArray['title']);
            $camera->setVideoFolder($cameraArray['videoFolder']);
            $camera->setType(CameraType::from($cameraArray['cameraType']));
            $camera->setLiveUri($cameraArray['liveUri'] ?? '');

            $cameraObjects[] = $camera;
        }

        return $cameraObjects;
    }

    public function getPreview(Camera $camera): ?string
    {
        $cacheTime = 10; //time in seconds
        $projectDir = $this->kernel->getProjectDir();
        $imageDir = $projectDir . '/var/cache/CameraPreviews/';
        if (!file_exists($imageDir)) {
            mkdir($imageDir, 0774, true);
        }
        $imagePath = $imageDir . "Camera_" . $camera->getUid() . ".jpg";
        if (file_exists($imagePath) && filectime($imagePath) >= time() - $cacheTime) {
            return $imagePath;
        }
        if (empty($camera->getLiveUri())) {
            return null;
        }
        $ffmpegCommand = "ffmpeg -y -i " . escapeshellarg($camera->getLiveUri()) . " -vframes 1 -rtsp_transport tcp " . escapeshellarg($imagePath);
        exec($ffmpegCommand, $output, $returnVar);
        if ($returnVar !== 0) {
            return null;
        }
        return $imagePath;
    }

}