<?php

declare(strict_types=1);

return [
    'name'        => 'SendGrid Suppression Sync',
    'description' => 'Sync SendGrid suppressions to Mautic Do Not Contact list or segments.',
    'version'     => '1.0.0',
    'author'      => 'Fabio de Melo',

    'routes' => [
        'main' => [
            // Dashboard
            'mautic_sendgridsync_dashboard' => [
                'path'       => '/plugins/sendgridsync/dashboard',
                'controller' => 'MauticPlugin\MauticSendGridSyncBundle\Controller\DashboardController::indexAction',
            ],
            'mautic_sendgridsync_dashboard_stats' => [
                'path'       => '/plugins/sendgridsync/dashboard/stats',
                'controller' => 'MauticPlugin\MauticSendGridSyncBundle\Controller\DashboardController::statsAction',
            ],
            'mautic_sendgridsync_dashboard_chart' => [
                'path'       => '/plugins/sendgridsync/dashboard/chart/{type}',
                'controller' => 'MauticPlugin\MauticSendGridSyncBundle\Controller\DashboardController::chartDataAction',
            ],
            'mautic_sendgridsync_dashboard_suppressions' => [
                'path'       => '/plugins/sendgridsync/dashboard/suppressions',
                'controller' => 'MauticPlugin\MauticSendGridSyncBundle\Controller\DashboardController::suppressionsAction',
            ],
            'mautic_sendgridsync_dashboard_history' => [
                'path'       => '/plugins/sendgridsync/dashboard/history',
                'controller' => 'MauticPlugin\MauticSendGridSyncBundle\Controller\DashboardController::historyAction',
            ],
            'mautic_sendgridsync_dashboard_export' => [
                'path'       => '/plugins/sendgridsync/dashboard/export',
                'controller' => 'MauticPlugin\MauticSendGridSyncBundle\Controller\DashboardController::exportAction',
            ],

            // Sync
            'mautic_sendgridsync_sync_run' => [
                'path'       => '/plugins/sendgridsync/sync/run',
                'controller' => 'MauticPlugin\MauticSendGridSyncBundle\Controller\SyncController::runAction',
                'method'     => 'POST',
            ],
            'mautic_sendgridsync_sync_status' => [
                'path'       => '/plugins/sendgridsync/sync/status/{logId}',
                'controller' => 'MauticPlugin\MauticSendGridSyncBundle\Controller\SyncController::statusAction',
            ],

            // Settings
            'mautic_sendgridsync_settings' => [
                'path'       => '/plugins/sendgridsync/settings',
                'controller' => 'MauticPlugin\MauticSendGridSyncBundle\Controller\SettingsController::indexAction',
            ],
            'mautic_sendgridsync_settings_save' => [
                'path'       => '/plugins/sendgridsync/settings/save',
                'controller' => 'MauticPlugin\MauticSendGridSyncBundle\Controller\SettingsController::saveAction',
                'method'     => 'POST',
            ],
            'mautic_sendgridsync_settings_test' => [
                'path'       => '/plugins/sendgridsync/settings/test',
                'controller' => 'MauticPlugin\MauticSendGridSyncBundle\Controller\SettingsController::testConnectionAction',
                'method'     => 'POST',
            ],
        ],
    ],

    'menu' => [
        'main' => [
            'mautic.sendgridsync.menu.root' => [
                'id'        => 'mautic_sendgridsync_root',
                'iconClass' => 'ri-mail-check-line',
                'priority'  => 60,
                'children'  => [
                    'mautic.sendgridsync.menu.dashboard' => [
                        'route' => 'mautic_sendgridsync_dashboard',
                    ],
                    'mautic.sendgridsync.menu.settings' => [
                        'route'  => 'mautic_sendgridsync_settings',
                        'access' => 'plugin:sendgridsync:settings:manage',
                    ],
                ],
            ],
        ],
    ],

    'services' => [
        'integrations' => [
            'mautic.integration.sendgridsync' => [
                'class' => \MauticPlugin\MauticSendGridSyncBundle\Integration\SendGridSyncIntegration::class,
                'tags'  => [
                    'mautic.integration',
                    'mautic.basic_integration',
                    'mautic.config_integration',
                ],
            ],
        ],
        'others' => [
            'mautic.sendgridsync.service.api_client' => [
                'class'     => \MauticPlugin\MauticSendGridSyncBundle\Service\SendGridApiClient::class,
                'arguments' => [
                    'mautic.http.client',
                    'monolog.logger.mautic',
                ],
            ],
            'mautic.sendgridsync.service.suppression_fetcher' => [
                'class'     => \MauticPlugin\MauticSendGridSyncBundle\Service\SuppressionFetcher::class,
                'arguments' => [
                    'mautic.sendgridsync.service.api_client',
                    'monolog.logger.mautic',
                ],
            ],
            'mautic.sendgridsync.service.dnc_mapper' => [
                'class' => \MauticPlugin\MauticSendGridSyncBundle\Service\DncMapper::class,
            ],
            'mautic.sendgridsync.service.contact_resolver' => [
                'class'     => \MauticPlugin\MauticSendGridSyncBundle\Service\ContactResolver::class,
                'arguments' => [
                    'mautic.lead.model.lead',
                    'doctrine.orm.entity_manager',
                ],
            ],
            'mautic.sendgridsync.service.sync_engine' => [
                'class'     => \MauticPlugin\MauticSendGridSyncBundle\Service\SyncEngine::class,
                'arguments' => [
                    'mautic.sendgridsync.service.suppression_fetcher',
                    'mautic.sendgridsync.service.contact_resolver',
                    'mautic.sendgridsync.service.dnc_mapper',
                    'mautic.lead.model.dnc',
                    'mautic.lead.model.list',
                    'doctrine.orm.entity_manager',
                    'mautic.sendgridsync.service.notification_service',
                    'monolog.logger.mautic',
                ],
            ],
            'mautic.sendgridsync.service.stats_calculator' => [
                'class'     => \MauticPlugin\MauticSendGridSyncBundle\Service\StatsCalculator::class,
                'arguments' => [
                    'doctrine.orm.entity_manager',
                ],
            ],
            'mautic.sendgridsync.service.notification_service' => [
                'class'     => \MauticPlugin\MauticSendGridSyncBundle\Service\NotificationService::class,
                'arguments' => [
                    'mautic.helper.mailer',
                    'monolog.logger.mautic',
                ],
            ],
        ],
        'command' => [
            'mautic.sendgridsync.command.sync' => [
                'class'     => \MauticPlugin\MauticSendGridSyncBundle\Command\SyncCommand::class,
                'arguments' => [
                    'mautic.sendgridsync.service.sync_engine',
                    'mautic.integrations.helper',
                ],
                'tags' => ['console.command'],
            ],
        ],
    ],
];
