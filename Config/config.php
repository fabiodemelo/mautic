<?php

declare(strict_types=1);

return [
    'name'        => 'SyncData',
    'description' => 'Sync SendGrid suppressions (bounces, spam reports, blocks, invalid emails, global & group unsubscribes) to Mautic\'s Do Not Contact list or designated segments. Includes dashboard, charts, CSV export, scheduled and on-demand sync, encrypted API key storage, contact re-linking, spike alerts, and a Max Records Per Sync cap. Support: support@demelos.com',
    'version'     => '2.3.3',
    'author'      => 'Fabio de Melo',

    'routes' => [
        'main' => [
            // Dashboard
            'mautic_syncdata_dashboard' => [
                'path'       => '/plugins/syncdata/dashboard',
                'controller' => 'MauticPlugin\MauticSyncDataBundle\Controller\DashboardController::indexAction',
            ],
            'mautic_syncdata_dashboard_stats' => [
                'path'       => '/plugins/syncdata/dashboard/stats',
                'controller' => 'MauticPlugin\MauticSyncDataBundle\Controller\DashboardController::statsAction',
            ],
            'mautic_syncdata_dashboard_chart' => [
                'path'       => '/plugins/syncdata/dashboard/chart/{type}',
                'controller' => 'MauticPlugin\MauticSyncDataBundle\Controller\DashboardController::chartDataAction',
            ],
            'mautic_syncdata_dashboard_suppressions' => [
                'path'       => '/plugins/syncdata/dashboard/suppressions',
                'controller' => 'MauticPlugin\MauticSyncDataBundle\Controller\DashboardController::suppressionsAction',
            ],
            'mautic_syncdata_dashboard_history' => [
                'path'       => '/plugins/syncdata/dashboard/history',
                'controller' => 'MauticPlugin\MauticSyncDataBundle\Controller\DashboardController::historyAction',
            ],
            'mautic_syncdata_dashboard_export' => [
                'path'       => '/plugins/syncdata/dashboard/export',
                'controller' => 'MauticPlugin\MauticSyncDataBundle\Controller\DashboardController::exportAction',
            ],

            // Sync
            'mautic_syncdata_sync_run' => [
                'path'       => '/plugins/syncdata/sync/run',
                'controller' => 'MauticPlugin\MauticSyncDataBundle\Controller\SyncController::runAction',
                'method'     => 'POST',
            ],
            'mautic_syncdata_sync_status' => [
                'path'       => '/plugins/syncdata/sync/status/{logId}',
                'controller' => 'MauticPlugin\MauticSyncDataBundle\Controller\SyncController::statusAction',
            ],

            // Settings
            'mautic_syncdata_settings' => [
                'path'       => '/plugins/syncdata/settings',
                'controller' => 'MauticPlugin\MauticSyncDataBundle\Controller\SettingsController::indexAction',
            ],
            'mautic_syncdata_settings_save' => [
                'path'       => '/plugins/syncdata/settings/save',
                'controller' => 'MauticPlugin\MauticSyncDataBundle\Controller\SettingsController::saveAction',
                'method'     => 'POST',
            ],
            'mautic_syncdata_settings_test' => [
                'path'       => '/plugins/syncdata/settings/test',
                'controller' => 'MauticPlugin\MauticSyncDataBundle\Controller\SettingsController::testConnectionAction',
                'method'     => 'POST',
            ],
        ],
    ],

    'menu' => [
        'main' => [
            'mautic.syncdata.menu.root' => [
                'id'        => 'mautic_syncdata_root',
                'iconClass' => 'ri-mail-check-line',
                'priority'  => 60,
                'children'  => [
                    'mautic.syncdata.menu.dashboard' => [
                        'route' => 'mautic_syncdata_dashboard',
                    ],
                    'mautic.syncdata.menu.settings' => [
                        'route'  => 'mautic_syncdata_settings',
                        'access' => 'plugin:syncdata:settings:edit',
                    ],
                ],
            ],
        ],
    ],

    'services' => [
        'integrations' => [
            'mautic.integration.syncdata' => [
                'class' => \MauticPlugin\MauticSyncDataBundle\Integration\SyncDataIntegration::class,
                'tags'  => [
                    'mautic.integration',
                    'mautic.basic_integration',
                    'mautic.config_integration',
                ],
            ],
        ],
        'others' => [
            'mautic.syncdata.service.api_client' => [
                'class'     => \MauticPlugin\MauticSyncDataBundle\Service\SyncDataApiClient::class,
                'arguments' => [
                    'mautic.http.client',
                    'monolog.logger.mautic',
                ],
            ],
            'mautic.syncdata.service.suppression_fetcher' => [
                'class'     => \MauticPlugin\MauticSyncDataBundle\Service\SuppressionFetcher::class,
                'arguments' => [
                    'mautic.syncdata.service.api_client',
                    'monolog.logger.mautic',
                ],
            ],
            'mautic.syncdata.service.dnc_mapper' => [
                'class' => \MauticPlugin\MauticSyncDataBundle\Service\DncMapper::class,
            ],
            'mautic.syncdata.service.contact_resolver' => [
                'class'     => \MauticPlugin\MauticSyncDataBundle\Service\ContactResolver::class,
                'arguments' => [
                    'mautic.lead.model.lead',
                    'doctrine.orm.entity_manager',
                ],
            ],
            'mautic.syncdata.service.sync_engine' => [
                'class'     => \MauticPlugin\MauticSyncDataBundle\Service\SyncEngine::class,
                'arguments' => [
                    'mautic.syncdata.service.suppression_fetcher',
                    'mautic.syncdata.service.contact_resolver',
                    'mautic.syncdata.service.dnc_mapper',
                    'mautic.lead.model.dnc',
                    'mautic.lead.model.list',
                    'doctrine.orm.entity_manager',
                    'mautic.syncdata.service.notification_service',
                    'monolog.logger.mautic',
                ],
            ],
            'mautic.syncdata.service.stats_calculator' => [
                'class'     => \MauticPlugin\MauticSyncDataBundle\Service\StatsCalculator::class,
                'arguments' => [
                    'doctrine.orm.entity_manager',
                ],
            ],
            'mautic.syncdata.service.notification_service' => [
                'class'     => \MauticPlugin\MauticSyncDataBundle\Service\NotificationService::class,
                'arguments' => [
                    'mautic.helper.mailer',
                    'monolog.logger.mautic',
                ],
            ],
        ],
        'command' => [
            'mautic.syncdata.command.sync' => [
                'class'     => \MauticPlugin\MauticSyncDataBundle\Command\SyncCommand::class,
                'arguments' => [
                    'mautic.syncdata.service.sync_engine',
                    'mautic.integrations.helper',
                    'mautic.syncdata.service.api_client',
                ],
                'tags' => ['console.command'],
            ],
        ],
    ],
];
