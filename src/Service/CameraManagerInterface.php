<?php

namespace App\Service;

use App\Entity\Camera;

interface CameraManagerInterface
{
    public function __construct(string $configPath);

    /**
     * @return Camera[]
     */
    public function getCameras(): array;
}