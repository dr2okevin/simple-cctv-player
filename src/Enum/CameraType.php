<?php

namespace App\Enum;

enum CameraType: string {
    case Reolink = 'reolink';
    case Unifi = 'unifi';
}