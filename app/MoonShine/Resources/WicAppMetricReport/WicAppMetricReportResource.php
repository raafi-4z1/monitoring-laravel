<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\WicAppMetricReport;

use App\Models\WicAppMetricReport;
use App\MoonShine\Resources\WicAppMetricReport\Pages\WicAppMetricReportFetchPage;
use App\MoonShine\Resources\WicAppMetricReport\Pages\WicAppMetricReportIndexPage;
use MoonShine\Contracts\Core\PageContract;
use MoonShine\Crud\Handlers\Handler;
use App\MoonShine\Handlers\GuardedExportHandler as ExportHandler;
use MoonShine\ImportExport\Contracts\HasImportExportContract;
use MoonShine\ImportExport\Traits\ImportExportConcern;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Support\Enums\Action;
use MoonShine\Support\Enums\PageType;
use MoonShine\Support\ListOf;
use MoonShine\UI\Fields\Preview;

/**
 * @extends ModelResource<WicAppMetricReport, WicAppMetricReportIndexPage, WicAppMetricReportFetchPage>
 */
class WicAppMetricReportResource extends ModelResource implements HasImportExportContract
{
    use ImportExportConcern;

    protected string $model        = WicAppMetricReport::class;
    protected string $column       = 'trx_date';
    protected string $title        = 'WIC APP Metric';
    protected string $sortColumn   = 'trx_date';
    protected int    $itemsPerPage = 25;
    protected bool   $usePagination = true;

    protected ?PageType $redirectAfterSave = PageType::INDEX;

    protected function activeActions(): ListOf
    {
        return parent::activeActions()
            ->except(Action::VIEW, Action::UPDATE, Action::DELETE, Action::MASS_DELETE);
    }

    public function getItemsPerPage(): int
    {
        $default = $this->itemsPerPage;
        $value   = (int) (session()?->get('wicAppMetricPerPage') ?? $default);
        return in_array($value, $this->perPageValues()) ? $value : $default;
    }

    public function perPageValues(): array
    {
        return [25 => 25, 50 => 50, 100 => 100, 200 => 200];
    }

    protected function search(): array
    {
        return [];
    }

    /**
     * @return list<class-string<PageContract>>
     */
    protected function pages(): array
    {
        return [
            WicAppMetricReportIndexPage::class,
            WicAppMetricReportFetchPage::class,
        ];
    }

    protected function exportFields(): iterable
    {
        return [
            Preview::make('app_id',              'id')->changeFill(fn($i) => $i->reportSource?->app_id ?? ''),
            Preview::make('data_source',         'id')->changeFill(fn($i) => $i->reportSource?->data_source ?? ''),
            Preview::make('data_source_name',    'id')->changeFill(fn($i) => $i->reportSource?->data_source_name ?? ''),
            Preview::make('trx_date',            'trx_date')->changeFill(fn($i) => $i->trx_date?->format('Y-m-d') ?? ''),
            Preview::make('trx_hour',            'trx_hour')->changeFill(fn($i) => sprintf('%02d', $i->trx_hour)),
            Preview::make('hostname',            'id')->changeFill(fn($i) => $i->reportSource?->service_integrator ?? ''),
            Preview::make('role_type',           'metric_type')->changeFill(fn($i) => match($i->metric_type) {
                'disk'   => 'Disk ' . $i->disk_path,
                'cpu'    => 'CPU',
                'memory' => 'Memory',
                default  => $i->metric_type,
            }),
            Preview::make('utilization_avg_pct', 'avg_pct')->changeFill(fn($i) => $i->avg_pct !== null ? number_format($i->avg_pct * 100, 2) : ($i->last_pct !== null ? number_format($i->last_pct * 100, 2) : '')),
            Preview::make('utilization_min_pct', 'min_pct')->changeFill(fn($i) => $i->min_pct !== null ? number_format($i->min_pct * 100, 2) : ''),
            Preview::make('utilization_max_pct', 'max_pct')->changeFill(fn($i) => $i->max_pct !== null ? number_format($i->max_pct * 100, 2) : ''),
            Preview::make('utilization_p95_pct', 'p95_pct')->changeFill(fn($i) => $i->p95_pct !== null ? number_format($i->p95_pct * 100, 2) : ($i->avg_pct !== null ? number_format($i->avg_pct * 100, 2) : ($i->last_pct !== null ? number_format($i->last_pct * 100, 2) : ''))),
        ];
    }

    protected function handlers(): ListOf
    {
        return new ListOf(Handler::class, [
            ExportHandler::make('Export Excel')->alias('export-excel')->filename('wic_app_metric_' . date('Ymd-His')),
            ExportHandler::make('Export CSV')->alias('export-csv')->csv()->filename('wic_app_metric_' . date('Ymd-His')),
        ]);
    }
}
