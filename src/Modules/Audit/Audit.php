<?php

declare(strict_types=1);

namespace App\Modules\Audit;

use App\Core\BaseModel;

/**
 * @property string|null $diff_value
 * @property string $id
 */
class Audit extends BaseModel
{
    protected $table = 'audit.tb_audit';
    public $timestamps = false;
    protected $fillable = [
        'id', 'id_user', 'user_name', 'action_type', 'execute_type', 'class',
        'function', 'params', 'raw', 'table_name', 'diff_value', 'error',
        'host', 'ip', 'base_url', 'method', 'hostname', 'original_url'
    ];
}
