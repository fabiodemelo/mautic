<?php

declare(strict_types=1);

namespace MauticPlugin\MauticSyncDataBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\CoreBundle\Controller\CommonController;
use Mautic\IntegrationsBundle\Facade\EncryptionService;
use Mautic\IntegrationsBundle\Helper\IntegrationsHelper;
use Mautic\LeadBundle\Model\ListModel;
use MauticPlugin\MauticSyncDataBundle\Entity\Suppression;
use MauticPlugin\MauticSyncDataBundle\Integration\SyncDataIntegration;
use MauticPlugin\MauticSyncDataBundle\Service\SyncDataApiClient;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class SettingsController extends CommonController
{
    public function indexAction(IntegrationsHelper $integrationsHelper, ListModel $listModel): Response
    {
        if (!$this->security->isGranted('plugin:syncdata:settings:edit')) {
            return $this->accessDenied();
        }

        $settings       = $this->getSettings($integrationsHelper);
        $apiKeys        = $this->getApiKeys($integrationsHelper);
        $rawApiKey      = $apiKeys['api_key'] ?? null;
        $hasApiKey      = is_string($rawApiKey) && '' !== $rawApiKey;
        $apiKeyPreview  = $hasApiKey ? $this->maskApiKey($rawApiKey) : '';
        $segments       = $this->getSegmentChoices($listModel);

        return $this->delegateView([
            'viewParameters' => [
                'settings'        => $settings,
                'hasApiKey'       => $hasApiKey,
                'apiKeyPreview'   => $apiKeyPreview,
                'types'           => Suppression::ALL_TYPES,
                'segments'        => $segments,
                'intervalChoices' => [
                    5    => '5 minutes',
                    15   => '15 minutes',
                    30   => '30 minutes',
                    60   => '1 hour',
                    360  => '6 hours',
                    720  => '12 hours',
                    1440 => '24 hours',
                ],
                'rangeChoices' => [
                    7  => '7 days',
                    30 => '30 days',
                    90 => '90 days',
                    0  => 'All time',
                ],
            ],
            'contentTemplate' => '@MauticSyncData/Settings/index.html.twig',
            'passthroughVars' => [
                'activeLink'    => '#mautic_syncdata_settings',
                'mauticContent' => 'syncdataSettings',
                'route'         => $this->generateUrl('mautic_syncdata_settings'),
            ],
        ]);
    }

    public function saveAction(
        Request $request,
        IntegrationsHelper $integrationsHelper,
        EncryptionService $encryptionService,
        EntityManagerInterface $entityManager,
    ): Response {
        if (!$this->security->isGranted('plugin:syncdata:settings:edit')) {
            return $this->accessDenied();
        }

        try {
            $integration       = $integrationsHelper->getIntegration(SyncDataIntegration::NAME);
            $integrationConfig = $integration->getIntegrationConfiguration();

            $apiKey = $request->request->get('api_key');
            if (null !== $apiKey && '' !== $apiKey) {
                // Encrypt before storing — IntegrationsHelper auto-decrypts on read
                $encryptedKeys = $encryptionService->encrypt(['api_key' => $apiKey]);
                $integrationConfig->setApiKeys($encryptedKeys);
            }

            $featureSettings = [
                'enabled_types'       => $request->request->all('enabled_types') ?: Suppression::ALL_TYPES,
                'sync_interval'       => (int) $request->request->get('sync_interval', 15),
                'initial_sync_range'  => (int) $request->request->get('initial_sync_range', 30),
                'default_action_mode' => $request->request->get('default_action_mode', 'dnc'),
                'action_modes'        => $request->request->all('action_modes') ?: [],
                'target_segments'     => $request->request->all('target_segments') ?: [],
                'notification_email'  => $request->request->get('notification_email', ''),
                'spike_threshold'     => (int) $request->request->get('spike_threshold', 50),
                'max_per_sync'        => (int) $request->request->get('max_per_sync', 0),
            ];

            $integrationConfig->setFeatureSettings($featureSettings);
            $integrationConfig->setIsPublished(true);

            $entityManager->persist($integrationConfig);
            $entityManager->flush();

            $this->addFlashMessage('mautic.syncdata.settings.saved');
        } catch (\Throwable $e) {
            $this->addFlashMessage('mautic.syncdata.settings.save_failed', ['%error%' => $e->getMessage()]);
        }

        return $this->redirectToRoute('mautic_syncdata_settings');
    }

    public function testConnectionAction(
        Request $request,
        IntegrationsHelper $integrationsHelper,
        SyncDataApiClient $apiClient,
    ): JsonResponse {
        if (!$this->security->isGranted('plugin:syncdata:settings:edit')) {
            return new JsonResponse(['success' => false, 'error' => 'Access denied'], 403);
        }

        $apiKey = $request->request->get('api_key');

        if (empty($apiKey)) {
            $apiKeys = $this->getApiKeys($integrationsHelper);
            $apiKey  = $apiKeys['api_key'] ?? '';
        }

        if (!is_string($apiKey) || '' === $apiKey) {
            return new JsonResponse(['success' => false, 'error' => 'No API key provided. Re-save the form first.']);
        }

        $apiClient->setApiKey($apiKey);
        $result = $apiClient->testConnection();

        return new JsonResponse($result);
    }

    private function maskApiKey(string $key): string
    {
        $visible = 10;
        if (strlen($key) <= $visible) {
            return $key;
        }

        return substr($key, 0, $visible).str_repeat('•', max(0, strlen($key) - $visible));
    }

    private function getSettings(IntegrationsHelper $integrationsHelper): array
    {
        try {
            $integration       = $integrationsHelper->getIntegration(SyncDataIntegration::NAME);
            $integrationConfig = $integration->getIntegrationConfiguration();

            return $integrationConfig->getFeatureSettings() ?? [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function getApiKeys(IntegrationsHelper $integrationsHelper): array
    {
        try {
            $integration       = $integrationsHelper->getIntegration(SyncDataIntegration::NAME);
            $integrationConfig = $integration->getIntegrationConfiguration();

            return $integrationConfig->getApiKeys() ?? [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function getSegmentChoices(ListModel $listModel): array
    {
        $lists   = $listModel->getUserLists();
        $choices = [];

        foreach ($lists as $list) {
            $choices[$list['id']] = $list['name'];
        }

        return $choices;
    }
}
