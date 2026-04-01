<?php

declare(strict_types=1);

namespace MauticPlugin\MauticSyncDataBundle\Controller;

use Mautic\CoreBundle\Controller\AbstractFormController;
use Mautic\IntegrationsBundle\Helper\IntegrationsHelper;
use MauticPlugin\MauticSyncDataBundle\Entity\Suppression;
use MauticPlugin\MauticSyncDataBundle\Integration\SyncDataIntegration;
use MauticPlugin\MauticSyncDataBundle\Service\SyncDataApiClient;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class SettingsController extends AbstractFormController
{
    public function __construct(
        private readonly IntegrationsHelper $integrationsHelper,
        private readonly SyncDataApiClient $apiClient,
    ) {
    }

    public function indexAction(): Response
    {
        if (!$this->security->isGranted('plugin:syncdata:settings:manage')) {
            return $this->accessDenied();
        }

        $settings   = $this->getSettings();
        $apiKeys    = $this->getApiKeys();
        $hasApiKey  = !empty($apiKeys['api_key']);
        $segments   = $this->getSegmentChoices();

        return $this->delegateView([
            'viewParameters' => [
                'settings'       => $settings,
                'hasApiKey'      => $hasApiKey,
                'types'          => Suppression::ALL_TYPES,
                'segments'       => $segments,
                'intervalChoices' => [
                    5 => '5 minutes', 15 => '15 minutes', 30 => '30 minutes',
                    60 => '1 hour', 360 => '6 hours', 720 => '12 hours', 1440 => '24 hours',
                ],
                'rangeChoices'   => [
                    7 => '7 days', 30 => '30 days', 90 => '90 days', 0 => 'All time',
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

    public function saveAction(Request $request): Response
    {
        if (!$this->security->isGranted('plugin:syncdata:settings:manage')) {
            return $this->accessDenied();
        }

        try {
            $integration       = $this->integrationsHelper->getIntegration(SyncDataIntegration::NAME);
            $integrationConfig = $integration->getIntegrationConfiguration();

            // Save API key
            $apiKey = $request->request->get('api_key');
            if (null !== $apiKey && '' !== $apiKey) {
                $integrationConfig->setApiKeys(['api_key' => $apiKey]);
            }

            // Save feature settings
            $featureSettings = [
                'enabled_types'         => $request->request->all('enabled_types') ?: Suppression::ALL_TYPES,
                'sync_interval'         => (int) $request->request->get('sync_interval', 15),
                'initial_sync_range'    => (int) $request->request->get('initial_sync_range', 30),
                'default_action_mode'   => $request->request->get('default_action_mode', 'dnc'),
                'action_modes'          => $request->request->all('action_modes') ?: [],
                'target_segments'       => $request->request->all('target_segments') ?: [],
                'notification_email'    => $request->request->get('notification_email', ''),
                'spike_threshold'       => (int) $request->request->get('spike_threshold', 50),
            ];

            $integrationConfig->setFeatureSettings($featureSettings);
            $integrationConfig->setIsPublished(true);

            $this->entityManager->persist($integrationConfig);
            $this->entityManager->flush();

            $this->addFlashMessage('mautic.syncdata.settings.saved');
        } catch (\Throwable $e) {
            $this->addFlashMessage('mautic.syncdata.settings.save_failed', ['%error%' => $e->getMessage()]);
        }

        return $this->redirectToRoute('mautic_syncdata_settings');
    }

    public function testConnectionAction(Request $request): JsonResponse
    {
        if (!$this->security->isGranted('plugin:syncdata:settings:manage')) {
            return new JsonResponse(['success' => false, 'error' => 'Access denied'], 403);
        }

        $apiKey = $request->request->get('api_key');

        if (empty($apiKey)) {
            // Try existing stored key
            $apiKeys = $this->getApiKeys();
            $apiKey  = $apiKeys['api_key'] ?? '';
        }

        if ('' === $apiKey) {
            return new JsonResponse(['success' => false, 'error' => 'No API key provided']);
        }

        $this->apiClient->setApiKey($apiKey);
        $result = $this->apiClient->testConnection();

        return new JsonResponse($result);
    }

    private function getSettings(): array
    {
        try {
            $integration       = $this->integrationsHelper->getIntegration(SyncDataIntegration::NAME);
            $integrationConfig = $integration->getIntegrationConfiguration();

            return $integrationConfig->getFeatureSettings() ?? [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function getApiKeys(): array
    {
        try {
            $integration       = $this->integrationsHelper->getIntegration(SyncDataIntegration::NAME);
            $integrationConfig = $integration->getIntegrationConfiguration();

            return $integrationConfig->getApiKeys() ?? [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function getSegmentChoices(): array
    {
        $listModel = $this->container->get('mautic.lead.model.list');
        $lists     = $listModel->getLists();
        $choices   = [];

        foreach ($lists as $list) {
            $choices[$list['id']] = $list['name'];
        }

        return $choices;
    }
}
