<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateRoleFeatureTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('role_features', ['id' => false, 'primary_key' => ['id_feature', 'id_role']]);
        $table->addColumn('id_role', 'string', ['limit' => 40])
            ->addColumn('id_feature', 'string', ['limit' => 40])
            ->addColumn('permissions', 'jsonb', ['null' => true])
            ->addForeignKey('id_role', 'roles', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('id_feature', 'features', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();
    }
}
