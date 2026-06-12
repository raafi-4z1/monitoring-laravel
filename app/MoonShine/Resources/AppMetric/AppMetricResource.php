<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\AppMetric;

use App\Models\AppMetric;
use App\MoonShine\Resources\AppMetric\Pages\AppMetricFormPage;
use App\MoonShine\Resources\AppMetric\Pages\AppMetricIndexPage;
use Illuminate\Database\Eloquent\Builder;
use MoonShine\Contracts\Core\PageContract;
use MoonShine\Laravel\Resources\ModelResource;

/**
 * @extends ModelResource<AppMetric, AppMetricIndexPage, AppMetricFormPage>
 */
class AppMetricResource extends ModelResource
{
    protected string $model  = AppMetric::class;
    protected string $column = 'id';
    protected string $title  = 'App Metrics';

    protected function resolveQuery(): Builder
    {
        return AppMetric::with(['masterAplikasi', 'masterMetrik']);
    }

    protected string $sortColumn    = 'recorded_at';
    protected int    $itemsPerPage  = 25;
    protected bool   $usePagination = true;

    public function getItemsPerPage(): int
    {
        $default = $this->itemsPerPage;
        $value   = (int) (session()?->get('appMetricPerPage') ?? $default);

        return in_array($value, $this->perPageValues()) ? $value : $default;
    }

    public function perPageValues(): array
    {
        return [
            10  => 10,
            25  => 25,
            50  => 50,
            100 => 100,
        ];
    }

    /**
     * @return list<class-string<PageContract>>
     */
    protected function pages(): array
    {
        return [
            AppMetricIndexPage::class,
            AppMetricFormPage::class,
        ];
    }
}
