<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\TrxPbiSettlementReport;

use App\Models\TrxPbiSettlementReport;
use App\MoonShine\Resources\TrxPbiSettlementReport\Pages\TrxPbiSettlementReportFetchPage;
use App\MoonShine\Resources\TrxPbiSettlementReport\Pages\TrxPbiSettlementReportIndexPage;
use MoonShine\Contracts\Core\PageContract;
use MoonShine\Crud\Handlers\Handler;
use MoonShine\ImportExport\Contracts\HasImportExportContract;
use MoonShine\ImportExport\ExportHandler;
use MoonShine\ImportExport\Traits\ImportExportConcern;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Support\Enums\Action;
use MoonShine\Support\Enums\PageType;
use MoonShine\Support\ListOf;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Preview;
use MoonShine\UI\Fields\Text;

/**
 * @extends ModelResource<TrxPbiSettlementReport, TrxPbiSettlementReportIndexPage, TrxPbiSettlementReportFetchPage>
 */
class TrxPbiSettlementReportResource extends ModelResource implements HasImportExportContract
{
    use ImportExportConcern;

    protected string $model        = TrxPbiSettlementReport::class;
    protected string $column       = 'trx_date';
    protected string $title        = 'TrxPBI Settlement';
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
        $value   = (int) (session()?->get('trxPbiSettlementPerPage') ?? $default);

        return in_array($value, $this->perPageValues()) ? $value : $default;
    }

    public function perPageValues(): array
    {
        return [
            25  => 25,
            50  => 50,
            100 => 100,
            200 => 200,
        ];
    }

    /**
     * @return list<class-string<PageContract>>
     */
    protected function pages(): array
    {
        return [
            TrxPbiSettlementReportIndexPage::class,
            TrxPbiSettlementReportFetchPage::class,
        ];
    }

    protected function exportFields(): iterable
    {
        return [
            Preview::make('app_id',             'id')->changeFill(fn($item) => $item->reportSource?->app_id ?? ''),
            Preview::make('data_source',        'id')->changeFill(fn($item) => $item->reportSource?->data_source ?? ''),
            Preview::make('data_source_name',   'id')->changeFill(fn($item) => $item->reportSource?->data_source_name ?? ''),
            Preview::make('trx_date',           'trx_date')
                ->changeFill(fn($item) => $item->trx_date?->format('Y-m-d')),
            Preview::make('trx_hour',           'trx_hour')
                ->changeFill(fn($item) => sprintf('%02d', $item->trx_hour)),
            Preview::make('service_name',       'id')->changeFill(fn($item) => $item->reportSource?->service_name ?? ''),
            Preview::make('service_integrator', 'id')->changeFill(fn($item) => $item->reportSource?->service_integrator ?? ''),
            Text::make('trx_currency',          'trx_currency'),
            Preview::make('trx_amount',         'trx_amount')
                ->changeFill(fn($item) => number_format((float) $item->trx_amount, 0, ',', '.')),
            Number::make('trx_count',           'trx_count'),
            Number::make('success_count',       'success_count'),
        ];
    }

    protected function handlers(): ListOf
    {
        return new ListOf(Handler::class, [
            ExportHandler::make('Export Excel')->alias('export-excel')->filename('trx_pbi_settlement_' . date('Ymd-His')),
            ExportHandler::make('Export CSV')->alias('export-csv')->csv()->filename('trx_pbi_settlement_' . date('Ymd-His')),
        ]);
    }
}
