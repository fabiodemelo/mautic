<?php

declare(strict_types=1);

namespace MauticPlugin\MauticSyncDataBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaException;
use Mautic\IntegrationsBundle\Migration\AbstractMigration;

/**
 * Creates plugin_syncdata_log table on first install.
 *
 * Compatible with Mautic 7+ (uses Mautic\IntegrationsBundle\Migration\AbstractMigration).
 */
class Version20260330001 extends AbstractMigration
{
    private string $table = 'plugin_syncdata_log';

    protected function isApplicable(Schema $schema): bool
    {
        try {
            // If the table already exists, skip the migration
            $schema->getTable($this->concatPrefix($this->table));

            return false;
        } catch (SchemaException) {
            return true;
        }
    }

    protected function up(): void
    {
        $table = $this->concatPrefix($this->table);

        $this->addSql("
            CREATE TABLE IF NOT EXISTS `{$table}` (
                id                     INT AUTO_INCREMENT NOT NULL,
                sync_type              VARCHAR(20) NOT NULL,
                started_at             DATETIME NOT NULL,
                completed_at           DATETIME DEFAULT NULL,
                status                 VARCHAR(20) NOT NULL DEFAULT 'running',
                records_fetched        INT NOT NULL DEFAULT 0,
                records_added          INT NOT NULL DEFAULT 0,
                records_skipped        INT NOT NULL DEFAULT 0,
                records_unmatched      INT NOT NULL DEFAULT 0,
                error_message          LONGTEXT DEFAULT NULL,
                suppression_breakdown  LONGTEXT DEFAULT NULL,
                created_at             DATETIME NOT NULL,
                PRIMARY KEY (id),
                INDEX idx_sd_status     (status),
                INDEX idx_sd_started_at (started_at)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
        ");
    }
}
