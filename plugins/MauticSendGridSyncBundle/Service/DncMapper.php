<?php

declare(strict_types=1);

namespace MauticPlugin\MauticSendGridSyncBundle\Service;

use Mautic\LeadBundle\Entity\DoNotContact;
use MauticPlugin\MauticSendGridSyncBundle\Entity\Suppression;

class DncMapper
{
    private const TYPE_TO_DNC = [
        Suppression::TYPE_BOUNCE             => DoNotContact::BOUNCED,
        Suppression::TYPE_SPAM_REPORT        => DoNotContact::UNSUBSCRIBED,
        Suppression::TYPE_BLOCK              => DoNotContact::MANUAL,
        Suppression::TYPE_INVALID_EMAIL      => DoNotContact::BOUNCED,
        Suppression::TYPE_GLOBAL_UNSUBSCRIBE => DoNotContact::UNSUBSCRIBED,
        Suppression::TYPE_GROUP_UNSUBSCRIBE  => DoNotContact::UNSUBSCRIBED,
    ];

    public function getDncReason(string $suppressionType): int
    {
        return self::TYPE_TO_DNC[$suppressionType] ?? DoNotContact::MANUAL;
    }

    public function buildComment(string $type, ?string $reason, ?string $status): string
    {
        $label = Suppression::getTypeLabel($type);
        $parts = ["[SendGrid Sync] {$label}"];

        if (null !== $status && '' !== $status) {
            $parts[] = $status;
        }

        if (null !== $reason && '' !== $reason) {
            $parts[] = $reason;
        }

        return implode(': ', $parts);
    }
}
