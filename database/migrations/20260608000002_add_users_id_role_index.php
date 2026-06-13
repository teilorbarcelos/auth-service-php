<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddUsersIdRoleIndex extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('users');
        if (!$table->hasIndex(['id_role'])) {
            $this->execute('CREATE INDEX IF NOT EXISTS idx_users_id_role ON users (id_role)');
        }
    }

    public function down(): void
    {
        $this->execute('DROP INDEX IF EXISTS idx_users_id_role');
    }
}
