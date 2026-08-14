<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\SpacexNycFileArchiveReport;

use App\Models\SpacexNycFileArchiveReport;
use App\MoonShine\Handlers\GuardedExportHandler as ExportHandler;
use App\MoonShine\Resources\SpacexNycFileArchiveReport\Pages\SpacexNycFileArchiveReportFetchPage;
use App\MoonShine\Resources\SpacexNycFileArchiveReport\Pages\SpacexNycFileArchiveReportIndexPage;
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
 * @extends ModelResource<SpacexNycFileArchiveReport, SpacexNycFileArchiveReportIndexPage, SpacexNycFileArchiveReportFetchPage>
 */
class SpacexNycFileArchiveReportResource extends ModelResource implements HasImportExportContract
{
    use ImportExportConcern;

    protected string $model         = SpacexNycFileArchiveReport::class;
    protected string $column        = 'trx_date';
    protected string $title         = 'File Archive';
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
        $value   = (int) (session()?->get('spacexNycFileArchivePerPage') ?? $default);

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
            SpacexNycFileArchiveReportIndexPage::class,
            SpacexNycFileArchiveReportFetchPage::class,
        ];
    }

    protected function exportFields(): iterable
    {
        return [
            Preview::make('app_id',           'id')->changeFill(fn($i) => $i->reportSource?->app_id ?? ''),
            Preview::make('data_source',      'id')->changeFill(fn($i) => $i->reportSource?->data_source ?? ''),
            Preview::make('data_source_name', 'id')->changeFill(fn($i) => $i->reportSource?->data_source_name ?? ''),
            Preview::make('trx_date',      'trx_date')->changeFill(fn($i) => $i->trx_date?->format('Y-m-d') ?? ''),
            Preview::make('filename',      'filename'),
            Preview::make('pathfile',      'pathfile')->changeFill(fn($i) => $i->pathfile ?: ''),
            Preview::make('row_count',     'row_count')->changeFill(fn($i) => $i->row_count !== null ? (string) $i->row_count : ''),
            Preview::make('status',        'status'),
            Preview::make('timestamp',     'timestamp'),
        ];
    }

    protected function handlers(): ListOf
    {
        return new ListOf(Handler::class, [
            ExportHandler::make('Export Excel')->alias('export-excel')->filename('spacex_nyc_file_archive_' . date('Ymd-His'))->forceSort('trx_date'),
            ExportHandler::make('Export CSV')->alias('export-csv')->csv()->filename('spacex_nyc_file_archive_' . date('Ymd-His'))->forceSort('trx_date'),
        ]);
    }
}
