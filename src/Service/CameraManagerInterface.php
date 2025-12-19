<?php

namespace App\Service;

use App\Entity\Camera;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\KernelInterface;

interface CameraManagerInterface
{
    public function __construct(string $configPath, KernelInterface $kernel, LoggerInterface $logger);

    /**
     * @return Camera[]
     */
    public function getCameras(): array;
}