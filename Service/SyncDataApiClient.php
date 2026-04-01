<?php

declare(strict_types=1);

namespace MauticPlugin\MauticSyncDataBundle\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class SyncDataApiClient
{
    private const BASE_URL    = 'https://api.sendgrid.com';
    private const PAGE_LIMIT  = 500;

    private ?string $apiKey = null;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function setApiKey(string $apiKey): void
    {
        $this->apiKey = $apiKey;
    }

    public function testConnection(): array
    {
        try {
            $response = $this->request('GET', '/v3/user/profile');

            return [
                'success' => true,
                'account' => $response['username'] ?? $response['first_name'] ?? 'Unknown',
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error'   => $e->getMessage(),
            ];
        }
    }

    public function getBounces(int $startTime = 0, int $limit = self::PAGE_LIMIT, int $offset = 0): array
    {
        $query = ['limit' => $limit, 'offset' => $offset];
        if ($startTime > 0) {
            $query['start_time'] = $startTime;
        }

        return $this->request('GET', '/v3/suppression/bounces', $query);
    }

    public function getSpamReports(int $startTime = 0, int $limit = self::PAGE_LIMIT, int $offset = 0): array
    {
        $query = ['limit' => $limit, 'offset' => $offset];
        if ($startTime > 0) {
            $query['start_time'] = $startTime;
        }

        return $this->request('GET', '/v3/suppression/spam_reports', $query);
    }

    public function getBlocks(int $startTime = 0, int $limit = self::PAGE_LIMIT, int $offset = 0): array
    {
        $query = ['limit' => $limit, 'offset' => $offset];
        if ($startTime > 0) {
            $query['start_time'] = $startTime;
        }

        return $this->request('GET', '/v3/suppression/blocks', $query);
    }

    public function getInvalidEmails(int $startTime = 0, int $limit = self::PAGE_LIMIT, int $offset = 0): array
    {
        $query = ['limit' => $limit, 'offset' => $offset];
        if ($startTime > 0) {
            $query['start_time'] = $startTime;
        }

        return $this->request('GET', '/v3/suppression/invalid_emails', $query);
    }

    public function getGlobalUnsubscribes(int $startTime = 0, int $limit = self::PAGE_LIMIT, int $offset = 0): array
    {
        $query = ['limit' => $limit, 'offset' => $offset];
        if ($startTime > 0) {
            $query['start_time'] = $startTime;
        }

        return $this->request('GET', '/v3/suppression/unsubscribes', $query);
    }

    public function getGroupUnsubscribes(int $groupId): array
    {
        return $this->request('GET', sprintf('/v3/asm/groups/%d/suppressions', $groupId));
    }

    public function getUnsubscribeGroups(): array
    {
        return $this->request('GET', '/v3/asm/groups');
    }

    /**
     * @throws \RuntimeException
     */
    private function request(string $method, string $endpoint, array $query = []): array
    {
        if (null === $this->apiKey || '' === $this->apiKey) {
            throw new \RuntimeException('SyncData API key is not configured.');
        }

        $options = [
            'headers' => [
                'Authorization' => 'Bearer '.$this->apiKey,
                'Content-Type'  => 'application/json',
            ],
        ];

        if (!empty($query)) {
            $options['query'] = $query;
        }

        $url = self::BASE_URL.$endpoint;

        $this->logger->debug('SyncData API request', ['method' => $method, 'url' => $url]);

        $response   = $this->httpClient->request($method, $url, $options);
        $statusCode = $response->getStatusCode();

        if ($statusCode >= 400) {
            $body    = $response->getContent(false);
            $decoded = json_decode($body, true);
            $errors  = $decoded['errors'] ?? [];
            $message = !empty($errors) ? $errors[0]['message'] : "HTTP {$statusCode}";

            $this->logger->error('SyncData API error', [
                'status' => $statusCode,
                'body'   => $body,
            ]);

            throw new \RuntimeException("SyncData API error: {$message}");
        }

        $headers = $response->getHeaders();
        $this->checkRateLimit($headers);

        return $response->toArray();
    }

    private function checkRateLimit(array $headers): void
    {
        $remaining = $headers['x-ratelimit-remaining'][0] ?? null;

        if (null !== $remaining && (int) $remaining < 10) {
            $this->logger->warning('SyncData rate limit nearly exhausted', [
                'remaining' => $remaining,
            ]);
            usleep(500000); // 0.5s pause
        }
    }
}
