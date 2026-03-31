<?php

declare(strict_types=1);

namespace MauticPlugin\MauticSendGridSyncBundle\Entity;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class SuppressionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Suppression::class);
    }

    public function getTotalCount(): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getCountSince(\DateTimeInterface $since): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->where('s.syncedAt >= :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getProtectedContactsCount(): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(DISTINCT s.mauticContactId)')
            ->where('s.actionTaken != :unmatched')
            ->andWhere('s.mauticContactId IS NOT NULL')
            ->setParameter('unmatched', Suppression::ACTION_UNMATCHED)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getBreakdownByType(): array
    {
        $results = $this->createQueryBuilder('s')
            ->select('s.suppressionType, COUNT(s.id) as cnt')
            ->groupBy('s.suppressionType')
            ->orderBy('cnt', 'DESC')
            ->getQuery()
            ->getResult();

        $breakdown = [];
        foreach ($results as $row) {
            $breakdown[$row['suppressionType']] = (int) $row['cnt'];
        }

        return $breakdown;
    }

    public function getTrendData(string $period = '30d', ?string $type = null): array
    {
        $since = new \DateTime();
        $since->modify('-'.intval($period).' days');

        $qb = $this->getEntityManager()->getConnection()->createQueryBuilder()
            ->select("DATE(synced_at) as date_label, COUNT(*) as cnt")
            ->from('plugin_sendgrid_suppressions')
            ->where('synced_at >= :since')
            ->setParameter('since', $since->format('Y-m-d'))
            ->groupBy('date_label')
            ->orderBy('date_label', 'ASC');

        if (null !== $type) {
            $qb->andWhere('suppression_type = :type')
                ->setParameter('type', $type);
        }

        return $qb->executeQuery()->fetchAllAssociative();
    }

    public function getRecentSuppressions(int $page = 1, int $limit = 25, array $filters = []): array
    {
        $qb = $this->createQueryBuilder('s')
            ->orderBy('s.syncedAt', 'DESC');

        if (!empty($filters['type'])) {
            $qb->andWhere('s.suppressionType = :type')
                ->setParameter('type', $filters['type']);
        }

        if (!empty($filters['email'])) {
            $qb->andWhere('s.email LIKE :email')
                ->setParameter('email', '%'.$filters['email'].'%');
        }

        if (!empty($filters['dateFrom'])) {
            $qb->andWhere('s.syncedAt >= :dateFrom')
                ->setParameter('dateFrom', new \DateTime($filters['dateFrom']));
        }

        if (!empty($filters['dateTo'])) {
            $qb->andWhere('s.syncedAt <= :dateTo')
                ->setParameter('dateTo', new \DateTime($filters['dateTo']));
        }

        $total = (int) (clone $qb)
            ->select('COUNT(s.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $items = $qb
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return [
            'items' => $items,
            'total' => $total,
            'page'  => $page,
            'pages' => (int) ceil($total / $limit),
        ];
    }

    public function existsBySendgridKey(string $email, string $type, \DateTimeInterface $sendgridCreatedAt): bool
    {
        $count = (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->where('s.email = :email')
            ->andWhere('s.suppressionType = :type')
            ->andWhere('s.sendgridCreatedAt = :createdAt')
            ->setParameter('email', strtolower(trim($email)))
            ->setParameter('type', $type)
            ->setParameter('createdAt', $sendgridCreatedAt)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }
}
