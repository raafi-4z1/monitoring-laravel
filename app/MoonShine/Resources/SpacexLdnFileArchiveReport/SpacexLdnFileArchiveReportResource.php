<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\SpacexLdnFileArchiveReport;

use App\Models\SpacexLdnFileArchiveReport;
use App\MoonShine\Handlers\GuardedExportHandler as ExportHandler;
use App\MoonShine\Resources\SpacexLdnFileArchiveReport\Pages\SpacexLdnFileArchiveReportFetchPage;
use App\MoonShine\Resources\SpacexLdnFileArchiveReport\Pages\SpacexLdnFileArchiveReportIndexPage;
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
 * @extends ModelResource<SpacexLdnFileArchiveReport, SpacexLdnFileArchiveReportIndexPage, SpacexLdnFileArchiveReportFetchPage>
 */
class SpacexLdnFileArchiveReportResource extends ModelResource implements HasImportExportContract
{
    use ImportExportConcern;

    protected string $model         = SpacexLdnFileArchiveReport::class;
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
        $value   = (int) (session()?->get('spacexLdnFileArchivePerPage') ?? $default);

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
            SpacexLdnFileArchiveReportIndexPage::class,
            SpacexLdnFileArchiveReportFetchPage::class,
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
            ExportHandler::make('Export Excel')->alias('export-excel')->filename('spacex_ldn_file_archive_' . date('Ymd-His'))->forceSort('trx_date'),
            ExportHandler::make('Export CSV')->alias('export-csv')->csv()->filename('spacex_ldn_file_archive_' . date('Ymd-His'))->forceSort('trx_date'),
        ]);
    }
}
