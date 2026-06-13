<?php

declare(strict_types=1);

namespace App\Modules\Audit;

use App\Modules\Audit\Audit;
use App\Infrastructure\Auth\UserSession;
use App\Infrastructure\Messaging\RabbitMQProvider;
use Illuminate\Database\Eloquent\Model;

class AuditObserver
{
    public function __construct(
        private readonly UserSession $userSession,
        private readonly ?RabbitMQProvider $messaging = null,
    ) {
    }

    public function created(Model $model): void
    {
        $this->log($model, 'CREATE');
    }

    public function updated(Model $model): void
    {
        $this->log($model, 'UPDATE');
    }

    public function deleted(Model $model): void
    {
        $this->log($model, 'DELETE');
    }

    private function log(Model $model, string $action): void
    {
        if (in_array($model->getTable(), ['tb_audit', 'tb_error_log', 'audit.tb_audit', 'audit.tb_error_log'])) {
            return;
        }

        $diff = null;
        if ($action === 'UPDATE') {
            $diff = $model->getDirty();
        } elseif ($action === 'CREATE') {
            $diff = $model->getAttributes();
        }

        $user = $this->userSession->getUser();
        $payload = [
            'table_name' => $model->getTable(),
            'action_type' => $action,
            'id_user' => $this->userSession->getUserId(),
            'user_name' => $user['name'] ?? null,
            'diff_value' => $diff ? json_encode($diff) : null,
            'raw' => json_encode($model->getAttributes()),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
            'original_url' => $_SERVER['REQUEST_URI'] ?? '',
        ];

        try {
            if ($this->messaging && ($_ENV['AUDIT_ASYNC'] ?? 'false') === 'true') {
                $this->messaging->publish('audit', $payload);
            } else {
                Audit::create($payload);
            }
        // @codeCoverageIgnoreStart
        } catch (\Throwable $e) {
            error_log("Audit failure: " . $e->getMessage());
        }
        // @codeCoverageIgnoreEnd
    }
}
