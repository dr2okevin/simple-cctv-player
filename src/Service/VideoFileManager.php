<?php

namespace App\Service;

use App\Entity\Camera;
use App\Entity\Video;

class VideoFileManager implements VideoFileManagerInterface
{
    /** @var string[] $videoExtensions */
    protected array $videoExtensions = ['mp4', 'mkv', 'avi'];

    /**
     * @inheritDoc
     */
    public function getVideos(Camera $camera): array
    {
        //first we want to get all files in the folder
        $folder = $camera->getVideoFolder();
        /** @var string[]|false $files */
        $files = scandir($folder);
        if ($files === false) {
            throw new \Exception('Could not read folder: ' . $folder);
        }
        //now we want to convert only the video files to objects
        $videoObjects = [];
        foreach ($files as $file) {
            $filenameArray = pathinfo($file);
            if (in_array($filenameArray['extension'], $this->videoExtensions)) {
                $fullPath = $folder . '/' . $file;
                $videoObject = new Video($fullPath, $filenameArray['filename'], $camera->getType());
                $videoObject->setSize($this->calculateVideoSize($videoObject));
                $videoObject->setRecordTime($this->calculateRecordTime($videoObject));
                $videoObject->setDuration($this->calculateDuration($videoObject));
                $videoObjects[] = $videoObject;
            }
        }
        return $videoObjects;
    }

    public function protectVideo(Video $video)
    {
        $video->setIsProtected(true);
    }

    public function unprotectVideo(Video $video)
    {
        $video->setIsProtected(false);
    }

    public function calculateVideoSize(Video $video): int
    {
        $size = filesize($video->getPath());
        if ($size !== false) {
            return $size;
        }
        return 0;
    }

    public function calculateRecordTime(Video $video): \DateTime
    {
        //@todo
        return new \DateTime();
    }

    public function calculateDuration(Video $video): int
    {
        $path = $video->getPath();
        if (file_exists($path)) {
            $command = 'ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 ' . $path;
            $duration = exec($command);
            if ($duration !== false && is_numeric($duration)) {
                return round($duration, 0);
            }
        }
        return 0;
    }
}