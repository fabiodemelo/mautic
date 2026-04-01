<?php

declare(strict_types=1);

namespace MauticPlugin\MauticSyncDataBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use MauticPlugin\MauticSyncDataBundle\Entity\Suppression;
use MauticPlugin\MauticSyncDataBundle\Entity\SuppressionRepository;
use MauticPlugin\MauticSyncDataBundle\Entity\SyncLog;
use MauticPlugin\MauticSyncDataBundle\Entity\SyncLogRepository;

class StatsCalculator
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function getSummaryCards(): array
    {
        $suppressionRepo = $this->getSuppressionRepository();
        $syncLogRepo     = $this->getSyncLogRepository();

        $now = new \DateTime();

        return [
            'total_synced'       => $suppressionRepo->getTotalCount(),
            'new_24h'            => $suppressionRepo->getCountSince((clone $now)->modify('-24 hours')),
            'new_7d'             => $suppressionRepo->getCountSince((clone $now)->modify('-7 days')),
            'new_30d'            => $suppressionRepo->getCountSince((clone $now)->modify('-30 days')),
            'contacts_protected' => $suppressionRepo->getProtectedContactsCount(),
            'last_sync'          => $this->getLastSyncInfo($syncLogRepo),
        ];
    }

    public function getBreakdownData(): array
    {
        $breakdown = $this->getSuppressionRepository()->getBreakdownByType();

        $colors = [
            Suppression::TYPE_BOUNCE             => '#e74c3c',
            Suppression::TYPE_SPAM_REPORT        => '#e67e22',
            Suppression::TYPE_BLOCK              => '#f1c40f',
            Suppression::TYPE_INVALID_EMAIL      => '#9b59b6',
            Suppression::TYPE_GLOBAL_UNSUBSCRIBE => '#3498db',
            Suppression::TYPE_GROUP_UNSUBSCRIBE  => '#1abc9c',
        ];

        $labels    = [];
        $data      = [];
        $chartColors = [];

        foreach ($breakdown as $type => $count) {
            $labels[]      = Suppression::getTypeLabel($type);
            $data[]        = $count;
            $chartColors[] = $colors[$type] ?? '#95a5a6';
        }

        return [
            'labels' => $labels,
            'data'   => $data,
            'colors' => $chartColors,
        ];
    }

    public function getTrendData(string $period = '30d', ?string $type = null): array
    {
        $days = (int) str_replace('d', '', $period);
        if ($days <= 0) {
            $days = 30;
        }

        $raw = $this->getSuppressionRepository()->getTrendData("{$days}", $type);

        $labels   = [];
        $datasets = [];

        foreach ($raw as $row) {
            $labels[]   = $row['date_label'];
            $datasets[] = (int) $row['cnt'];
        }

        return [
            'labels'   => $labels,
            'datasets' => [
                [
                    'label'           => null !== $type ? Suppression::getTypeLabel($type) : 'All Suppressions',
                    'data'            => $datasets,
                    'borderColor'     => '#3498db',
                    'backgroundColor' => 'rgba(52, 152, 219, 0.1)',
                    'fill'            => true,
                ],
            ],
        ];
    }

    public function getRecentSuppressions(int $page = 1, int $limit = 25, array $filters = []): array
    {
        $result = $this->getSuppressionRepository()->getRecentSuppressions($page, $limit, $filters);

        $items = [];
        foreach ($result['items'] as $suppression) {
            $items[] = [
                'id'              => $suppression->getId(),
                'email'           => $suppression->getEmail(),
                'type'            => $suppression->getSuppressionType(),
                'type_label'      => Suppression::getTypeLabel($suppression->getSuppressionType()),
                'reason'          => $suppression->getSourceReason(),
                'status'          => $suppression->getSourceStatus(),
                'source_date'   => $suppression->getSourceCreatedAt()->format('Y-m-d H:i:s'),
                'synced_date'     => $suppression->getSyncedAt()->format('Y-m-d H:i:s'),
                'action'          => $suppression->getActionTaken(),
                'contact_id'      => $suppression->getMauticContactId(),
            ];
        }

        return [
            'items' => $items,
            'total' => $result['total'],
            'page'  => $result['page'],
            'pages' => $result['pages'],
        ];
    }

    public function getSyncHistory(int $page = 1, int $limit = 20): array
    {
        $result = $this->getSyncLogRepository()->getSyncHistory($page, $limit);

        $items = [];
        foreach ($result['items'] as $log) {
            $items[] = [
                'id'         => $log->getId(),
                'sync_type'  => $log->getSyncType(),
                'status'     => $log->getStatus(),
                'started_at' => $log->getStartedAt()->format('Y-m-d H:i:s'),
                'duration'   => $log->getDurationSeconds(),
                'fetched'    => $log->getRecordsFetched(),
                'added'      => $log->getRecordsAdded(),
                'skipped'    => $log->getRecordsSkipped(),
                'unmatched'  => $log->getRecordsUnmatched(),
                'error'      => $log->getErrorMessage(),
                'breakdown'  => $log->getSuppressionBreakdown(),
            ];
        }

        return [
            'items' => $items,
            'total' => $result['total'],
            'page'  => $result['page'],
            'pages' => $result['pages'],
        ];
    }

    private function getLastSyncInfo(SyncLogRepository $repo): array
    {
        $lastSync = $repo->getLastSuccessfulSync();

        if (null === $lastSync) {
            return ['time' => null, 'status' => 'never'];
        }

        return [
            'time'   => $lastSync->getCompletedAt()->format('Y-m-d H:i:s'),
            'status' => $lastSync->getStatus(),
        ];
    }

    private function getSuppressionRepository(): SuppressionRepository
    {
        return $this->entityManager->getRepository(Suppression::class);
    }

    private function getSyncLogRepository(): SyncLogRepository
    {
        return $this->entityManager->getRepository(SyncLog::class);
    }
}
