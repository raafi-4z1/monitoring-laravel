<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\TrxPbiLimitReport;

use App\Models\TrxPbiLimitReport;
use App\MoonShine\Resources\TrxPbiLimitReport\Pages\TrxPbiLimitReportFetchPage;
use App\MoonShine\Resources\TrxPbiLimitReport\Pages\TrxPbiLimitReportIndexPage;
use MoonShine\Contracts\Core\PageContract;
use MoonShine\Crud\Handlers\Handler;
use MoonShine\ImportExport\Contracts\HasImportExportContract;
use MoonShine\ImportExport\ExportHandler;
use MoonShine\ImportExport\Traits\ImportExportConcern;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Support\Enums\Action;
use MoonShine\Support\ListOf;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Text;

/**
 * @extends ModelResource<TrxPbiLimitReport, TrxPbiLimitReportIndexPage, TrxPbiLimitReportFetchPage>
 */
class TrxPbiLimitReportResource extends ModelResource implements HasImportExportContract
{
    use ImportExportConcern;

    protected string $model       = TrxPbiLimitReport::class;
    protected string $column      = 'report_hour';
    protected string $title       = 'TrxPBI Limit';
    protected string $sortColumn  = 'report_hour';
    protected int    $itemsPerPage = 25;
    protected bool   $usePagination = true;

    protected function activeActions(): ListOf
    {
        return parent::activeActions()
            ->except(Action::VIEW, Action::UPDATE, Action::DELETE, Action::MASS_DELETE);
    }

    public function getItemsPerPage(): int
    {
        $default = $this->itemsPerPage;
        $value   = (int) (session()?->get('trxPbiLimitPerPage') ?? $default);

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
            TrxPbiLimitReportIndexPage::class,
            TrxPbiLimitReportFetchPage::class,
        ];
    }

    protected function exportFields(): iterable
    {
        return [
            Date::make('Jam', 'report_hour'),
            Text::make('CCY2', 'ccy2'),
            Number::make('Total Transaksi', 'total_trx'),
            Number::make('Total Nominal', 'total_nominal'),
            Number::make('Total NominalEqUSD', 'total_nominal_eq_usd'),
        ];
    }

    protected function export(): ?Handler
    {
        return ExportHandler::make('Export Excel')
            ->filename('trx_pbi_limit_' . date('Ymd-His'));
    }

    protected function import(): ?Handler
    {
        return null;
    }
}
