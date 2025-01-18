<?php

namespace App\Service;

use App\Entity\Camera;
use App\Enum\CameraType;

class CameraManager implements CameraManagerInterface
{
    private string $configPath;

    public function __construct(string $configPath)
    {
        $this->configPath = $configPath;
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
        foreach ($cameraArrays['cameras'] as $cameraArray) {
            $camera = new Camera();
            $camera->setTitle($cameraArray['title']);
            $camera->setVideoFolder($cameraArray['videoFolder']);
            $camera->setType(CameraType::from($cameraArray['cameraType']));
            $camera->setLiveUri($cameraArray['liveUri'] ?? '');

            $cameraObjects[] = $camera;
        }

        return $cameraObjects;
    }

}