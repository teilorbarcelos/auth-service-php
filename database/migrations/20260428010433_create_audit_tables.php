<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateAuditTables extends AbstractMigration
{
    public function up(): void
    {
        $this->execute('CREATE SCHEMA IF NOT EXISTS audit');

        $tableAudit = $this->table('audit.tb_audit', ['id' => false, 'primary_key' => 'id']);
        $tableAudit->addColumn('id', 'uuid')
                   ->addColumn('id_user', 'string', ['limit' => 255, 'null' => true])
                   ->addColumn('user_name', 'string', ['null' => true])
                   ->addColumn('action_type', 'string', ['limit' => 255, 'null' => true])
                   ->addColumn('execute_type', 'string', ['limit' => 255, 'null' => true])
                   ->addColumn('class', 'string', ['limit' => 255, 'null' => true])
                   ->addColumn('function', 'string', ['limit' => 255, 'null' => true])
                   ->addColumn('params', 'text', ['null' => true])
                   ->addColumn('raw', 'text', ['null' => true])
                   ->addColumn('table_name', 'string', ['limit' => 255, 'null' => true])
                   ->addColumn('diff_value', 'text', ['null' => true])
                   ->addColumn('error', 'text', ['null' => true])
                   ->addColumn('host', 'text', ['null' => true])
                   ->addColumn('ip', 'text', ['null' => true])
                   ->addColumn('base_url', 'text', ['null' => true])
                   ->addColumn('method', 'text', ['null' => true])
                   ->addColumn('hostname', 'text', ['null' => true])
                   ->addColumn('original_url', 'text', ['null' => true])
                   ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'precision' => 6])
                   ->create();

        $tableError = $this->table('audit.tb_error_log', ['id' => false, 'primary_key' => 'id']);
        $tableError->addColumn('id', 'uuid')
                   ->addColumn('id_user', 'string', ['null' => true])
                   ->addColumn('source', 'string', ['null' => true])
                   ->addColumn('error_message', 'text', ['null' => true])
                   ->addColumn('error_data', 'text', ['null' => true])
                   ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'precision' => 6])
                   ->create();

        $this->execute('ALTER TABLE audit.tb_audit ALTER COLUMN created_at TYPE timestamp(6)');
        $this->execute('ALTER TABLE audit.tb_error_log ALTER COLUMN created_at TYPE timestamp(6)');
    }

    public function down(): void
    {
        $this->table('audit.tb_audit')->drop()->save();
        $this->table('audit.tb_error_log')->drop()->save();
        $this->execute('DROP SCHEMA IF EXISTS audit');
    }
}
