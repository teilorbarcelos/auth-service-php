<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateFeatureTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('features', ['id' => false, 'primary_key' => 'id']);
        $table->addColumn('id', 'string', ['limit' => 40])
              ->addColumn('name', 'string', ['limit' => 255])
              ->addColumn('description', 'text')
              ->addColumn('active', 'boolean', ['default' => true])
              ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
              ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
              ->create();
    }
}
