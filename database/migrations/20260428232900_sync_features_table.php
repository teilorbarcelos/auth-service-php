<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class SyncFeaturesTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('features');
        
        if (!$table->hasColumn('description')) {
            $table->addColumn('description', 'text', ['null' => true]);
        }
        
        if (!$table->hasColumn('active')) {
            $table->addColumn('active', 'boolean', ['default' => true]);
        }

        if (!$table->hasColumn('deleted_at')) {
            $table->addColumn('deleted_at', 'timestamp', ['null' => true]);
        }

        if (!$table->hasColumn('is_deleted')) {
            $table->addColumn('is_deleted', 'boolean', ['default' => false]);
        }

        $table->save();
    }
}
