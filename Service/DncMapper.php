<?php

declare(strict_types=1);

namespace MauticPlugin\MauticSyncDataBundle\Service;

use Mautic\LeadBundle\Entity\DoNotContact;
use MauticPlugin\MauticSyncDataBundle\Entity\Suppression;

class DncMapper
{
    /**
     * Provider event → Mautic DNC reason.
     *
     *  - Deliverability problems (bounce, block, invalid, spam) → BOUNCED (2)
     *    so they are tracked under the same "do not retry — bad address"
     *    bucket and do not pollute the user-driven UNSUBSCRIBED list.
     *  - Explicit user opt-outs (global / group unsubscribe) → UNSUBSCRIBED (1)
     */
    private const TYPE_TO_DNC = [
        Suppression::TYPE_BOUNCE             => DoNotContact::BOUNCED,
        Suppression::TYPE_SPAM_REPORT        => DoNotContact::BOUNCED,
        Suppression::TYPE_BLOCK              => DoNotContact::BOUNCED,
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
        $parts = ["[SyncData] {$label}"];

        if (null !== $status && '' !== $status) {
            $parts[] = $status;
        }

        if (null !== $reason && '' !== $reason) {
            $parts[] = $reason;
        }

        return implode(': ', $parts);
    }
}
