<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\TrxPbiLoaderReport;

use App\Models\TrxPbiLoaderReport;
use App\MoonShine\Handlers\GuardedExportHandler as ExportHandler;
use App\MoonShine\Resources\TrxPbiLoaderReport\Pages\TrxPbiLoaderReportFetchPage;
use App\MoonShine\Resources\TrxPbiLoaderReport\Pages\TrxPbiLoaderReportIndexPage;
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
 * @extends ModelResource<TrxPbiLoaderReport, TrxPbiLoaderReportIndexPage, TrxPbiLoaderReportFetchPage>
 */
class TrxPbiLoaderReportResource extends ModelResource implements HasImportExportContract
{
    use ImportExportConcern;

    protected string $model         = TrxPbiLoaderReport::class;
    protected string $column        = 'trx_date';
    protected string $title         = 'Batch Job';
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
        $value   = (int) (session()?->get('trxPbiLoaderPerPage') ?? $default);

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
            TrxPbiLoaderReportIndexPage::class,
            TrxPbiLoaderReportFetchPage::class,
        ];
    }

    protected function exportFields(): iterable
    {
        return [
            Preview::make('app_id',                 'id')->changeFill(fn($i) => $i->reportSource?->app_id ?? ''),
            Preview::make('data_source',            'id')->changeFill(fn($i) => $i->reportSource?->data_source ?? ''),
            Preview::make('job_type',               'job_type'),
            Preview::make('job_name',               'job_name'),
            Preview::make('trx_date',               'trx_date')->changeFill(fn($i) => $i->trx_date?->format('Y-m-d') ?? ''),
            Preview::make('trx_hour',               'trx_hour')->changeFill(fn($i) => sprintf('%02d', $i->trx_hour)),
            Preview::make('start_time',             'start_time')->changeFill(fn($i) => $i->start_time ?? ''),
            Preview::make('end_time',               'end_time')->changeFill(fn($i) => $i->end_time ?? ''),
            Preview::make('durations (sec)',        'duration_sec')->changeFill(fn($i) => (string) $i->duration_sec),
            Preview::make('record_processed',       'record_processed')->changeFill(fn($i) => (string) $i->record_processed),
            Preview::make('throughput_row_per_sec', 'throughput_row_per_sec')->changeFill(fn($i) => number_format($i->throughput_row_per_sec, 2, ',', '')),
            Preview::make('status_job',             'status_job'),
        ];
    }

    protected function handlers(): ListOf
    {
        return new ListOf(Handler::class, [
            ExportHandler::make('Export Excel')->alias('export-excel')->filename('trx_pbi_loader_' . date('Ymd-His')),
            ExportHandler::make('Export CSV')->alias('export-csv')->csv()->filename('trx_pbi_loader_' . date('Ymd-His')),
        ]);
    }
}
