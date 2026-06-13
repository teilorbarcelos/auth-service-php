<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;
use App\Modules\Feature\Feature;
use App\Modules\Role\Role;
use App\Modules\User\User;
use App\Modules\User\UserAuth;
use Illuminate\Database\Capsule\Manager as Capsule;

class InitialSeed extends AbstractSeed
{
    public function run(): void
    {
        $capsule = new Capsule();
        $capsule->addConnection([
            'driver' => $_ENV['DB_DRIVER'] ?? getenv('DB_DRIVER') ?: 'pgsql',
            'host' => $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: 'db',
            'database' => $_ENV['DB_DATABASE'] ?? getenv('DB_DATABASE') ?: 'backend_php_slim_db',
            'username' => $_ENV['DB_USERNAME'] ?? getenv('DB_USERNAME') ?: 'postgres',
            'password' => $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?: 'postgrespw',
            'charset' => $_ENV['DB_CHARSET'] ?? getenv('DB_CHARSET') ?: 'utf8',
        ]);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        $features = [
            ['id' => 'dashboard', 'name' => 'Dashboard', 'description' => 'Visualizar indicadores'],
            ['id' => 'user', 'name' => 'Usuários', 'description' => 'Gerenciar usuários'],
            ['id' => 'role', 'name' => 'Perfis', 'description' => 'Gerenciar cargos'],
            ['id' => 'product', 'name' => 'Produtos', 'description' => 'Gerenciar produtos'],
        ];

        foreach ($features as $f) {
            Feature::updateOrCreate(['id' => $f['id']], $f);
        }

        $adminRole = Role::updateOrCreate(['id' => 'administrator'], [
            'id' => 'administrator',
            'name' => 'Administrador',
            'description' => 'Acesso total'
        ]);

        Role::updateOrCreate(['id' => 'admin'], [
            'id' => 'admin',
            'name' => 'Admin',
            'description' => 'Acesso administrativo'
        ]);

        Role::updateOrCreate(['id' => 'user'], [
            'id' => 'user',
            'name' => 'Usuário',
            'description' => 'Acesso básico'
        ]);

        $permissionsJson = json_encode(['create' => true, 'view' => true, 'delete' => true, 'activate' => true]);

        Capsule::table('role_features')->where('id_role', 'administrator')->delete();

        foreach ($features as $f) {
            Capsule::table('role_features')->updateOrInsert(
                ['id_role' => 'administrator', 'id_feature' => $f['id']],
                ['permissions' => $permissionsJson]
            );
        }

        $adminEmail = $_ENV['FIRST_USER'] ?? getenv('FIRST_USER') ?: 'admin@email.com';
        $adminPassword = $_ENV['FIRST_PASSWORD'] ?? getenv('FIRST_PASSWORD') ?: 'admin@123';

        $existingAdmin = User::where('email', $adminEmail)->first();
        $userId = $existingAdmin ? $existingAdmin->id : (string) \Illuminate\Support\Str::uuid();

        User::updateOrCreate(['email' => $adminEmail], [
            'id' => $userId,
            'name' => 'Administrator',
            'email' => $adminEmail,
            'id_role' => 'administrator',
            'active' => true
        ]);

        UserAuth::updateOrCreate(['id' => $userId], [
            'id' => $userId,
            'password' => password_hash($adminPassword, PASSWORD_BCRYPT),
            'first_access' => false
        ]);
    }
}
