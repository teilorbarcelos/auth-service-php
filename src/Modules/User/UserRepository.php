<?php

declare(strict_types=1);

namespace App\Modules\User;

use App\Core\BaseRepository;
use App\Modules\User\User;

/**
 * @extends BaseRepository<User>
 */
class UserRepository extends BaseRepository
{
    protected string $modelClass = User::class;

    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    public function delete(string $id): bool
    {
        $model = $this->find($id);
        if ($model) {
            $uniqueId = substr(bin2hex(random_bytes(4)), 0, 8);
            $model->update([
                'name' => 'Deleted User',
                'email' => "deleted-{$id}-anonymized-{$uniqueId}@email.com",
                'active' => false,
                'is_deleted' => true
            ]);
            return (bool) $model->delete();
        }
        return false;
    }
}
