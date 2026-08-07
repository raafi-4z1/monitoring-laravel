<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\SpacexLdnJobExecutionReport;

use App\Models\SpacexLdnJobExecutionReport;
use App\MoonShine\Handlers\GuardedExportHandler as ExportHandler;
use App\MoonShine\Resources\SpacexLdnJobExecutionReport\Pages\SpacexLdnJobExecutionReportFetchPage;
use App\MoonShine\Resources\SpacexLdnJobExecutionReport\Pages\SpacexLdnJobExecutionReportIndexPage;
use MoonShine\Contracts\Core\PageContract;
use MoonShine\Crud\Handlers\Handler;
use MoonShine\ImportExport\Contracts\HasImportExportContract;
use MoonShine\ImportExport\Traits\ImportExportConcern;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Support\Enums\Action;
use MoonShine\Support\Enums\PageType;
use MoonShine\Support\ListOf;
use MoonShine\UI\Fields\Preview;

/**
 * @extends ModelResource<SpacexLdnJobExecutionReport, SpacexLdnJobExecutionReportIndexPage, SpacexLdnJobExecutionReportFetchPage>
 */
class SpacexLdnJobExecutionReportResource extends ModelResource implements HasImportExportContract
{
    use ImportExportConcern;

    protected string $model         = SpacexLdnJobExecutionReport::class;
    protected string $column        = 'trx_date';
    protected string $title         = 'Job Execution';
    protected string $sortColumn    = 'trx_date';
    protected int    $itemsPerPage  = 25;
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
        $value   = (int) (session()?->get('spacexLdnJobExecutionPerPage') ?? $default);

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
            SpacexLdnJobExecutionReportIndexPage::class,
            SpacexLdnJobExecutionReportFetchPage::class,
        ];
    }

    protected function exportFields(): iterable
    {
        return [
            Preview::make('app_id',           'id')->changeFill(fn($i) => $i->reportSource?->app_id ?? ''),
            Preview::make('data_source',      'id')->changeFill(fn($i) => $i->reportSource?->data_source ?? ''),
            Preview::make('data_source_name', 'id')->changeFill(fn($i) => $i->reportSource?->data_source_name ?? ''),
            Preview::make('trx_date',      'trx_date')->changeFill(fn($i) => $i->trx_date?->format('Y-m-d') ?? ''),
            Preview::make('job_name',      'job_name'),
            Preview::make('start_time',    'start_time')->changeFill(fn($i) => $i->start_time?->format('Y-m-d H:i:s') ?? ''),
            Preview::make('end_time',      'end_time')->changeFill(fn($i) => $i->end_time?->format('Y-m-d H:i:s') ?? ''),
            Preview::make('duration',      'duration'),
            Preview::make('date_parameter','date_parameter'),
            Preview::make('filename',      'filename'),
            Preview::make('log_type',      'log_type'),
            Preview::make('status',        'status'),
        ];
    }

    protected function handlers(): ListOf
    {
        return new ListOf(Handler::class, [
            ExportHandler::make('Export Excel')->alias('export-excel')->filename('spacex_ldn_job_execution_' . date('Ymd-His'))->forceSort('trx_date'),
            ExportHandler::make('Export CSV')->alias('export-csv')->csv()->filename('spacex_ldn_job_execution_' . date('Ymd-His'))->forceSort('trx_date'),
        ]);
    }
}
