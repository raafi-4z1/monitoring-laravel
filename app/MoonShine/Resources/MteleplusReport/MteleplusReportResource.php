<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\MteleplusReport;

use App\Models\MteleplusReport;
use App\MoonShine\Resources\MteleplusReport\Pages\MteleplusReportFetchPage;
use App\MoonShine\Resources\MteleplusReport\Pages\MteleplusReportIndexPage;
use MoonShine\Contracts\Core\PageContract;
use MoonShine\Crud\Handlers\Handler;
use App\MoonShine\Handlers\GuardedExportHandler as ExportHandler;
use MoonShine\ImportExport\Contracts\HasImportExportContract;
use MoonShine\ImportExport\Traits\ImportExportConcern;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Support\Enums\Action;
use MoonShine\Support\ListOf;
use MoonShine\UI\Fields\Number;
use MoonShine\Support\Enums\PageType;
use MoonShine\UI\Fields\Preview;

/**
 * @extends ModelResource<MteleplusReport, MteleplusReportIndexPage, MteleplusReportFetchPage>
 */
class MteleplusReportResource extends ModelResource implements HasImportExportContract
{
    use ImportExportConcern;

    protected string $model = MteleplusReport::class;
    protected string $column = 'trx_date';
    protected string $title = 'Mteleplus Reports';

    protected string $sortColumn = 'trx_date';
    protected int $itemsPerPage = 25;
    protected bool $usePagination = true;
    protected ?PageType $redirectAfterSave = PageType::INDEX;

    protected function activeActions(): ListOf
    {
        return parent::activeActions()
            ->except(Action::VIEW, Action::UPDATE, Action::DELETE, Action::MASS_DELETE)
        ;
    }

    public function getItemsPerPage(): int
    {
        $default = $this->itemsPerPage;
        $value   = (int) (session()?->get('mteleplusPerPage') ?? $default);

        if (! in_array($value, $this->perPageValues())) {
            return $default;
        }

        return $value;
    }

    public function perPageValues(): array
    {
        return [
            25 => 25,
            50 => 50,
            100 => 100,
            200 => 200,
        ];
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
            MteleplusReportIndexPage::class,
            MteleplusReportFetchPage::class,
        ];
    }

    protected function exportFields(): iterable
    {
        return [
            Preview::make('app_id',           'id')->changeFill(fn($item) => $item->reportSource?->app_id ?? ''),
            Preview::make('data_source',      'id')->changeFill(fn($item) => $item->reportSource?->data_source ?? ''),
            Preview::make('data_source_name', 'id')->changeFill(fn($item) => $item->reportSource?->data_source_name ?? ''),
            Preview::make('trx_date', 'trx_date')->changeFill(fn($item) => $item->trx_date?->format('Y-m-d') ?? ''),
            Preview::make('trx_hour', 'trx_hour')->changeFill(fn($item) => sprintf('%02d', $item->trx_hour)),
            Number::make('AKT Success',    'akt_success'),
            Number::make('AKT Fail',       'akt_fail'),
            Preview::make('AKT Total',     'akt_total'),
            Number::make('RPIN Success',   'rpin_success'),
            Number::make('RPIN Fail',      'rpin_fail'),
            Preview::make('RPIN Total',    'rpin_total'),
            Preview::make('Total Success', 'total_success'),
            Preview::make('Total Fail',    'total_fail'),
            Number::make('Incoming',       'total_incoming'),
            Number::make('Outgoing',       'total_outgoing'),
        ];
    }

    protected function handlers(): ListOf
    {
        return new ListOf(Handler::class, [
            ExportHandler::make('Export Excel')->alias('export-excel')->filename('mteleplus_' . date('Ymd-His'))->forceSort('trx_date'),
            ExportHandler::make('Export CSV')->alias('export-csv')->csv()->filename('mteleplus_' . date('Ymd-His'))->forceSort('trx_date'),
        ]);
    }
}
