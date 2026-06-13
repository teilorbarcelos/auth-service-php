<?php

declare(strict_types=1);

namespace App\Core;

use App\Modules\User\User;
use App\Modules\User\UserAuth;
use App\Modules\Role\Role;
use App\Modules\Feature\Feature;

class DatabaseBootstrap
{
    public static function init(): void
    {
        $adminEmail = $_ENV['FIRST_USER'] ?? 'admin@email.com';
        $adminPassword = $_ENV['FIRST_PASSWORD'] ?? 'admin@123';

        if (User::withTrashed()->where('email', $adminEmail)->exists()) {
            return;
        }

        $hash = md5($adminEmail);
        $adminId = sprintf(
            '%08s-%04s-%04x-%04x-%12s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            (hexdec(substr($hash, 12, 4)) & 0x0fff) | 0x4000,
            (hexdec(substr($hash, 16, 4)) & 0x3fff) | 0x8000,
            substr($hash, 20, 12)
        );

        $adminRole = Role::firstOrCreate(['id' => 'administrator'], [
            'id' => 'administrator',
            'name' => 'Administrador',
            'description' => 'Acesso total ao sistema'
        ]);

        Role::firstOrCreate(['id' => 'user'], [
            'id' => 'user',
            'name' => 'Usuário',
            'description' => 'Acesso básico'
        ]);

        User::create([
            'id' => $adminId,
            'name' => 'Administrator',
            'email' => $adminEmail,
            'id_role' => $adminRole->id,
            'active' => true
        ]);

        UserAuth::create([
            'id' => $adminId,
            'password' => password_hash($adminPassword, PASSWORD_BCRYPT),
            'first_access' => false
        ]);

        $features = [
            ['id' => 'user', 'name' => 'Usuários', 'description' => 'Gestão de usuários'],
            ['id' => 'role', 'name' => 'Papéis', 'description' => 'Gestão de papéis e permissões'],
            ['id' => 'product', 'name' => 'Produtos', 'description' => 'Gestão de produtos'],
        ];

        $syncData = [];
        foreach ($features as $f) {
            Feature::firstOrCreate(['id' => $f['id']], $f);
            $syncData[$f['id']] = [
                'permissions' => json_encode([
                    'create' => true,
                    'view' => true,
                    'delete' => true,
                    'activate' => true
                ])
            ];
        }

        $adminRole->features()->sync($syncData);
    }
}
