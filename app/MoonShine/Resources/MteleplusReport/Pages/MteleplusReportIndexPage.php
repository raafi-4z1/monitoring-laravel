<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\MteleplusReport\Pages;

use App\Models\MteleplusReport;
use App\MoonShine\Resources\MteleplusReport\MteleplusReportResource;
use Illuminate\Support\Collection;
use MoonShine\Apexcharts\Components\DonutChartMetric;
use MoonShine\Apexcharts\Components\LineChartMetric;
use MoonShine\Apexcharts\Support\SeriesItem;
use MoonShine\Contracts\UI\ActionButtonContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Crud\Buttons\CreateButton;
use MoonShine\Crud\Components\Fragment;
use MoonShine\Crud\JsonResponse;
use MoonShine\Crud\QueryTags\QueryTag;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\Support\AlpineJs;
use MoonShine\Support\Attributes\AsyncMethod;
use MoonShine\Support\Enums\JsEvent;
use MoonShine\Support\ListOf;
use MoonShine\UI\Components\Alert;
use MoonShine\UI\Components\Layout\Column;
use MoonShine\UI\Components\Layout\Div;
use MoonShine\UI\Components\Layout\Divider;
use MoonShine\UI\Components\Layout\Grid;
use MoonShine\UI\Components\Metrics\Wrapped\ValueMetric;
use MoonShine\UI\Components\Table\TableBuilder;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\DateRange;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Preview;
use MoonShine\UI\Fields\Select;
use Throwable;


/**
 * @extends IndexPage<MteleplusReportResource>
 */
class MteleplusReportIndexPage extends IndexPage
{
    protected bool $isLazy = true;

    protected function assets(): array
    {
        return [
            ...DonutChartMetric::make('')->getAssets(),
        ];
    }

    /**
     * @return list<FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            Date::make('Tanggal', 'report_date')->sortable()->format('Y-m-d'),
            Number::make('AKT Success',  'akt_success')->sortable(),
            Number::make('AKT Fail',     'akt_fail')->sortable(),
            Preview::make('AKT Total',   'akt_total')
                ->changeFill(fn($item) => number_format($item->akt_total))
                ->sortable(),
            Number::make('RPIN Success', 'rpin_success')->sortable(),
            Number::make('RPIN Fail',    'rpin_fail')->sortable(),
            Preview::make('RPIN Total',  'rpin_total')
                ->changeFill(fn($item) => number_format($item->rpin_total))
                ->sortable(),
            Preview::make('Total Success', 'total_success')
                ->changeFill(fn($item) => number_format($item->total_success))
                ->sortable(),
            Preview::make('Total Fail',    'total_fail')
                ->changeFill(fn($item) => number_format($item->total_fail))
                ->sortable(),
            Number::make('Incoming', 'total_incoming')->sortable(),
            Number::make('Outgoing', 'total_outgoing')->sortable(),
        ];
    }

    protected function topLeftButtons(): ListOf
    {
        return new ListOf(ActionButtonContract::class, [
            CreateButton::for(
                label: 'Fetch Manual',
                resource: $this->getResource(),
            ),
        ]);
    }

    /**
     * @return list<FieldContract>
     */
    protected function filters(): iterable
    {
        return [
            DateRange::make('Tanggal', 'report_date'),
        ];
    }

    /**
     * @return list<QueryTag>
     */
    protected function queryTags(): array
    {
        return [];
    }

    /**
     * @param  TableBuilder  $component
     *
     * @return TableBuilder
     */
    protected function modifyListComponent(ComponentContract $component): ComponentContract
    {
        return $component
            ->columnSelection()
            ->sticky()
            ->stickyButtons()
            ->async(events: [
                AlpineJs::event(JsEvent::FRAGMENT_UPDATED, 'mteleplus-charts'),
            ])
            ->topRight(function () {
                return [
                    Div::make([
                        Select::make('Per page')
                            ->onChangeMethod('changeListingComponentState')
                            ->options($this->getResource()->perPageValues())
                            ->withoutWrapper()
                            ->native()
                            ->setValue($this->getResource()->getItemsPerPage()),
                    ]),
                ];
            });
    }

    #[AsyncMethod]
    public function changeListingComponentState(): JsonResponse
    {
        $perPage = request()->integer('value');

        if ($perPage > 0) {
            session(['mteleplusPerPage' => $perPage]);
        }

        return JsonResponse::make()
            ->events([
                AlpineJs::event(JsEvent::TABLE_UPDATED, $this->getListComponentName()),
            ]);
    }


    /**
     * @return list<ComponentContract>
     * @throws Throwable
     */
    protected function topLayer(): array
    {
        return [
            ...parent::topLayer()
        ];
    }

    /**
     * @return list<ComponentContract>
     * @throws Throwable
     */
    protected function mainLayer(): array
    {
        [, , $period, $data] = $this->getFilteredData();

        return [
            $this->lastUpdateAlert(),
            ...parent::mainLayer(),

            Fragment::make([
                $this->buildCharts($data, $period),
            ])
            ->name('mteleplus-charts')
            ->withQueryParams(),
        ];
    }

    /**
     * @return list<ComponentContract>
     * @throws Throwable
     */
    protected function bottomLayer(): array
    {
        return [
            ...parent::bottomLayer()
        ];
    }

