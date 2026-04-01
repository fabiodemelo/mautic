<?php

declare(strict_types=1);

namespace MauticPlugin\MauticSyncDataBundle\Tests\Unit\Service;

use Mautic\LeadBundle\Entity\DoNotContact;
use MauticPlugin\MauticSyncDataBundle\Entity\Suppression;
use MauticPlugin\MauticSyncDataBundle\Service\DncMapper;
use PHPUnit\Framework\TestCase;

class DncMapperTest extends TestCase
{
    private DncMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new DncMapper();
    }

    public function testBounceMapsToBounced(): void
    {
        $this->assertSame(DoNotContact::BOUNCED, $this->mapper->getDncReason(Suppression::TYPE_BOUNCE));
    }

    public function testSpamReportMapsToUnsubscribed(): void
    {
        $this->assertSame(DoNotContact::UNSUBSCRIBED, $this->mapper->getDncReason(Suppression::TYPE_SPAM_REPORT));
    }

    public function testBlockMapsToManual(): void
    {
        $this->assertSame(DoNotContact::MANUAL, $this->mapper->getDncReason(Suppression::TYPE_BLOCK));
    }

    public function testInvalidEmailMapsToBounced(): void
    {
        $this->assertSame(DoNotContact::BOUNCED, $this->mapper->getDncReason(Suppression::TYPE_INVALID_EMAIL));
    }

    public function testGlobalUnsubscribeMapsToUnsubscribed(): void
    {
        $this->assertSame(DoNotContact::UNSUBSCRIBED, $this->mapper->getDncReason(Suppression::TYPE_GLOBAL_UNSUBSCRIBE));
    }

    public function testGroupUnsubscribeMapsToUnsubscribed(): void
    {
        $this->assertSame(DoNotContact::UNSUBSCRIBED, $this->mapper->getDncReason(Suppression::TYPE_GROUP_UNSUBSCRIBE));
    }

    public function testUnknownTypeDefaultsToManual(): void
    {
        $this->assertSame(DoNotContact::MANUAL, $this->mapper->getDncReason('unknown_type'));
    }

    public function testBuildCommentWithAllParts(): void
    {
        $comment = $this->mapper->buildComment(Suppression::TYPE_BOUNCE, '550 User unknown', '5.1.1');
        $this->assertSame('[SendGrid Sync] Bounce: 5.1.1: 550 User unknown', $comment);
    }

    public function testBuildCommentWithReasonOnly(): void
    {
        $comment = $this->mapper->buildComment(Suppression::TYPE_SPAM_REPORT, 'User reported spam', null);
        $this->assertSame('[SendGrid Sync] Spam Report: User reported spam', $comment);
    }

    public function testBuildCommentWithStatusOnly(): void
    {
        $comment = $this->mapper->buildComment(Suppression::TYPE_BLOCK, null, '4.0.0');
        $this->assertSame('[SendGrid Sync] Block: 4.0.0', $comment);
    }

    public function testBuildCommentWithNoParts(): void
    {
        $comment = $this->mapper->buildComment(Suppression::TYPE_GLOBAL_UNSUBSCRIBE, null, null);
        $this->assertSame('[SendGrid Sync] Global Unsubscribe', $comment);
    }
}
