<?php

namespace App\Service;

use App\Entity\Camera;
use Symfony\Component\HttpKernel\KernelInterface;

interface CameraManagerInterface
{
    public function __construct(string $configPath, KernelInterface $kernel);

    /**
     * @return Camera[]
     */
    public function getCameras(): array;
}