<?php

declare(strict_types=1);

namespace App\Modules\Dashboard;

class DashboardService
{
    public function __construct(
        private readonly DashboardRepository $repository,
    ) {
    }

    /** @return array<string, mixed> */
    public function getStats(?string $createdAtStart, ?string $createdAtEnd): array
    {
        $start = $this->parseStartDate($createdAtStart);
        $end = $this->parseEndDate($createdAtEnd);

        $startUtc = clone $start;
        $startUtc->setTimezone(new \DateTimeZone('UTC'));
        $endUtc = clone $end;
        $endUtc->setTimezone(new \DateTimeZone('UTC'));

        $startStr = $startUtc->format('Y-m-d H:i:s');
        $endStr = $endUtc->format('Y-m-d H:i:s');

        $tz = date_default_timezone_get();

        // @codeCoverageIgnoreStart
        $driver = \Illuminate\Database\Capsule\Manager::connection()->getDriverName();
        if ($driver === 'sqlite') {
            $dateExpression = "strftime('%Y-%m-%d', created_at)";
        } else {
            $dateExpression = "to_char(created_at AT TIME ZONE 'UTC' AT TIME ZONE '$tz', 'YYYY-MM-DD')";
        }
        // @codeCoverageIgnoreEnd

        return [
            'userCreationStats' => $this->repository->getUserStats($dateExpression, $startStr, $endStr),
            'productCreationStats' => $this->repository->getProductStats($dateExpression, $startStr, $endStr),
            'productsPerUser' => $this->repository->getProductsPerUser($startStr, $endStr),
        ];
    }

    private function parseStartDate(?string $dateStr, int $defaultDaysAgo = 30): \DateTime
    {
        $tz = new \DateTimeZone(date_default_timezone_get());
        if (empty($dateStr)) {
            $date = new \DateTime('now', $tz);
            $date->modify("-{$defaultDaysAgo} days");
            $date->setTime(0, 0, 0);
            return $date;
        }

        $date = \DateTime::createFromFormat('Y-m-d', $dateStr, $tz);
        if ($date === false) {
            $date = new \DateTime('now', $tz);
            $date->modify("-{$defaultDaysAgo} days");
            $date->setTime(0, 0, 0);
            return $date;
        }

        $date->setTime(0, 0, 0);
        return $date;
    }

    private function parseEndDate(?string $dateStr): \DateTime
    {
        $tz = new \DateTimeZone(date_default_timezone_get());
        if (empty($dateStr)) {
            return new \DateTime('now', $tz);
        }

        $date = \DateTime::createFromFormat('Y-m-d', $dateStr, $tz);
        if ($date === false) {
            return new \DateTime('now', $tz);
        }

        $date->setTime(23, 59, 59);
        return $date;
    }
}
