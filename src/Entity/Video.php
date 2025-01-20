<?php

namespace App\Entity;

use App\Enum\CameraType;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: "App\Repository\VideoRepository")]
#[ORM\Table(name: "videos")]
class Video
{
    #[ORM\Id]
    #[ORM\Column(type: "string", length: 14, unique: true)]
    protected string $uid;

    #[ORM\Column(type: "string", length: 255)]
    protected string $path;

    #[ORM\Column(type: "string", length: 255)]
    protected string $title;

    #[ORM\Column(type: "string", length: 50, enumType: CameraType::class)]
    protected CameraType $cameraType;

    #[ORM\Column(type: "boolean")]
    protected bool $isProtected = false;

    #[ORM\Column(type: "datetime")]
    protected \DateTime $recordTime;

    #[ORM\Column(type: "integer", nullable: true)]
    protected ?int $size;

    #[ORM\Column(type: "integer", nullable: true)]
    protected ?int $duration;

    public function __construct(string $path, string $title, CameraType $cameraType)
    {
        $this->setPath($path);
        $this->setTitle($title);
        $this->uid = self::calculateUid($path);
        $this->setCameraType($cameraType);
    }

    public static function calculateUid(string $path): string
    {
        return substr(sha1($path), 0, 16);
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