<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\EngineNotifReport;

use App\Models\EngineNotifReport;
use App\MoonShine\Resources\EngineNotifReport\Pages\EngineNotifReportFetchPage;
use App\MoonShine\Resources\EngineNotifReport\Pages\EngineNotifReportIndexPage;
use MoonShine\Contracts\Core\PageContract;
use MoonShine\Crud\Handlers\Handler;
use App\MoonShine\Handlers\GuardedExportHandler as ExportHandler;
use MoonShine\ImportExport\Contracts\HasImportExportContract;
use MoonShine\ImportExport\Traits\ImportExportConcern;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Support\Enums\Action;
use MoonShine\Support\ListOf;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Preview;
use MoonShine\Support\Enums\PageType;

/**
 * @extends ModelResource<EngineNotifReport, EngineNotifReportIndexPage,  EngineNotifReportFetchPage>
 */
class EngineNotifReportResource extends ModelResource implements HasImportExportContract
{
    use ImportExportConcern;

    protected string $model = EngineNotifReport::class;
    protected string $column = 'report_hour';
    protected string $title = 'Engine Notif Reports';

    protected string $sortColumn = 'report_hour';
    protected int $itemsPerPage = 10;
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
            EngineNotifReportIndexPage::class,
            EngineNotifReportFetchPage::class,
        ];
    }

    protected function exportFields(): iterable
    {
        return [
            Date::make('Jam', 'report_hour')->withTime()->format('Y-m-d H:i'),
            Number::make('MVRK Success',  'mvrk_success'),
            Number::make('MVRK Fail',     'mvrk_fail'),
            Number::make('MVRK Total',    'mvrk_total'),
            Number::make('SMS Success',   'sms_success'),
            Number::make('SMS Fail',      'sms_fail'),
            Number::make('SMS Total',     'sms_total'),
            Number::make('Email Success', 'email_success'),
            Number::make('Email Fail',    'email_fail'),
            Number::make('Email Total',   'email_total'),
            Number::make('Total Success', 'total_success'),
            Number::make('Total Fail',    'total_fail'),
            Preview::make('Avg RT (s)',   'avg_response_time')
                ->changeFill(fn($item) => number_format((float) $item->avg_response_time, 2) . 's'),
            Preview::make('Avg Lifespan (ms)', 'avg_lifespan')
                ->changeFill(fn($item) => number_format((float) $item->avg_lifespan, 2) . 'ms'),
        ];
    }

    protected function handlers(): ListOf
    {
        return new ListOf(Handler::class, [
            ExportHandler::make('Export Excel')->alias('export-excel')->filename('engine_notif_' . date('Ymd-His')),
            ExportHandler::make('Export CSV')->alias('export-csv')->csv()->filename('engine_notif_' . date('Ymd-His')),
        ]);
    }
}
