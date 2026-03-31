<?php

declare(strict_types=1);

namespace MauticPlugin\MauticSendGridSyncBundle\Controller;

use Mautic\CoreBundle\Controller\AbstractFormController;
use Mautic\IntegrationsBundle\Helper\IntegrationsHelper;
use MauticPlugin\MauticSendGridSyncBundle\Entity\SyncLog;
use MauticPlugin\MauticSendGridSyncBundle\Integration\SendGridSyncIntegration;
use MauticPlugin\MauticSendGridSyncBundle\Service\SendGridApiClient;
use MauticPlugin\MauticSendGridSyncBundle\Service\SyncEngine;
use Symfony\Component\HttpFoundation\JsonResponse;

class SyncController extends AbstractFormController
{
    public function __construct(
        private readonly SyncEngine $syncEngine,
        private readonly IntegrationsHelper $integrationsHelper,
        private readonly SendGridApiClient $apiClient,
    ) {
    }

    public function runAction(): JsonResponse
    {
        if (!$this->security->isGranted('plugin:sendgridsync:settings:manage')) {
            return new JsonResponse(['success' => false, 'error' => 'Access denied'], 403);
        }

        try {
            $integration       = $this->integrationsHelper->getIntegration(SendGridSyncIntegration::NAME);
            $integrationConfig = $integration->getIntegrationConfiguration();
            $apiKeys           = $integrationConfig->getApiKeys();
            $featureSettings   = $integrationConfig->getFeatureSettings() ?? [];

            $apiKey = $apiKeys['api_key'] ?? '';
            if ('' === $apiKey) {
                return new JsonResponse([
                    'success' => false,
                    'error'   => 'SendGrid API key is not configured.',
                ]);
            }

            $this->apiClient->setApiKey($apiKey);

            $syncLog = $this->syncEngine->sync(SyncLog::TYPE_MANUAL, $featureSettings);

            if (SyncLog::STATUS_FAILED === $syncLog->getStatus()) {
                return new JsonResponse([
                    'success' => false,
                    'error'   => $syncLog->getErrorMessage(),
                ]);
            }

            return new JsonResponse([
                'success' => true,
                'message' => sprintf(
                    'Sync completed: %d fetched, %d added, %d skipped, %d unmatched',
                    $syncLog->getRecordsFetched(),
                    $syncLog->getRecordsAdded(),
                    $syncLog->getRecordsSkipped(),
                    $syncLog->getRecordsUnmatched(),
                ),
                'log_id'  => $syncLog->getId(),
            ]);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'success' => false,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    public function statusAction(int $logId): JsonResponse
    {
        if (!$this->security->isGranted('plugin:sendgridsync:dashboard:view')) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        $syncLog = $this->entityManager->getRepository(SyncLog::class)->find($logId);

        if (null === $syncLog) {
            return new JsonResponse(['error' => 'Sync log not found'], 404);
        }

        return new JsonResponse([
            'id'       => $syncLog->getId(),
            'status'   => $syncLog->getStatus(),
            'fetched'  => $syncLog->getRecordsFetched(),
            'added'    => $syncLog->getRecordsAdded(),
            'skipped'  => $syncLog->getRecordsSkipped(),
            'unmatched' => $syncLog->getRecordsUnmatched(),
            'duration' => $syncLog->getDurationSeconds(),
            'error'    => $syncLog->getErrorMessage(),
        ]);
    }
}
