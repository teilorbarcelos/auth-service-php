<?php

declare(strict_types=1);

namespace App\Modules\User;

use App\Core\BaseService;


use App\Modules\User\UserRepository;
use App\Modules\User\User;
use Illuminate\Database\Eloquent\Model;
use Respect\Validation\Validator as v;

/**
 * @extends BaseService<User, UserRepository>
 */
class UserService extends BaseService
{
    protected array $filterableFields = ['name', 'email', 'active', 'id_role', 'role.name', 'createdAt', 'updatedAt'];
    protected array $searchableFields = ['name', 'email', 'role.name'];

    public function __construct(
        protected UserRepository $userRepository,
        protected \App\Infrastructure\Auth\JwtService $jwtService,
        protected \App\Infrastructure\Pdf\PdfProviderInterface $pdfProvider
    ) {
        $this->repository = $userRepository;
    }

    public function create(array $data): Model|array
    {
        $this->validate($data, [
            'name' => v::stringType()->notEmpty()->length(3, 255),
            'email' => v::email()->notEmpty(),
            'id_role' => v::stringType()->notEmpty(),
            'password' => v::optional(v::stringType()->notEmpty()),
        ]);

        $user = parent::create($data);

        if ($user instanceof User) {
            $password = isset($data['password']) && is_string($data['password']) ? $data['password'] : 'default@123';
            UserAuth::create([
                'id' => $user->id,
                'password' => password_hash($password, PASSWORD_BCRYPT),
                'first_access' => false
            ]);
        }

        return $user;
    }

    public function update(string $id, array $data): Model|array|null
    {
        $this->validate($data, [
            'name' => v::optional(v::stringType()->notEmpty()->length(3, 255)),
            'email' => v::optional(v::email()->notEmpty()),
            'id_role' => v::optional(v::stringType()->notEmpty()),
            'active' => v::optional(v::boolType()),
        ]);

        $user = parent::update($id, $data);

        if ($user) {
            $this->jwtService->invalidateUserTokens($id);
        }

        return $user;
    }

    public function setStatus(string $id, bool $active): Model|array|null
    {
        $user = parent::setStatus($id, $active);
        if ($user) {
            $this->jwtService->invalidateUserTokens($id);
        }
        return $user;
    }

    /**
     * @param array<string, mixed> $query
     * @return \Psr\Http\Message\StreamInterface
     */
    public function exportPdf(array $query): \Psr\Http\Message\StreamInterface
    {
        $orderBy = isset($query['orderBy']) && is_string($query['orderBy']) ? $query['orderBy'] : 'created_at';
        $orderDirection = isset($query['orderDirection']) && is_string($query['orderDirection']) ? $query['orderDirection'] : 'desc';

        $parsed = \App\Core\Helpers\QueryParserHelper::parseQueryParams(
            $query,
            $this->filterableFields,
            $this->searchableFields,
            false
        );

        \App\Core\Helpers\QueryParserHelper::validateOrder($orderBy, $this->filterableFields);

        $users = $this->repository->search(
            $parsed['andRules'],
            $parsed['orRules'],
            $orderBy,
            $orderDirection,
            ['role']
        );

        $usersData = array_map(function (User $user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'roleName' => $user->role ? $user->role->name : null,
                'active' => (bool)$user->active,
            ];
        }, $users);

        $pdfData = [
            'title' => 'Relatório de Usuários',
            'generatedAt' => (new \DateTime())->format('d/m/Y H:i:s'),
            'users' => $usersData,
        ];

        return $this->pdfProvider->generatePdf([
            'template' => 'user-list',
            'data' => $pdfData,
        ]);
    }
}
