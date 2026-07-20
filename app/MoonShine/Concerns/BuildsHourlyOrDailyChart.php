<?php

declare(strict_types=1);

namespace App\MoonShine\Concerns;

use Illuminate\Support\Collection;

/**
 * Dipakai oleh IndexPage yang chart-nya berbasis data per jam. Saat rentang tanggal
 * yang difilter lebar (banyak hari), chart per-jam jadi terlalu padat/noisy untuk
 * dibaca trennya — trait ini menurunkan granularitas jadi per hari secara otomatis.
 */
trait BuildsHourlyOrDailyChart
{
    /**
     * @param  Collection  $data       baris data (1 baris = 1 jam)
     * @param  callable  $dateOf       fn($row): \Carbon\Carbon  tanggal kalender baris
     * @param  callable  $hourOf       fn($row): int  jam baris (0-23)
     * @param  int  $dailyThresholdDays  switch ke per-hari kalau rentang > sekian hari kalender
     * @return array{isDaily: bool, label: callable}
     */
    protected function chartGranularity(Collection $data, callable $dateOf, callable $hourOf, int $dailyThresholdDays = 3): array
    {
        $days    = $data->map(fn($r) => $dateOf($r)->format('Y-m-d'))->unique();
        $isDaily = $days->count() > $dailyThresholdDays;

        if ($isDaily) {
            return [
                'isDaily' => true,
                'label'   => fn($r) => $dateOf($r)->format('d/m/Y'),
            ];
        }

        $isSingleDay = $days->count() === 1;

        return [
            'isDaily' => false,
            'label'   => $isSingleDay
                ? fn($r) => sprintf('%02d:00', $hourOf($r))
                : fn($r) => $dateOf($r)->format('d/m') . ' ' . sprintf('%02d:00', $hourOf($r)),
        ];
    }
}
