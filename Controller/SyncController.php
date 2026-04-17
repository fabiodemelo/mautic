<?php

declare(strict_types=1);

namespace MauticPlugin\MauticSyncDataBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\CoreBundle\Controller\CommonController;
use Mautic\IntegrationsBundle\Helper\IntegrationsHelper;
use MauticPlugin\MauticSyncDataBundle\Entity\SyncLog;
use MauticPlugin\MauticSyncDataBundle\Integration\SyncDataIntegration;
use MauticPlugin\MauticSyncDataBundle\Service\SyncDataApiClient;
use MauticPlugin\MauticSyncDataBundle\Service\SyncEngine;
use Symfony\Component\HttpFoundation\JsonResponse;

class SyncController extends CommonController
{
    public function runAction(
        SyncEngine $syncEngine,
        IntegrationsHelper $integrationsHelper,
        SyncDataApiClient $apiClient,
    ): JsonResponse {
        if (!$this->security->isGranted('plugin:syncdata:settings:edit')) {
            return new JsonResponse(['success' => false, 'error' => 'Access denied'], 403);
        }

        try {
            $integration       = $integrationsHelper->getIntegration(SyncDataIntegration::NAME);
            $integrationConfig = $integration->getIntegrationConfiguration();
            $apiKeys           = $integrationConfig->getApiKeys();
            $featureSettings   = $integrationConfig->getFeatureSettings() ?? [];

            $apiKey = $apiKeys['api_key'] ?? '';
            if ('' === $apiKey) {
                return new JsonResponse([
                    'success' => false,
                    'error'   => 'SyncData API key is not configured.',
                ]);
            }

            $apiClient->setApiKey($apiKey);

            $syncLog = $syncEngine->sync(SyncLog::TYPE_MANUAL, $featureSettings);

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

    public function statusAction(EntityManagerInterface $entityManager, int $logId): JsonResponse
    {
        if (!$this->security->isGranted('plugin:syncdata:dashboard:view')) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        $syncLog = $entityManager->getRepository(SyncLog::class)->find($logId);

        if (null === $syncLog) {
            return new JsonResponse(['error' => 'Sync log not found'], 404);
        }

        return new JsonResponse([
            'id'        => $syncLog->getId(),
            'status'    => $syncLog->getStatus(),
            'fetched'   => $syncLog->getRecordsFetched(),
            'added'     => $syncLog->getRecordsAdded(),
            'skipped'   => $syncLog->getRecordsSkipped(),
            'unmatched' => $syncLog->getRecordsUnmatched(),
            'duration'  => $syncLog->getDurationSeconds(),
            'error'     => $syncLog->getErrorMessage(),
        ]);
    }
}
