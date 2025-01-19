<?php

namespace App\Entity;

use App\Enum\CameraType;

class Video
{
    protected string $uid;

    protected string $path;

    protected string $title;

    protected CameraType $cameraType;

    protected bool $isProtected = false;

    protected \DateTime $recordTime;

    protected ?int $size;

    protected ?int $duration;

    public function __construct(string $path, string $title, CameraType $cameraType)
    {
        $this->setPath($path);
        $this->setTitle($title);
        $this->uid = substr(sha1($path), 0, 14);
        $this->cameraType = $cameraType;
    }

    /**
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @param string $path
     */
    public function setPath(string $path): void
    {
        $this->path = $path;
    }

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
     * @return int|null
     */
    public function getSize(): ?int
    {
        return $this->size;
    }

    /**
     * @param int|null $size
     */
    public function setSize(?int $size): void
    {
        $this->size = $size;
    }

    /**
     * @return \DateTime
     */
    public function getRecordTime(): \DateTime
    {
        return $this->recordTime;
    }

    /**
     * @param \DateTime $recordTime
     */
    public function setRecordTime(\DateTime $recordTime): void
    {
        $this->recordTime = $recordTime;
    }

    /**
     * @return int|null
     */
    public function getDuration(): ?int
    {
        return $this->duration;
    }

    /**
     * @param int $duration
     */
    public function setDuration(int $duration): void
    {
        $this->duration = $duration;
    }

    /**
     * @return bool
     */
    public function isProtected(): bool
    {
        return $this->isProtected;
    }

    /**
     * @param bool $isProtected
     */
    public function setIsProtected(bool $isProtected): void
    {
        $this->isProtected = $isProtected;
    }

    /**
     * @return string
     */
    public function getUid(): string
    {
        return $this->uid;
    }

    /**
     * @return CameraType
     */
    public function getCameraType(): CameraType
    {
        return $this->cameraType;
    }

    /**
     * @param CameraType $cameraType
     */
    public function setCameraType(CameraType $cameraType): void
    {
        $this->cameraType = $cameraType;
    }
}