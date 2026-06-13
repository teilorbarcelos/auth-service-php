<?php

declare(strict_types=1);

namespace App\Modules\Dashboard;

use Illuminate\Database\Capsule\Manager as Capsule;

class DashboardRepository
{
    /** @return array<int, array{date: string, count: int}> */
    public function getUserStats(string $dateExpression, string $startStr, string $endStr): array
    {
        $rows = Capsule::table('users')
            ->selectRaw("$dateExpression as date, count(*) as count")
            ->where('created_at', '>=', $startStr)
            ->where('created_at', '<=', $endStr)
            ->where('is_deleted', '=', false)
            ->groupByRaw("$dateExpression")
            ->orderBy('date', 'asc')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $result[] = ['date' => (string) ($row->date ?? ''), 'count' => (int) ($row->count ?? 0)];
        }
        return $result;
    }

    /** @return array<int, array{date: string, count: int}> */
    public function getProductStats(string $dateExpression, string $startStr, string $endStr): array
    {
        $rows = Capsule::table('products')
            ->selectRaw("$dateExpression as date, count(*) as count")
            ->where('created_at', '>=', $startStr)
            ->where('created_at', '<=', $endStr)
            ->where('is_deleted', '=', false)
            ->groupByRaw("$dateExpression")
            ->orderBy('date', 'asc')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $result[] = ['date' => (string) ($row->date ?? ''), 'count' => (int) ($row->count ?? 0)];
        }
        return $result;
    }

    /** @return array<int, array{userId: string|null, userName: string, count: int}> */
    public function getProductsPerUser(string $startStr, string $endStr): array
    {
        $rows = Capsule::table('products as p')
            ->leftJoin('users as u', function ($join) {
                $join->on(Capsule::connection()->raw('CAST(p.id_user AS text)'), '=', 'u.id');
            })
            ->selectRaw('p.id_user as "userId", COALESCE(u.name, \'Anonymous\') as "userName", count(*) as count')
            ->where('p.created_at', '>=', $startStr)
            ->where('p.created_at', '<=', $endStr)
            ->where('p.is_deleted', '=', false)
            ->groupBy('p.id_user', 'u.name')
            ->orderBy('count', 'desc')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'userId' => $row->userId ?? $row->userid ?? null,
                'userName' => $row->userName ?? $row->username ?? 'Anonymous',
                'count' => (int) ($row->count ?? 0),
            ];
        }
        return $result;
    }
}
