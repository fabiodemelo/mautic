<?php

declare(strict_types=1);

namespace MauticPlugin\MauticSendGridSyncBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\LeadBundle\Entity\DoNotContact;
use Mautic\LeadBundle\Model\DoNotContactModel;
use Mautic\LeadBundle\Model\ListModel;
use MauticPlugin\MauticSendGridSyncBundle\Entity\Suppression;
use MauticPlugin\MauticSendGridSyncBundle\Entity\SuppressionRepository;
use MauticPlugin\MauticSendGridSyncBundle\Entity\SyncLog;
use MauticPlugin\MauticSendGridSyncBundle\Entity\SyncLogRepository;
use Psr\Log\LoggerInterface;

class SyncEngine
{
    private const BATCH_SIZE = 100;

    public function __construct(
        private readonly SuppressionFetcher $suppressionFetcher,
        private readonly ContactResolver $contactResolver,
        private readonly DncMapper $dncMapper,
        private readonly DoNotContactModel $dncModel,
        private readonly ListModel $listModel,
        private readonly EntityManagerInterface $entityManager,
        private readonly NotificationService $notificationService,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Run the sync process.
     *
     * @param array $settings Feature settings from integration config
     */
    public function sync(
        string $syncType = SyncLog::TYPE_INCREMENTAL,
        array $settings = [],
        ?string $specificType = null,
        bool $dryRun = false,
    ): SyncLog {
        $syncLog = new SyncLog();
        $syncLog->setSyncType($syncType);
        $this->entityManager->persist($syncLog);
        $this->entityManager->flush();

        try {
            $enabledTypes = $settings['enabled_types'] ?? Suppression::ALL_TYPES;
            if (null !== $specificType) {
                $enabledTypes = [$specificType];
            }

            $startTime = $this->resolveStartTime($syncType, $settings);

            $this->logger->info('Starting SendGrid sync', [
                'type'         => $syncType,
                'enabledTypes' => $enabledTypes,
                'startTime'    => $startTime,
                'dryRun'       => $dryRun,
            ]);

            $allSuppressions = $this->suppressionFetcher->fetchAll($enabledTypes, $startTime);

            $totalFetched = 0;
            $totalAdded   = 0;
            $totalSkipped = 0;
            $totalUnmatched = 0;
            $breakdown    = [];

            foreach ($allSuppressions as $type => $suppressions) {
                $count = count($suppressions);
                $totalFetched += $count;
                $breakdown[$type] = 0;

                if (0 === $count) {
                    continue;
                }

                $emails    = array_column($suppressions, 'email');
                $contactMap = $this->contactResolver->resolveByEmails($emails);

                $actionMode    = $this->getActionMode($type, $settings);
                $targetSegment = $this->getTargetSegment($type, $settings);

                $batchCount = 0;

                foreach ($suppressions as $record) {
                    $email = $record['email'];

                    $suppressionRepo = $this->getSuppressionRepository();
                    if ($suppressionRepo->existsBySendgridKey($email, $type, $record['created_at'])) {
                        ++$totalSkipped;
                        continue;
                    }

                    $contact   = $contactMap[$email] ?? null;
                    $contactId = null !== $contact ? $contact->getId() : null;

                    $suppression = new Suppression();
                    $suppression->setEmail($email);
                    $suppression->setSuppressionType($type);
                    $suppression->setSendgridReason($record['reason']);
                    $suppression->setSendgridStatus($record['status']);
                    $suppression->setSendgridCreatedAt($record['created_at']);
                    $suppression->setSendgridGroupId($record['group_id']);
                    $suppression->setSendgridGroupName($record['group_name']);
                    $suppression->setMauticContactId($contactId);

                    if (null === $contact) {
                        $suppression->setActionTaken(Suppression::ACTION_UNMATCHED);
                        ++$totalUnmatched;
                    } elseif (!$dryRun) {
                        if ('segment' === $actionMode && null !== $targetSegment) {
                            $this->addContactToSegment($contact, $targetSegment);
                            $suppression->setActionTaken(Suppression::ACTION_SEGMENT);
                        } else {
                            $dncReason = $this->dncMapper->getDncReason($type);
                            $comment   = $this->dncMapper->buildComment($type, $record['reason'], $record['status']);
                            $this->dncModel->addDncForContact(
                                $contact,
                                'email',
                                $dncReason,
                                $comment,
                            );
                            $suppression->setActionTaken(Suppression::ACTION_DNC);
                        }
                        ++$totalAdded;
                        ++$breakdown[$type];
                    }

                    $this->entityManager->persist($suppression);
                    ++$batchCount;

                    if ($batchCount % self::BATCH_SIZE === 0) {
                        $this->entityManager->flush();
                        $this->entityManager->clear(Suppression::class);
                    }
                }

                $this->entityManager->flush();
            }

            $syncLog->setRecordsFetched($totalFetched);
            $syncLog->setRecordsAdded($totalAdded);
            $syncLog->setRecordsSkipped($totalSkipped);
            $syncLog->setRecordsUnmatched($totalUnmatched);
            $syncLog->setSuppressionBreakdown($breakdown);
            $syncLog->markCompleted();

            $this->entityManager->persist($syncLog);
            $this->entityManager->flush();

            $this->logger->info('SendGrid sync completed', [
                'fetched'   => $totalFetched,
                'added'     => $totalAdded,
                'skipped'   => $totalSkipped,
                'unmatched' => $totalUnmatched,
            ]);

            $this->checkForSpike($breakdown, $settings);

        } catch (\Throwable $e) {
            $this->logger->error('SendGrid sync failed', ['error' => $e->getMessage()]);

            $syncLog->markFailed($e->getMessage());
            $this->entityManager->persist($syncLog);
            $this->entityManager->flush();

            $notificationEmail = $settings['notification_email'] ?? null;
            if (null !== $notificationEmail && '' !== $notificationEmail) {
                $this->notificationService->sendSyncFailureNotification($syncLog, $notificationEmail);
            }
        }

        return $syncLog;
    }

    public function getLastSyncTimestamp(): int
    {
        return $this->getSyncLogRepository()->getLastSyncTimestamp();
    }

    private function resolveStartTime(string $syncType, array $settings): int
    {
        if (SyncLog::TYPE_FULL === $syncType) {
            $initialRange = (int) ($settings['initial_sync_range'] ?? 30);
            if (0 === $initialRange) {
                return 0;
            }

            return (new \DateTime("-{$initialRange} days"))->getTimestamp();
        }

        $lastTimestamp = $this->getLastSyncTimestamp();
        if (0 === $lastTimestamp) {
            $initialRange = (int) ($settings['initial_sync_range'] ?? 30);
            if (0 === $initialRange) {
                return 0;
            }

            return (new \DateTime("-{$initialRange} days"))->getTimestamp();
        }

        return $lastTimestamp;
    }

    private function getActionMode(string $type, array $settings): string
    {
        $actionModes = $settings['action_modes'] ?? [];

        return $actionModes[$type] ?? ($settings['default_action_mode'] ?? 'dnc');
    }

    private function getTargetSegment(string $type, array $settings): ?int
    {
        $targetSegments = $settings['target_segments'] ?? [];
        $segmentId      = $targetSegments[$type] ?? ($settings['default_target_segment'] ?? null);

        return null !== $segmentId ? (int) $segmentId : null;
    }

    private function addContactToSegment(object $contact, int $segmentId): void
    {
        $segment = $this->listModel->getEntity($segmentId);
        if (null === $segment) {
            $this->logger->warning("Target segment {$segmentId} not found");

            return;
        }

        $this->listModel->addLead($contact, $segment, true);
    }

    private function checkForSpike(array $breakdown, array $settings): void
    {
        $threshold         = (int) ($settings['spike_threshold'] ?? 50);
        $notificationEmail = $settings['notification_email'] ?? null;

        if (null === $notificationEmail || '' === $notificationEmail || $threshold <= 0) {
            return;
        }

        foreach ($breakdown as $type => $count) {
            if ($count >= $threshold) {
                $this->notificationService->sendSpikeAlert($type, $count, $threshold, $notificationEmail);
            }
        }
    }

    private function getSyncLogRepository(): SyncLogRepository
    {
        return $this->entityManager->getRepository(SyncLog::class);
    }

    private function getSuppressionRepository(): SuppressionRepository
    {
        return $this->entityManager->getRepository(Suppression::class);
    }
}
