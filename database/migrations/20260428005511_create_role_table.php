<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateRoleTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('roles', ['id' => false, 'primary_key' => 'id']);
        $table->addColumn('id', 'string', ['limit' => 40])
              ->addColumn('name', 'string', ['limit' => 255])
              ->addColumn('description', 'string', ['limit' => 255])
              ->addColumn('active', 'boolean', ['default' => true])
              ->addColumn('is_deleted', 'boolean', ['default' => false, 'null' => true])
              ->addColumn('deleted_at', 'timestamp', ['null' => true])
              ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
              ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
              ->create();
    }
}
