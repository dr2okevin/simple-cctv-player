<?php

namespace App\Service;

use App\Entity\CameraApiSettings;

interface CameraApiInterface
{
    public function __construct(CameraApiSettings $apiSettings);

    public function enableRecording(): bool;

    public function disableRecording(): bool;

    public function enableSiren(): bool;

    public function disableSiren(): bool;
}