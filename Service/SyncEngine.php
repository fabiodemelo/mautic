<?php

declare(strict_types=1);

namespace MauticPlugin\MauticSyncDataBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\LeadBundle\Entity\DoNotContact as DoNotContactEntity;
use Mautic\LeadBundle\Model\DoNotContact;
use Mautic\LeadBundle\Model\ListModel;
use MauticPlugin\MauticSyncDataBundle\Entity\Suppression;
use MauticPlugin\MauticSyncDataBundle\Entity\SuppressionRepository;
use MauticPlugin\MauticSyncDataBundle\Entity\SyncLog;
use MauticPlugin\MauticSyncDataBundle\Entity\SyncLogRepository;
use Psr\Log\LoggerInterface;

class SyncEngine
{
    private const BATCH_SIZE = 100;

    public function __construct(
        private readonly SuppressionFetcher $suppressionFetcher,
        private readonly ContactResolver $contactResolver,
        private readonly DncMapper $dncMapper,
        private readonly DoNotContact $dncModel,
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

            $this->logger->info('Starting SyncData sync', [
                'type'         => $syncType,
                'enabledTypes' => $enabledTypes,
                'startTime'    => $startTime,
                'dryRun'       => $dryRun,
            ]);

            // Try to re-link any previously UNMATCHED records (contact may have been added since)
            $this->relinkUnmatched($settings, $dryRun);

            $allSuppressions = $this->suppressionFetcher->fetchAll($enabledTypes, $startTime);

            $maxPerSync = (int) ($settings['max_per_sync'] ?? 0);

            $totalFetched   = 0;
            $totalAdded     = 0;
            $totalSkipped   = 0;
            $totalUnmatched = 0;
            $totalProcessed = 0;
            $breakdown      = [];
            $reachedCap     = false;

            foreach ($allSuppressions as $type => $suppressions) {
                $count = count($suppressions);
                $totalFetched += $count;
                $breakdown[$type] = 0;

                if (0 === $count || $reachedCap) {
                    continue;
                }

                $emails    = array_column($suppressions, 'email');
                $contactMap = $this->contactResolver->resolveByEmails($emails);

                $actionMode    = $this->getActionMode($type, $settings);
                $targetSegment = $this->getTargetSegment($type, $settings);

                $batchCount = 0;

                foreach ($suppressions as $record) {
                    if ($maxPerSync > 0 && $totalProcessed >= $maxPerSync) {
                        $reachedCap = true;
                        break;
                    }
                    ++$totalProcessed;
                    $email = $record['email'];

                    $suppressionRepo = $this->getSuppressionRepository();
                    if ($suppressionRepo->existsBySourceKey($email, $type, $record['created_at'])) {
                        ++$totalSkipped;
                        continue;
                    }

                    $contact   = $contactMap[$email] ?? null;
                    $contactId = null !== $contact ? $contact->getId() : null;

                    $suppression = new Suppression();
                    $suppression->setEmail($email);
                    $suppression->setSuppressionType($type);
                    $suppression->setSourceReason($record['reason']);
                    $suppression->setSourceStatus($record['status']);
                    $suppression->setSourceCreatedAt($record['created_at']);
                    $suppression->setSourceGroupId($record['group_id']);
                    $suppression->setSourceGroupName($record['group_name']);
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

            $this->logger->info('SyncData sync completed', [
                'fetched'   => $totalFetched,
                'added'     => $totalAdded,
                'skipped'   => $totalSkipped,
                'unmatched' => $totalUnmatched,
            ]);

            $this->checkForSpike($breakdown, $settings);

        } catch (\Throwable $e) {
            $this->logger->error('SyncData sync failed', ['error' => $e->getMessage()]);

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

    /**
     * Re-link any UNMATCHED suppressions whose contact has since been created.
     */
    private function relinkUnmatched(array $settings, bool $dryRun): void
    {
        $unmatched = $this->getSuppressionRepository()->findUnmatched(500);
        if (empty($unmatched)) {
            return;
        }

        $emails     = array_map(fn (Suppression $s) => $s->getEmail(), $unmatched);
        $contactMap = $this->contactResolver->resolveByEmails($emails);

        foreach ($unmatched as $suppression) {
            $contact = $contactMap[strtolower($suppression->getEmail())] ?? null;
            if (null === $contact) {
                continue;
            }

            $suppression->setMauticContactId($contact->getId());
            $type       = $suppression->getSuppressionType();
            $actionMode = $this->getActionMode($type, $settings);
            $segmentId  = $this->getTargetSegment($type, $settings);

            if ($dryRun) {
                continue;
            }

            if ('segment' === $actionMode && null !== $segmentId) {
                $this->addContactToSegment($contact, $segmentId);
                $suppression->setActionTaken(Suppression::ACTION_SEGMENT);
            } else {
                $dncReason = $this->dncMapper->getDncReason($type);
                $comment   = $this->dncMapper->buildComment(
                    $type,
                    $suppression->getSourceReason(),
                    $suppression->getSourceStatus(),
                );
                $this->dncModel->addDncForContact($contact, 'email', $dncReason, $comment);
                $suppression->setActionTaken(Suppression::ACTION_DNC);
            }
        }

        $this->entityManager->flush();
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
