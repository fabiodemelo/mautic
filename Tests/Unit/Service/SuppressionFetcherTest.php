<?php

declare(strict_types=1);

namespace MauticPlugin\MauticSyncDataBundle\Tests\Unit\Service;

use MauticPlugin\MauticSyncDataBundle\Entity\Suppression;
use MauticPlugin\MauticSyncDataBundle\Service\SyncDataApiClient;
use MauticPlugin\MauticSyncDataBundle\Service\SuppressionFetcher;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class SuppressionFetcherTest extends TestCase
{
    private SyncDataApiClient|MockObject $apiClient;
    private SuppressionFetcher $fetcher;

    protected function setUp(): void
    {
        $this->apiClient = $this->createMock(SyncDataApiClient::class);
        $this->fetcher   = new SuppressionFetcher($this->apiClient, new NullLogger());
    }

    public function testFetchBouncesNormalizesRecords(): void
    {
        $this->apiClient->method('getBounces')->willReturn([
            [
                'email'   => 'test@example.com',
                'reason'  => '550 User unknown',
                'status'  => '5.1.1',
                'created' => 1711756800,
            ],
        ]);

        $result = $this->fetcher->fetchByType(Suppression::TYPE_BOUNCE, 0);

        $this->assertCount(1, $result);
        $this->assertSame('test@example.com', $result[0]['email']);
        $this->assertSame(Suppression::TYPE_BOUNCE, $result[0]['type']);
        $this->assertSame('550 User unknown', $result[0]['reason']);
        $this->assertSame('5.1.1', $result[0]['status']);
        $this->assertInstanceOf(\DateTimeInterface::class, $result[0]['created_at']);
    }

    public function testFetchSpamReports(): void
    {
        $this->apiClient->method('getSpamReports')->willReturn([
            ['email' => 'spammer@example.com', 'created' => 1711756800],
        ]);

        $result = $this->fetcher->fetchByType(Suppression::TYPE_SPAM_REPORT, 0);

        $this->assertCount(1, $result);
        $this->assertSame('spammer@example.com', $result[0]['email']);
        $this->assertSame(Suppression::TYPE_SPAM_REPORT, $result[0]['type']);
    }

    public function testFetchAllCallsEnabledTypes(): void
    {
        $this->apiClient->method('getBounces')->willReturn([
            ['email' => 'a@example.com', 'created' => 1711756800],
        ]);
        $this->apiClient->method('getBlocks')->willReturn([
            ['email' => 'b@example.com', 'created' => 1711756800],
        ]);

        $result = $this->fetcher->fetchAll([Suppression::TYPE_BOUNCE, Suppression::TYPE_BLOCK], 0);

        $this->assertArrayHasKey(Suppression::TYPE_BOUNCE, $result);
        $this->assertArrayHasKey(Suppression::TYPE_BLOCK, $result);
        $this->assertCount(1, $result[Suppression::TYPE_BOUNCE]);
        $this->assertCount(1, $result[Suppression::TYPE_BLOCK]);
    }

    public function testEmptyEmailsAreSkipped(): void
    {
        $this->apiClient->method('getBounces')->willReturn([
            ['email' => '', 'created' => 1711756800],
            ['email' => 'valid@example.com', 'created' => 1711756800],
        ]);

        $result = $this->fetcher->fetchByType(Suppression::TYPE_BOUNCE, 0);

        $this->assertCount(1, $result);
        $this->assertSame('valid@example.com', $result[0]['email']);
    }

    public function testEmailsAreLowercased(): void
    {
        $this->apiClient->method('getBounces')->willReturn([
            ['email' => 'Test@Example.COM', 'created' => 1711756800],
        ]);

        $result = $this->fetcher->fetchByType(Suppression::TYPE_BOUNCE, 0);

        $this->assertSame('test@example.com', $result[0]['email']);
    }

    public function testFetchGroupUnsubscribes(): void
    {
        $this->apiClient->method('getUnsubscribeGroups')->willReturn([
            ['id' => 1, 'name' => 'Marketing'],
            ['id' => 2, 'name' => 'Newsletter'],
        ]);

        $this->apiClient->method('getGroupUnsubscribes')
            ->willReturnMap([
                [1, ['user1@example.com', 'user2@example.com']],
                [2, ['user3@example.com']],
            ]);

        $result = $this->fetcher->fetchByType(Suppression::TYPE_GROUP_UNSUBSCRIBE, 0);

        $this->assertCount(3, $result);
        $this->assertSame(Suppression::TYPE_GROUP_UNSUBSCRIBE, $result[0]['type']);
        $this->assertSame(1, $result[0]['group_id']);
        $this->assertSame('Marketing', $result[0]['group_name']);
    }

    public function testApiErrorForOneTypeDoesNotBreakAll(): void
    {
        $this->apiClient->method('getBounces')->willReturn([
            ['email' => 'a@example.com', 'created' => 1711756800],
        ]);
        $this->apiClient->method('getSpamReports')
            ->willThrowException(new \RuntimeException('API Error'));

        $result = $this->fetcher->fetchAll([Suppression::TYPE_BOUNCE, Suppression::TYPE_SPAM_REPORT], 0);

        $this->assertCount(1, $result[Suppression::TYPE_BOUNCE]);
        $this->assertEmpty($result[Suppression::TYPE_SPAM_REPORT]);
    }
}
