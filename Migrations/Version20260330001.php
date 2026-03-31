<?php

declare(strict_types=1);

namespace MauticPlugin\MauticSendGridSyncBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260330001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create plugin_sendgrid_sync_log table';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('plugin_sendgrid_sync_log')) {
            return;
        }

        $table = $schema->createTable('plugin_sendgrid_sync_log');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('sync_type', 'string', ['length' => 20]);
        $table->addColumn('started_at', 'datetime');
        $table->addColumn('completed_at', 'datetime', ['notnull' => false]);
        $table->addColumn('status', 'string', ['length' => 20, 'default' => 'running']);
        $table->addColumn('records_fetched', 'integer', ['default' => 0]);
        $table->addColumn('records_added', 'integer', ['default' => 0]);
        $table->addColumn('records_skipped', 'integer', ['default' => 0]);
        $table->addColumn('records_unmatched', 'integer', ['default' => 0]);
        $table->addColumn('error_message', 'text', ['notnull' => false]);
        $table->addColumn('suppression_breakdown', 'json', ['notnull' => false]);
        $table->addColumn('created_at', 'datetime');

        $table->setPrimaryKey(['id']);
        $table->addIndex(['status'], 'idx_sgsl_status');
        $table->addIndex(['started_at'], 'idx_sgsl_started_at');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('plugin_sendgrid_sync_log');
    }
}
