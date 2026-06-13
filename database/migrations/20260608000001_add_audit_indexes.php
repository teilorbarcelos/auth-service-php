<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddAuditIndexes extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('audit.tb_audit');
        if (!$table->hasIndex(['table_name'])) {
            $this->execute('CREATE INDEX idx_tb_audit_table_name ON audit.tb_audit (table_name)');
        }
        if (!$table->hasIndex(['id_user'])) {
            $this->execute('CREATE INDEX idx_tb_audit_id_user ON audit.tb_audit (id_user)');
        }
        if (!$table->hasIndex(['created_at'])) {
            $this->execute('CREATE INDEX idx_tb_audit_created_at ON audit.tb_audit (created_at DESC)');
        }
    }

    public function down(): void
    {
        $this->execute('DROP INDEX IF EXISTS audit.idx_tb_audit_table_name');
        $this->execute('DROP INDEX IF EXISTS audit.idx_tb_audit_id_user');
        $this->execute('DROP INDEX IF EXISTS audit.idx_tb_audit_created_at');
    }
}
