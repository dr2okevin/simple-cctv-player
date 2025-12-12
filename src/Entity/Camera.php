<?php

namespace App\Entity;

use App\Enum\CameraType;
use App\Service\CameraApiInterface;
use App\Service\ReolinkApiService;

class Camera
{
    protected string $uid;

    protected string $title;

    protected string $videoFolder;

    protected CameraType $type;

    protected string $liveUri;

    protected ?CameraApiSettings $cameraApiSettings = null;

    /**
     * @var int|null how much space in MB should be kept free
     */
    protected ?int $keepFreeSpace = null;

    /**
     * @var int the maximum age in hours
     */
    protected int $maxAge = 0;

    protected const defaultKeepFreeSpace = 1024;

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @param string $title
     */
    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    /**
     * @return string
     */
    public function getVideoFolder(): string
    {
        return $this->videoFolder;
    }

    /**
     * @param string $videoFolder
     */
    public function setVideoFolder(string $videoFolder): void
    {
        if ($videoFolder !== '/') {
            $this->videoFolder = rtrim($videoFolder, '/');
        } else {
            $this->videoFolder = $videoFolder;
        }
    }

    /**
     * @return string
     */
    public function getLiveUri(): string
    {
        return $this->liveUri;
    }

    /**
     * @param string $liveUri
     */
    public function setLiveUri(string $liveUri): void
    {
        $this->liveUri = $liveUri;
    }

    /**
     * @return CameraType
     */
    public function getType(): CameraType
    {
        return $this->type;
    }

    /**
     * @param CameraType $type
     */
    public function setType(CameraType $type): void
    {
        $this->type = $type;
    }

    /**
     * @return string
     */
    public function getUid(): string
    {
        return $this->uid;
    }

    /**
     * @param string $uid
     */
    public function setUid(string $uid): void
    {
        $this->uid = $uid;
    }

    /**
     * @return int
     */
    public function getKeepFreeSpace(): int
    {
        if (!$this->keepFreeSpace == null) {
            return $this->keepFreeSpace;
        } else {
            return self::defaultKeepFreeSpace;
        }
    }

    /**
     * @param int|string $keepFreeSpace
     */
    public function setKeepFreeSpace(int|string $keepFreeSpace): void
    {
        //Integers are interpreted as Megabyte
        if (is_int($keepFreeSpace)) {
            $this->keepFreeSpace = $keepFreeSpace;
            return;
        }

        $keepFreeSpace = strtolower(trim($keepFreeSpace));

        //What unit is it?
        if (preg_match('/^([\d\.]+)\s*(kb|mb|gb|tb|pb)?$/', $keepFreeSpace, $matches)) {
            $numeric = (float)$matches[1];
            $suffix = $matches[2] ?? '';

            // convert to MB
            switch ($suffix) {
                case 'kb':
                    $numeric /= 1024; // MB in kB
                    break;
                case 'gb':
                    $numeric *= 1024; // GB in MB
                    break;
                case 'tb':
                    $numeric *= 1024 * 1024; // TB in MB
                    break;
                case 'pb':
                    $numeric *= 1024 * 1024 * 1024; // PB in MB
                    break;
                case 'mb':
                default:
                    // Is already MB or unknown
                    break;
            }

            $this->keepFreeSpace = (int)round($numeric);
        }
    }

    /**
     * @return int the maximum age in hours
     */
    public function getMaxAge(): int
    {
        return $this->maxAge;
    }

    /**
     * @param int|string $maxAge the maximum age in hours
     */
    public function setMaxAge(int|string $maxAge): void
    {
        if (is_string($maxAge)) {
            $maxAge = (int)$maxAge;
        }

        $this->maxAge = $maxAge;
    }

    /**
     * @return CameraApiSettings|null
     */
    public function getCameraApiSettings(): ?CameraApiSettings
    {
        return $this->cameraApiSettings;
    }

    /**
     * @param CameraApiSettings|null $cameraApiSettings
     */
    public function setCameraApiSettings(?CameraApiSettings $cameraApiSettings): void
    {
        $this->cameraApiSettings = $cameraApiSettings;
    }

    public function getCameraApi(): ?CameraApiInterface
    {
        if (!$this->getCameraApiSettings()) {
            return null;
        }
        switch ($this->getCameraApiSettings()->getType()) {
            case "reolink";
                return new ReolinkApiService($this->getCameraApiSettings());
        }
        return null;
    }
}