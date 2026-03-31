<?php

declare(strict_types=1);

namespace MauticPlugin\MauticSendGridSyncBundle\Service;

use MauticPlugin\MauticSendGridSyncBundle\Entity\Suppression;
use Psr\Log\LoggerInterface;

class SuppressionFetcher
{
    private const PAGE_LIMIT = 500;

    public function __construct(
        private readonly SendGridApiClient $apiClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Fetch all enabled suppression types.
     *
     * @param string[] $enabledTypes
     *
     * @return array<string, array> Keyed by suppression type
     */
    public function fetchAll(array $enabledTypes, int $startTime = 0): array
    {
        $results = [];

        foreach ($enabledTypes as $type) {
            try {
                $results[$type] = $this->fetchByType($type, $startTime);
                $this->logger->info("Fetched {$type} suppressions", ['count' => count($results[$type])]);
            } catch (\Throwable $e) {
                $this->logger->error("Failed to fetch {$type}", ['error' => $e->getMessage()]);
                $results[$type] = [];
            }
        }

        return $results;
    }

    /**
     * Fetch suppressions for a single type.
     *
     * @return array Normalized suppression records
     */
    public function fetchByType(string $type, int $startTime = 0): array
    {
        if ($type === Suppression::TYPE_GROUP_UNSUBSCRIBE) {
            return $this->fetchGroupUnsubscribes();
        }

        return $this->paginateFetch($type, $startTime);
    }

    /**
     * Normalize a raw SendGrid suppression record to a common structure.
     */
    private function normalize(string $type, array $raw): array
    {
        $createdTimestamp = $raw['created'] ?? $raw['created_at'] ?? 0;

        return [
            'email'      => strtolower(trim($raw['email'] ?? '')),
            'type'       => $type,
            'reason'     => $raw['reason'] ?? null,
            'status'     => $raw['status'] ?? null,
            'created_at' => (new \DateTime())->setTimestamp((int) $createdTimestamp),
            'group_id'   => $raw['group_id'] ?? null,
            'group_name' => $raw['group_name'] ?? null,
        ];
    }

    private function paginateFetch(string $type, int $startTime): array
    {
        $allRecords = [];
        $offset     = 0;

        do {
            $raw = $this->callApiByType($type, $startTime, self::PAGE_LIMIT, $offset);

            foreach ($raw as $record) {
                $normalized = $this->normalize($type, $record);
                if ('' !== $normalized['email']) {
                    $allRecords[] = $normalized;
                }
            }

            $offset += self::PAGE_LIMIT;
        } while (count($raw) === self::PAGE_LIMIT);

        return $allRecords;
    }

    private function fetchGroupUnsubscribes(): array
    {
        $groups     = $this->apiClient->getUnsubscribeGroups();
        $allRecords = [];

        foreach ($groups as $group) {
            $groupId   = (int) ($group['id'] ?? 0);
            $groupName = $group['name'] ?? '';

            if (0 === $groupId) {
                continue;
            }

            try {
                $emails = $this->apiClient->getGroupUnsubscribes($groupId);

                foreach ($emails as $emailRecord) {
                    $email = is_string($emailRecord) ? $emailRecord : ($emailRecord['email'] ?? '');
                    if ('' === $email) {
                        continue;
                    }

                    $allRecords[] = [
                        'email'      => strtolower(trim($email)),
                        'type'       => Suppression::TYPE_GROUP_UNSUBSCRIBE,
                        'reason'     => "Unsubscribed from group: {$groupName}",
                        'status'     => null,
                        'created_at' => new \DateTime(),
                        'group_id'   => $groupId,
                        'group_name' => $groupName,
                    ];
                }
            } catch (\Throwable $e) {
                $this->logger->error("Failed to fetch group unsubscribes for group {$groupId}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $allRecords;
    }

    private function callApiByType(string $type, int $startTime, int $limit, int $offset): array
    {
        return match ($type) {
            Suppression::TYPE_BOUNCE             => $this->apiClient->getBounces($startTime, $limit, $offset),
            Suppression::TYPE_SPAM_REPORT        => $this->apiClient->getSpamReports($startTime, $limit, $offset),
            Suppression::TYPE_BLOCK              => $this->apiClient->getBlocks($startTime, $limit, $offset),
            Suppression::TYPE_INVALID_EMAIL      => $this->apiClient->getInvalidEmails($startTime, $limit, $offset),
            Suppression::TYPE_GLOBAL_UNSUBSCRIBE => $this->apiClient->getGlobalUnsubscribes($startTime, $limit, $offset),
            default => throw new \InvalidArgumentException("Unknown suppression type: {$type}"),
        };
    }
}
