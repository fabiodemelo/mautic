<?php

declare(strict_types=1);

namespace MauticPlugin\MauticSyncDataBundle\Command;

use Mautic\IntegrationsBundle\Helper\IntegrationsHelper;
use MauticPlugin\MauticSyncDataBundle\Entity\SyncLog;
use MauticPlugin\MauticSyncDataBundle\Integration\SyncDataIntegration;
use MauticPlugin\MauticSyncDataBundle\Service\SyncDataApiClient;
use MauticPlugin\MauticSyncDataBundle\Service\SyncEngine;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class SyncCommand extends Command
{
    protected static $defaultName = 'mautic:syncdata:sync';

    public function __construct(
        private readonly SyncEngine $syncEngine,
        private readonly IntegrationsHelper $integrationsHelper,
        private readonly SyncDataApiClient $apiClient,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('mautic:syncdata:sync')
            ->setDescription('Sync suppressions to Mautic DNC or segments')
            ->addOption('type', null, InputOption::VALUE_OPTIONAL, 'Sync type: incremental or full', 'incremental')
            ->addOption('suppression', null, InputOption::VALUE_OPTIONAL, 'Specific suppression type to sync')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be synced without making changes');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $integration = $this->integrationsHelper->getIntegration(SyncDataIntegration::NAME);
        } catch (\Throwable) {
            $io->error('SyncData integration is not configured or not enabled.');

            return Command::FAILURE;
        }

        $integrationConfig = $integration->getIntegrationConfiguration();
        $apiKeys           = $integrationConfig->getApiKeys();
        $featureSettings   = $integrationConfig->getFeatureSettings() ?? [];

        $apiKey = $apiKeys['api_key'] ?? null;
        if (!is_string($apiKey) || '' === $apiKey) {
            $io->error('SyncData API key is not configured or could not be decrypted. Re-save it on the Settings page.');

            return Command::FAILURE;
        }

        $this->apiClient->setApiKey($apiKey);

        $syncType     = $input->getOption('type');
        $specificType = $input->getOption('suppression');
        $dryRun       = (bool) $input->getOption('dry-run');

        $syncTypeConst = match ($syncType) {
            'full'  => SyncLog::TYPE_FULL,
            default => SyncLog::TYPE_INCREMENTAL,
        };

        $io->title('SyncData');
        $io->text([
            "Sync type: {$syncType}",
            'Dry run: '.($dryRun ? 'Yes' : 'No'),
            'Suppression filter: '.($specificType ?? 'All enabled'),
        ]);

        $syncLog = $this->syncEngine->sync($syncTypeConst, $featureSettings, $specificType, $dryRun);

        if (SyncLog::STATUS_FAILED === $syncLog->getStatus()) {
            $io->error('Sync failed: '.$syncLog->getErrorMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf(
            'Sync completed: %d fetched, %d added, %d skipped, %d unmatched (%.1fs)',
            $syncLog->getRecordsFetched(),
            $syncLog->getRecordsAdded(),
            $syncLog->getRecordsSkipped(),
            $syncLog->getRecordsUnmatched(),
            $syncLog->getDurationSeconds() ?? 0,
        ));

        $breakdown = $syncLog->getSuppressionBreakdown();
        if (!empty($breakdown)) {
            $io->table(
                ['Type', 'Count'],
                array_map(
                    fn ($type, $count) => [$type, $count],
                    array_keys($breakdown),
                    array_values($breakdown),
                ),
            );
        }

        return Command::SUCCESS;
    }
}
