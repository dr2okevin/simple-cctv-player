<?php

namespace App\Service;

use App\Entity\Camera;
use App\Enum\CameraType;
use Symfony\Component\HttpKernel\KernelInterface;

class CameraManager implements CameraManagerInterface
{
    public function __construct(private readonly string $configPath, protected KernelInterface $kernel)
    {
    }

    /**
     * @inheritDoc
     */
    public function getCameras(): array
    {
        if (!file_exists($this->configPath)) {
            $json = json_encode([]);
            $file = fopen($this->configPath, 'w');
            fwrite($file, $json);
        }

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
            $camera->setKeepFreeSpace($cameraArray['keepFreeSpace'] ?? '');
            $camera->setMaxAge($cameraArray['maxAge'] ?? 0);

            $cameraObjects[$uid] = $camera;
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

    public function updateCamera(Camera $camera): bool
    {
        $cameras = $this->getCameras();
        $cameras[$camera->getUid()] = $camera;
        return $this->writeCameraSettings($cameras);
    }

    /**
     * @param Camera[] $cameras
     * @return bool
     */
    protected function writeCameraSettings(array $cameras): bool
    {
        foreach ($cameras as $camera) {
            $cameras[$camera->getUid()] = [
                'title' => $camera->getTitle(),
                'videoFolder' => $camera->getVideoFolder(),
                'cameraType' => $camera->getType()->value,
                'liveUri' => $camera->getLiveUri(),
                'keepFreeSpace' => $camera->getKeepFreeSpace(),
                'maxAge' => $camera->getMaxAge()
            ];
        }
        $cameras = ['cameras' => $cameras];
        $configString = json_encode($cameras, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($configString === false) {
            return false;
        }
        $file = fopen($this->configPath, 'w');
        $writeRet = fwrite($file, $configString);
        if ($writeRet === false) {
            return false;
        }
        return true;
    }

}