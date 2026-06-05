<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\MteleplusReport;

use App\Models\MteleplusReport;
use App\MoonShine\Resources\MteleplusReport\Pages\MteleplusReportFetchPage;
use App\MoonShine\Resources\MteleplusReport\Pages\MteleplusReportIndexPage;
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
use MoonShine\UI\Fields\Preview;

/**
 * @extends ModelResource<MteleplusReport, MteleplusReportIndexPage, MteleplusReportFetchPage>
 */
class MteleplusReportResource extends ModelResource implements HasImportExportContract
{
    use ImportExportConcern;

    protected string $model = MteleplusReport::class;
    protected string $column = 'report_date';
    protected string $title = 'Mteleplus Reports';

    protected string $sortColumn = 'report_date';
    protected int $itemsPerPage = 10;
    protected bool $usePagination = true;

    protected function activeActions(): ListOf
    {
        return parent::activeActions()
            ->except(Action::VIEW, Action::UPDATE, Action::DELETE, Action::MASS_DELETE)
        ;
    }

    public function getItemsPerPage(): int
    {
        $default = $this->itemsPerPage;
        $value   = (int) (session()?->get('perPage') ?? $default);

        if (! in_array($value, $this->perPageValues())) {
            return $default;
        }

        return $value;
    }

    public function perPageValues(): array
    {
        return [
            5  => 5,
            10 => 10,
            20 => 20,
            50 => 50,
            100 => 100,
        ];
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
            Date::make('Tanggal', 'report_date'),
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

    protected function export(): ?Handler
    {
        return ExportHandler::make('Export Excel')
            ->filename('mteleplus_' . date('Ymd-His'));
    }

    protected function import(): ?Handler
    {
        return null;
    }
}
