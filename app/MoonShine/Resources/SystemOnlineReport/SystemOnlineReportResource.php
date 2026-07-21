<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\SystemOnlineReport;

use App\Models\SystemOnlineReport;
use App\MoonShine\Handlers\GuardedExportHandler as ExportHandler;
use App\MoonShine\Resources\SystemOnlineReport\Pages\SystemOnlineReportFetchPage;
use App\MoonShine\Resources\SystemOnlineReport\Pages\SystemOnlineReportIndexPage;
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
 * @extends ModelResource<SystemOnlineReport, SystemOnlineReportIndexPage, SystemOnlineReportFetchPage>
 */
class SystemOnlineReportResource extends ModelResource implements HasImportExportContract
{
    use ImportExportConcern;

    protected string $model         = SystemOnlineReport::class;
    protected string $column        = 'trx_date';
    protected string $title         = 'System Online';
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
        $value   = (int) (session()?->get('systemOnlinePerPage') ?? $default);

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
            SystemOnlineReportIndexPage::class,
            SystemOnlineReportFetchPage::class,
        ];
    }

    protected function exportFields(): iterable
    {
        return [
            Preview::make('app_id',                 'id')->changeFill(fn($i) => $i->reportSource?->app_id ?? ''),
            Preview::make('data_source',             'id')->changeFill(fn($i) => $i->reportSource?->data_source ?? ''),
            Preview::make('data_source_name',        'id')->changeFill(fn($i) => $i->reportSource?->data_source_name ?? ''),
            Preview::make('trx_date',                'trx_date')->changeFill(fn($i) => $i->trx_date?->format('Y-m-d') ?? ''),
            Preview::make('trx_hour',                'trx_hour')->changeFill(fn($i) => sprintf('%02d', $i->trx_hour)),
            Preview::make('service_name',            'service_name'),
            Preview::make('called_app',              'id')->changeFill(fn($i) => '-'),
            Preview::make('response_time_avg_ms',    'response_time_avg_ms')->changeFill(fn($i) => number_format($i->response_time_avg_ms, 1, ',', '')),
        ];
    }

    protected function handlers(): ListOf
    {
        return new ListOf(Handler::class, [
            ExportHandler::make('Export Excel')->alias('export-excel')->filename('system_online_' . date('Ymd-His'))->forceSort('trx_date'),
            ExportHandler::make('Export CSV')->alias('export-csv')->csv()->filename('system_online_' . date('Ymd-His'))->forceSort('trx_date'),
        ]);
    }
}
