<?php

declare(strict_types=1);

namespace MauticPlugin\MauticSyncDataBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260330002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create plugin_syncdata_suppressions table';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('plugin_syncdata_suppressions')) {
            return;
        }

        $table = $schema->createTable('plugin_syncdata_suppressions');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('email', 'string', ['length' => 255]);
        $table->addColumn('suppression_type', 'string', ['length' => 30]);
        $table->addColumn('sendgrid_reason', 'text', ['notnull' => false]);
        $table->addColumn('sendgrid_status', 'string', ['length' => 50, 'notnull' => false]);
        $table->addColumn('sendgrid_created_at', 'datetime');
        $table->addColumn('sendgrid_group_id', 'integer', ['notnull' => false]);
        $table->addColumn('sendgrid_group_name', 'string', ['length' => 100, 'notnull' => false]);
        $table->addColumn('mautic_contact_id', 'integer', ['notnull' => false]);
        $table->addColumn('action_taken', 'string', ['length' => 20, 'default' => 'unmatched']);
        $table->addColumn('synced_at', 'datetime');
        $table->addColumn('created_at', 'datetime');

        $table->setPrimaryKey(['id']);
        $table->addIndex(['email'], 'idx_sd_email');
        $table->addIndex(['suppression_type'], 'idx_sd_type');
        $table->addIndex(['synced_at'], 'idx_sd_synced_at');
        $table->addIndex(['mautic_contact_id'], 'idx_sd_contact');
        $table->addUniqueIndex(['email', 'suppression_type', 'sendgrid_created_at'], 'uniq_sd_email_type_date');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('plugin_syncdata_suppressions');
    }
}
