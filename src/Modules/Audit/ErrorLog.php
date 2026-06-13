<?php

declare(strict_types=1);

namespace App\Modules\Audit;

use App\Core\BaseModel;

/**
 * @property string $id
 * @property string $source
 * @property array<string, mixed> $error_data
 */
class ErrorLog extends BaseModel
{
    protected $table = 'audit.tb_error_log';
    public $timestamps = false;
    protected $fillable = [
        'id', 'id_user', 'source', 'error_message', 'error_data'
    ];

    /** @var array<string, string> */
    protected $casts = [
        'error_data' => 'array'
    ];

    /**
     * @codeCoverageIgnore
     */
    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (!$model->id) {
                $model->id = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }
}
