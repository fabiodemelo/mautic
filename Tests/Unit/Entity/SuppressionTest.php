<?php

declare(strict_types=1);

namespace MauticPlugin\MauticSyncDataBundle\Tests\Unit\Entity;

use MauticPlugin\MauticSyncDataBundle\Entity\Suppression;
use PHPUnit\Framework\TestCase;

class SuppressionTest extends TestCase
{
    public function testEmailIsLowercasedAndTrimmed(): void
    {
        $suppression = new Suppression();
        $suppression->setEmail('  Test@Example.COM  ');

        $this->assertSame('test@example.com', $suppression->getEmail());
    }

    public function testAllTypesConstant(): void
    {
        $this->assertCount(6, Suppression::ALL_TYPES);
        $this->assertContains(Suppression::TYPE_BOUNCE, Suppression::ALL_TYPES);
        $this->assertContains(Suppression::TYPE_SPAM_REPORT, Suppression::ALL_TYPES);
        $this->assertContains(Suppression::TYPE_BLOCK, Suppression::ALL_TYPES);
        $this->assertContains(Suppression::TYPE_INVALID_EMAIL, Suppression::ALL_TYPES);
        $this->assertContains(Suppression::TYPE_GLOBAL_UNSUBSCRIBE, Suppression::ALL_TYPES);
        $this->assertContains(Suppression::TYPE_GROUP_UNSUBSCRIBE, Suppression::ALL_TYPES);
    }

    public function testGetTypeLabel(): void
    {
        $this->assertSame('Bounce', Suppression::getTypeLabel(Suppression::TYPE_BOUNCE));
        $this->assertSame('Spam Report', Suppression::getTypeLabel(Suppression::TYPE_SPAM_REPORT));
        $this->assertSame('Block', Suppression::getTypeLabel(Suppression::TYPE_BLOCK));
        $this->assertSame('Invalid Email', Suppression::getTypeLabel(Suppression::TYPE_INVALID_EMAIL));
        $this->assertSame('Global Unsubscribe', Suppression::getTypeLabel(Suppression::TYPE_GLOBAL_UNSUBSCRIBE));
        $this->assertSame('Group Unsubscribe', Suppression::getTypeLabel(Suppression::TYPE_GROUP_UNSUBSCRIBE));
    }

    public function testGetTypeLabelFallback(): void
    {
        $label = Suppression::getTypeLabel('some_unknown_type');
        $this->assertSame('Some unknown type', $label);
    }

    public function testDefaultActionIsUnmatched(): void
    {
        $suppression = new Suppression();
        $this->assertSame(Suppression::ACTION_UNMATCHED, $suppression->getActionTaken());
    }

    public function testSettersAndGetters(): void
    {
        $suppression = new Suppression();
        $now         = new \DateTime();

        $suppression->setEmail('user@test.com');
        $suppression->setSuppressionType(Suppression::TYPE_BOUNCE);
        $suppression->setSourceReason('Mailbox full');
        $suppression->setSourceStatus('4.2.2');
        $suppression->setSourceCreatedAt($now);
        $suppression->setSourceGroupId(5);
        $suppression->setSourceGroupName('Marketing');
        $suppression->setMauticContactId(123);
        $suppression->setActionTaken(Suppression::ACTION_DNC);

        $this->assertSame('user@test.com', $suppression->getEmail());
        $this->assertSame(Suppression::TYPE_BOUNCE, $suppression->getSuppressionType());
        $this->assertSame('Mailbox full', $suppression->getSourceReason());
        $this->assertSame('4.2.2', $suppression->getSourceStatus());
        $this->assertSame($now, $suppression->getSourceCreatedAt());
        $this->assertSame(5, $suppression->getSourceGroupId());
        $this->assertSame('Marketing', $suppression->getSourceGroupName());
        $this->assertSame(123, $suppression->getMauticContactId());
        $this->assertSame(Suppression::ACTION_DNC, $suppression->getActionTaken());
        $this->assertInstanceOf(\DateTimeInterface::class, $suppression->getSyncedAt());
        $this->assertInstanceOf(\DateTimeInterface::class, $suppression->getCreatedAt());
    }
}
