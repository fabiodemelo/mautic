<?php

declare(strict_types=1);

namespace MauticPlugin\MauticSyncDataBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaException;
use Mautic\IntegrationsBundle\Migration\AbstractMigration;

/**
 * Creates plugin_syncdata_suppressions table on first install.
 *
 * Compatible with Mautic 7+ (uses Mautic\IntegrationsBundle\Migration\AbstractMigration).
 */
class Version20260330002 extends AbstractMigration
{
    private string $table = 'plugin_syncdata_suppressions';

    protected function isApplicable(Schema $schema): bool
    {
        try {
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
                id                  INT AUTO_INCREMENT NOT NULL,
                email               VARCHAR(255) NOT NULL,
                suppression_type    VARCHAR(30) NOT NULL,
                source_reason       LONGTEXT DEFAULT NULL,
                source_status       VARCHAR(50) DEFAULT NULL,
                source_created_at   DATETIME NOT NULL,
                source_group_id     INT DEFAULT NULL,
                source_group_name   VARCHAR(100) DEFAULT NULL,
                mautic_contact_id   INT DEFAULT NULL,
                action_taken        VARCHAR(20) NOT NULL DEFAULT 'unmatched',
                synced_at           DATETIME NOT NULL,
                created_at          DATETIME NOT NULL,
                PRIMARY KEY (id),
                INDEX idx_sd_email      (email),
                INDEX idx_sd_type       (suppression_type),
                INDEX idx_sd_synced_at  (synced_at),
                INDEX idx_sd_contact    (mautic_contact_id),
                UNIQUE INDEX uniq_sd_email_type_date (email, suppression_type, source_created_at)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
        ");
    }
}
