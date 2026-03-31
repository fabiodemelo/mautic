<?php

declare(strict_types=1);

namespace MauticPlugin\MauticSendGridSyncBundle\Command;

use Mautic\IntegrationsBundle\Helper\IntegrationsHelper;
use MauticPlugin\MauticSendGridSyncBundle\Entity\SyncLog;
use MauticPlugin\MauticSendGridSyncBundle\Integration\SendGridSyncIntegration;
use MauticPlugin\MauticSendGridSyncBundle\Service\SendGridApiClient;
use MauticPlugin\MauticSendGridSyncBundle\Service\SyncEngine;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class SyncCommand extends Command
{
    protected static $defaultName = 'mautic:sendgrid:sync';

    public function __construct(
        private readonly SyncEngine $syncEngine,
        private readonly IntegrationsHelper $integrationsHelper,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Sync SendGrid suppressions to Mautic DNC or segments')
            ->addOption('type', null, InputOption::VALUE_OPTIONAL, 'Sync type: incremental or full', 'incremental')
            ->addOption('suppression', null, InputOption::VALUE_OPTIONAL, 'Specific suppression type to sync')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be synced without making changes');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $integration = $this->integrationsHelper->getIntegration(SendGridSyncIntegration::NAME);
        } catch (\Throwable $e) {
            $io->error('SendGrid Sync integration is not configured or not enabled.');

            return Command::FAILURE;
        }

        $integrationConfig  = $integration->getIntegrationConfiguration();
        $apiKeys            = $integrationConfig->getApiKeys();
        $featureSettings    = $integrationConfig->getFeatureSettings();

        $apiKey = $apiKeys['api_key'] ?? '';
        if ('' === $apiKey) {
            $io->error('SendGrid API key is not configured. Go to Settings > Plugins > SendGrid Sync.');

            return Command::FAILURE;
        }

        // Inject API key into the client via the sync engine's fetcher
        $apiClient = $this->getApiClient();
        if (null !== $apiClient) {
            $apiClient->setApiKey($apiKey);
        }

        $syncType       = $input->getOption('type');
        $specificType   = $input->getOption('suppression');
        $dryRun         = $input->getOption('dry-run');

        $syncTypeConst = match ($syncType) {
            'full'   => SyncLog::TYPE_FULL,
            default  => SyncLog::TYPE_INCREMENTAL,
        };

        $io->title('SendGrid Suppression Sync');
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

        if ($syncLog->getSuppressionBreakdown()) {
            $io->table(
                ['Type', 'Count'],
                array_map(
                    fn ($type, $count) => [$type, $count],
                    array_keys($syncLog->getSuppressionBreakdown()),
                    array_values($syncLog->getSuppressionBreakdown()),
                ),
            );
        }

        return Command::SUCCESS;
    }

    private function getApiClient(): ?SendGridApiClient
    {
        // Access the API client through reflection to set the key
        // This is resolved via the service container in the actual runtime
        try {
            $reflection = new \ReflectionClass($this->syncEngine);
            $prop = $reflection->getProperty('suppressionFetcher');
            $prop->setAccessible(true);
            $fetcher = $prop->getValue($this->syncEngine);

            $fetcherRef = new \ReflectionClass($fetcher);
            $clientProp = $fetcherRef->getProperty('apiClient');
            $clientProp->setAccessible(true);

            return $clientProp->getValue($fetcher);
        } catch (\Throwable) {
            return null;
        }
    }
}
