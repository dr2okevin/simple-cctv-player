<?php

namespace App\Service;

use App\Entity\Camera;
use App\Entity\CameraApiSettings;
use App\Enum\CameraType;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Psr\Log\LoggerInterface;

class CameraManager implements CameraManagerInterface
{
    public function __construct(private readonly string $configPath, protected KernelInterface $kernel, private readonly LoggerInterface $logger)
    {
    }

    /**
     * @inheritDoc
     * @return Camera[]
     * @throws \Exception
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
         * @var array{title: string, videoFolder: string, cameraType: string, liveUri: ?string, keepFreeSpace: ?string} $cameraArray
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

            if (isset($cameraArray['cameraApi'])) {
                $camera->setCameraApiSettings(new CameraApiSettings($cameraArray['cameraApi']));
            }

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

        $cmd = [
            'ffmpeg',
            '-hide_banner', '-loglevel', 'error', '-nostdin',
            '-y',
            '-rtsp_transport', 'tcp',            // MUST be before -i
            #'-stimeout', '5000000',              // 5s (microseconds) for RTSP connect/read
            '-i', $camera->getLiveUri(),
            '-an', '-sn', '-dn',
            #'-skip_frame', 'nokey',              // only keyframes (avoids missing refs)
            '-frames:v', '1',
            $imagePath,
        ];

        $process = new Process($cmd);
        $process->setTimeout(10);
        $process->run();

        if (!$process->isSuccessful()) {
            $this->logger->error('ffmpeg failed', [
                'code' => $process->getExitCode(),
                'err' => $process->getErrorOutput(),
                'out' => $process->getOutput(),
                'cmd' => $process->getCommandLine(),
            ]);

            return null;
        }
        return $imagePath;
    }

    public function liveMp4(Camera $camera): ?StreamedResponse
    {
        $uri = $camera->getLiveUri();
        if (!$uri) {
            return null;
        }

        $cmd = [
            'ffmpeg',
            '-hide_banner', '-loglevel', 'error', '-nostdin',
            '-stimeout', '5000000',                 // 5s (microseconds)
            '-i', $uri,
            '-c:v', 'copy',                          // no re-encode
            '-movflags', 'frag_keyframe+empty_moov+default_base_moof',
            '-f', 'mp4',
            'pipe:1',
        ];

        $process = new Process($cmd);
        $process->setTimeout(null);

        $response = new StreamedResponse(function () use ($process) {
            // Push ffmpeg stdout to client
            $process->start(function (string $type, string $buffer) {
                if ($type === Process::OUT) {
                    echo $buffer;
                    @ob_flush();
                    flush();
                }
            });

            $process->wait(); // blocks until client disconnects/ffmpeg exits
        });

        $response->headers->set('Content-Type', 'video/mp4');
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');
        // If you're behind nginx, disable buffering for low latency
        $response->headers->set('X-Accel-Buffering', 'no');

        return $response;
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
        return $writeRet !== false;
    }

}