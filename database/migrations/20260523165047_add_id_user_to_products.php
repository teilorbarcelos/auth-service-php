<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddIdUserToProducts extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('products');
        $table->addColumn('id_user', 'uuid', ['null' => true])
              ->update();
    }
}