    protected function lastUpdateAlert(): Alert
    {
        $latest = MteleplusReport::latest('report_date')->first();

        return $latest
            ? Alert::make(type: 'info')
                ->content("Data terakhir: <strong>{$latest->report_date->format('d M Y')}</strong> — diupdate: {$latest->updated_at->format('d M Y H:i')}")
            : Alert::make(type: 'warning')
                ->content('Belum ada data. Gunakan Fetch Manual.');
    }

    private function getFilteredData(): array
    {
        $from = request()->input('_data.filter.report_date.from')
             ?? request()->input('filter.report_date.from')
             ?? now()->startOfMonth()->format('Y-m-d');

        $to   = request()->input('_data.filter.report_date.to')
             ?? request()->input('filter.report_date.to')
             ?? now()->format('Y-m-d');

        $dateFrom = !empty($from) ? $from : now()->startOfMonth()->format('Y-m-d');
        $dateTo   = !empty($to)   ? $to   : now()->format('Y-m-d');

        $period = \Carbon\Carbon::parse($dateFrom)->format('d M Y')
                . ' - '
                . \Carbon\Carbon::parse($dateTo)->format('d M Y');

        $data = MteleplusReport::query()
            ->whereBetween('report_date', [$dateFrom, $dateTo])
            ->orderBy('report_date')
            ->get();

        return [$dateFrom, $dateTo, $period, $data];
    }

    private function buildCharts(Collection $data, string $period): Grid
    {
        if ($data->isEmpty()) {
            return Grid::make([
                Column::make([Divider::make()])->columnSpan(12),
                Column::make([
                    Alert::make(type: 'info')->content('Tidak ada data untuk periode ini.'),
                ])->columnSpan(12),
            ]);
        }

        $totalSuccess  = $data->sum('total_success');
        $totalFail     = $data->sum('total_fail');
        $totalIncoming = $data->sum('total_incoming');
        $totalOutgoing = $data->sum('total_outgoing');
        $totalAkt      = $data->sum('akt_success') + $data->sum('akt_fail');
        $totalRpin     = $data->sum('rpin_success') + $data->sum('rpin_fail');

        $aktSuccessByDate  = $data->mapWithKeys(fn($r) => [$r->report_date->format('Y-m-d') => (int) $r->akt_success])->toArray();
        $aktFailByDate     = $data->mapWithKeys(fn($r) => [$r->report_date->format('Y-m-d') => (int) $r->akt_fail])->toArray();
        $rpinSuccessByDate = $data->mapWithKeys(fn($r) => [$r->report_date->format('Y-m-d') => (int) $r->rpin_success])->toArray();
        $rpinFailByDate    = $data->mapWithKeys(fn($r) => [$r->report_date->format('Y-m-d') => (int) $r->rpin_fail])->toArray();
        $incomingByDate    = $data->mapWithKeys(fn($r) => [$r->report_date->format('Y-m-d') => (int) $r->total_incoming])->toArray();
        $outgoingByDate    = $data->mapWithKeys(fn($r) => [$r->report_date->format('Y-m-d') => (int) $r->total_outgoing])->toArray();

        return Grid::make([
            Column::make([Divider::make()])->columnSpan(12),

            Column::make([
                Alert::make(type: 'info')
                    ->content("Data periode: <strong>{$period}</strong>"),
            ])->columnSpan(12),

            // ValueMetrics
            Column::make([
                ValueMetric::make("Total Success ({$period})")
                    ->value(number_format($totalSuccess)),
            ])->columnSpan(3),

            Column::make([
                ValueMetric::make("Total Fail ({$period})")
                    ->value(number_format($totalFail)),
            ])->columnSpan(3),

            Column::make([
                ValueMetric::make("Total Incoming ({$period})")
                    ->value(number_format($totalIncoming)),
            ])->columnSpan(3),

            Column::make([
                ValueMetric::make("Total Outgoing ({$period})")
                    ->value(number_format($totalOutgoing)),
            ])->columnSpan(3),

            // Line Chart: AKT
            Column::make([
                LineChartMetric::make('AKT per Hari')
                    ->series(SeriesItem::make('AKT Success', $aktSuccessByDate)->line())
                    ->series(SeriesItem::make('AKT Fail',    $aktFailByDate)->line()),
            ])->columnSpan(6),

            // Line Chart: RPIN
            Column::make([
                LineChartMetric::make('RPIN per Hari')
                    ->series(SeriesItem::make('RPIN Success', $rpinSuccessByDate)->line())
                    ->series(SeriesItem::make('RPIN Fail',    $rpinFailByDate)->line()),
            ])->columnSpan(6),

            // Line Chart: Incoming vs Outgoing
            Column::make([
                LineChartMetric::make('Incoming vs Outgoing per Hari')
                    ->series(SeriesItem::make('Incoming', $incomingByDate)->line())
                    ->series(SeriesItem::make('Outgoing', $outgoingByDate)->line()),
            ])->columnSpan(12),

            // Donut: AKT vs RPIN
            Column::make([
                DonutChartMetric::make('Distribusi AKT vs RPIN')
                    ->values(['AKT' => $totalAkt, 'RPIN' => $totalRpin])
                    ->decimals(0),
            ])->columnSpan(6),

            // Donut: Incoming vs Outgoing
            Column::make([
                DonutChartMetric::make('Incoming vs Outgoing')
                    ->values(['Incoming' => $totalIncoming, 'Outgoing' => $totalOutgoing])
                    ->decimals(0),
            ])->columnSpan(6),
        ]);
    }
}
