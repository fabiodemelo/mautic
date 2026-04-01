<?php

declare(strict_types=1);

namespace MauticPlugin\MauticSyncDataBundle\Tests\Unit\Service;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Model\DoNotContactModel;
use Mautic\LeadBundle\Model\ListModel;
use MauticPlugin\MauticSyncDataBundle\Entity\Suppression;
use MauticPlugin\MauticSyncDataBundle\Entity\SuppressionRepository;
use MauticPlugin\MauticSyncDataBundle\Entity\SyncLog;
use MauticPlugin\MauticSyncDataBundle\Entity\SyncLogRepository;
use MauticPlugin\MauticSyncDataBundle\Service\ContactResolver;
use MauticPlugin\MauticSyncDataBundle\Service\DncMapper;
use MauticPlugin\MauticSyncDataBundle\Service\NotificationService;
use MauticPlugin\MauticSyncDataBundle\Service\SuppressionFetcher;
use MauticPlugin\MauticSyncDataBundle\Service\SyncEngine;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class SyncEngineTest extends TestCase
{
    private SuppressionFetcher|MockObject $fetcher;
    private ContactResolver|MockObject $contactResolver;
    private DoNotContactModel|MockObject $dncModel;
    private ListModel|MockObject $listModel;
    private EntityManagerInterface|MockObject $em;
    private NotificationService|MockObject $notification;
    private SyncEngine $engine;

    protected function setUp(): void
    {
        $this->fetcher         = $this->createMock(SuppressionFetcher::class);
        $this->contactResolver = $this->createMock(ContactResolver::class);
        $this->dncModel        = $this->createMock(DoNotContactModel::class);
        $this->listModel       = $this->createMock(ListModel::class);
        $this->em              = $this->createMock(EntityManagerInterface::class);
        $this->notification    = $this->createMock(NotificationService::class);

        $syncLogRepo       = $this->createMock(SyncLogRepository::class);
        $suppressionRepo   = $this->createMock(SuppressionRepository::class);

        $syncLogRepo->method('getLastSyncTimestamp')->willReturn(0);
        $suppressionRepo->method('existsBySourceKey')->willReturn(false);

        $this->em->method('getRepository')
            ->willReturnMap([
                [SyncLog::class, $syncLogRepo],
                [Suppression::class, $suppressionRepo],
            ]);

        $this->em->method('persist')->willReturn(null);
        $this->em->method('flush')->willReturn(null);

        $this->engine = new SyncEngine(
            $this->fetcher,
            $this->contactResolver,
            new DncMapper(),
            $this->dncModel,
            $this->listModel,
            $this->em,
            $this->notification,
            new NullLogger(),
        );
    }

    public function testSyncAddsDncForMatchedContacts(): void
    {
        $contact = $this->createMock(Lead::class);
        $contact->method('getId')->willReturn(42);

        $this->fetcher->method('fetchAll')->willReturn([
            Suppression::TYPE_BOUNCE => [
                [
                    'email'      => 'test@example.com',
                    'type'       => Suppression::TYPE_BOUNCE,
                    'reason'     => '550 User unknown',
                    'status'     => '5.1.1',
                    'created_at' => new \DateTime('2026-03-29'),
                    'group_id'   => null,
                    'group_name' => null,
                ],
            ],
        ]);

        $this->contactResolver->method('resolveByEmails')
            ->willReturn(['test@example.com' => $contact]);

        $this->dncModel->expects($this->once())
            ->method('addDncForContact');

        $syncLog = $this->engine->sync(SyncLog::TYPE_MANUAL, [
            'enabled_types'       => [Suppression::TYPE_BOUNCE],
            'default_action_mode' => 'dnc',
        ]);

        $this->assertSame(SyncLog::STATUS_SUCCESS, $syncLog->getStatus());
        $this->assertSame(1, $syncLog->getRecordsFetched());
        $this->assertSame(1, $syncLog->getRecordsAdded());
        $this->assertSame(0, $syncLog->getRecordsUnmatched());
    }

    public function testSyncRecordsUnmatchedContacts(): void
    {
        $this->fetcher->method('fetchAll')->willReturn([
            Suppression::TYPE_BOUNCE => [
                [
                    'email'      => 'unknown@example.com',
                    'type'       => Suppression::TYPE_BOUNCE,
                    'reason'     => '550 User unknown',
                    'status'     => '5.1.1',
                    'created_at' => new \DateTime('2026-03-29'),
                    'group_id'   => null,
                    'group_name' => null,
                ],
            ],
        ]);

        $this->contactResolver->method('resolveByEmails')
            ->willReturn(['unknown@example.com' => null]);

        $this->dncModel->expects($this->never())
            ->method('addDncForContact');

        $syncLog = $this->engine->sync(SyncLog::TYPE_MANUAL, [
            'enabled_types' => [Suppression::TYPE_BOUNCE],
        ]);

        $this->assertSame(1, $syncLog->getRecordsUnmatched());
        $this->assertSame(0, $syncLog->getRecordsAdded());
    }

    public function testSyncHandlesApiFailure(): void
    {
        $this->fetcher->method('fetchAll')
            ->willThrowException(new \RuntimeException('API connection failed'));

        $syncLog = $this->engine->sync(SyncLog::TYPE_MANUAL, [
            'enabled_types' => [Suppression::TYPE_BOUNCE],
        ]);

        $this->assertSame(SyncLog::STATUS_FAILED, $syncLog->getStatus());
        $this->assertStringContainsString('API connection failed', $syncLog->getErrorMessage());
    }

    public function testDryRunDoesNotAddDnc(): void
    {
        $contact = $this->createMock(Lead::class);
        $contact->method('getId')->willReturn(42);

        $this->fetcher->method('fetchAll')->willReturn([
            Suppression::TYPE_BOUNCE => [
                [
                    'email'      => 'test@example.com',
                    'type'       => Suppression::TYPE_BOUNCE,
                    'reason'     => 'Bounce',
                    'status'     => '5.1.1',
                    'created_at' => new \DateTime('2026-03-29'),
                    'group_id'   => null,
                    'group_name' => null,
                ],
            ],
        ]);

        $this->contactResolver->method('resolveByEmails')
            ->willReturn(['test@example.com' => $contact]);

        $this->dncModel->expects($this->never())
            ->method('addDncForContact');

        $syncLog = $this->engine->sync(SyncLog::TYPE_MANUAL, [
            'enabled_types' => [Suppression::TYPE_BOUNCE],
        ], null, true);

        $this->assertSame(SyncLog::STATUS_SUCCESS, $syncLog->getStatus());
    }

    public function testSyncSendsNotificationOnFailure(): void
    {
        $this->fetcher->method('fetchAll')
            ->willThrowException(new \RuntimeException('API error'));

        $this->notification->expects($this->once())
            ->method('sendSyncFailureNotification');

        $this->engine->sync(SyncLog::TYPE_MANUAL, [
            'enabled_types'      => [Suppression::TYPE_BOUNCE],
            'notification_email' => 'admin@example.com',
        ]);
    }
}
