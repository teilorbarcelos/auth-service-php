<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateAuthTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('auth', ['id' => false, 'primary_key' => 'id']);
        $table->addColumn('id', 'string', ['limit' => 40])
              ->addColumn('password', 'string', ['limit' => 255, 'null' => true])
              ->addColumn('request_password_token', 'string', ['limit' => 255, 'null' => true])
              ->addColumn('request_password_expiration', 'timestamp', ['null' => true])
              ->addColumn('retries', 'integer', ['default' => 0])
              ->addColumn('first_access', 'boolean', ['default' => true])
              ->addColumn('active', 'boolean', ['default' => true])
              ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
              ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
              ->create();
    }
}
