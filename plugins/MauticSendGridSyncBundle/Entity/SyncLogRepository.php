<?php

declare(strict_types=1);

namespace MauticPlugin\MauticSendGridSyncBundle\Entity;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class SyncLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SyncLog::class);
    }

    public function getLastSuccessfulSync(): ?SyncLog
    {
        return $this->createQueryBuilder('sl')
            ->where('sl.status = :status')
            ->setParameter('status', SyncLog::STATUS_SUCCESS)
            ->orderBy('sl.completedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function getLastSyncTimestamp(): int
    {
        $lastSync = $this->getLastSuccessfulSync();

        if (null === $lastSync) {
            return 0;
        }

        return $lastSync->getCompletedAt()->getTimestamp();
    }

    public function getSyncHistory(int $page = 1, int $limit = 20): array
    {
        $qb = $this->createQueryBuilder('sl')
            ->orderBy('sl.startedAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $items = $qb->getQuery()->getResult();

        $total = (int) $this->createQueryBuilder('sl')
            ->select('COUNT(sl.id)')
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'items' => $items,
            'total' => $total,
            'page'  => $page,
            'pages' => (int) ceil($total / $limit),
        ];
    }
}
