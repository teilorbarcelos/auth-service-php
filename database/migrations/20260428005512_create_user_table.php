<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateUserTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('users', ['id' => false, 'primary_key' => 'id']);
        $table->addColumn('id', 'string', ['limit' => 40])
              ->addColumn('name', 'string', ['limit' => 255])
              ->addColumn('phone', 'string', ['limit' => 15, 'null' => true])
              ->addColumn('email', 'string', ['limit' => 255])
              ->addColumn('cognito_id', 'string', ['limit' => 255, 'null' => true])
              ->addColumn('active', 'boolean', ['default' => true])
              ->addColumn('document', 'string', ['limit' => 20, 'null' => true])
              ->addColumn('is_deleted', 'boolean', ['default' => false, 'null' => true])
              ->addColumn('deleted_at', 'timestamp', ['null' => true])
              ->addColumn('avatar', 'string', ['limit' => 255, 'null' => true])
              ->addColumn('id_auth', 'string', ['limit' => 40, 'null' => true])
              ->addColumn('id_role', 'string', ['limit' => 40])
              ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
              ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
              ->addIndex(['email'], ['unique' => true])
              ->addIndex(['cognito_id'], ['unique' => true])
              ->addIndex(['id_auth'], ['unique' => true])
              ->addForeignKey('id_auth', 'auth', 'id', ['delete'=> 'SET_NULL', 'update'=> 'CASCADE'])
              ->addForeignKey('id_role', 'roles', 'id', ['delete'=> 'RESTRICT', 'update'=> 'CASCADE'])
              ->create();
    }
}
