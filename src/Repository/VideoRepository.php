<?php

namespace App\Repository;

use App\Entity\Camera;
use App\Entity\Video;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Video>
 */
class VideoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Video::class);
    }

    public function save(Video $video): void
    {
        $this->getEntityManager()->persist($video);
        $this->getEntityManager()->flush();
    }

    public function remove(Video $video): void
    {
        $this->getEntityManager()->remove($video);
        $this->getEntityManager()->flush();
    }

    public function findDeletableVideosByCamera(Camera $camera): array
    {
        $folder = $camera->getVideoFolder();
        // Wir filtern alle Videos, deren Pfad mit dem Kamera-Ordner beginnt.
        $qb = $this->createQueryBuilder('v')
            ->where('v.path LIKE :folder')
            ->andWhere('v.isProtected = :protected')
            ->setParameter('folder', $folder . '/%')
            ->setParameter('protected', false)
            ->orderBy('v.recordTime', 'ASC');

        return $qb->getQuery()->getResult();
    }

    public function findDeletableVideosByCameraAndAge(Camera $camera, \DateTime $maxTime): array
    {
        $folder = $camera->getVideoFolder();
        // Wir filtern alle Videos, deren Pfad mit dem Kamera-Ordner beginnt.
        $qb = $this->createQueryBuilder('v')
            ->where('v.path LIKE :folder')
            ->andWhere('v.isProtected = :protected')
            ->andWhere('v.recordTime < :maxTimestamp')
            ->setParameter('folder', $folder . '/%')
            ->setParameter('protected', false)
            ->setParameter('maxTimestamp', $maxTime)
            ->orderBy('v.recordTime', 'ASC');

        return $qb->getQuery()->getResult();
    }

    public function findAllUidsByCamera(Camera $camera)
    {
        $folder = $camera->getVideoFolder();
        $qb = $this->createQueryBuilder('v')
            ->select('v.uid')
            ->where('v.path LIKE :folder')
            ->setParameter('folder', $folder . '/%')
            ->orderBy('v.recordTime', 'ASC');

        return $qb->getQuery()->getSingleColumnResult();
    }

    public function findLatestVideoByCamera(mixed $camera): Video
    {
        $folder = $camera->getVideoFolder();
        $qb = $this->createQueryBuilder('v')
            ->select('v')
            ->where('v.path LIKE :folder')
            ->setParameter('folder', $folder . '/%')
            ->setMaxResults(1)
            ->orderBy('v.recordTime', 'ASC');

        return $qb->getQuery()->getSingleResult();
    }


}
