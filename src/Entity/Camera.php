<?php

namespace App\Entity;

use App\Enum\CameraType;

class Camera
{
    protected string $uid;

    protected string $title;

    protected string $videoFolder;

    protected CameraType $type;

    protected string $liveUri;

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
        if($videoFolder !== '/'){
            $this->videoFolder = rtrim($videoFolder, '/');
        }
        else {
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
}