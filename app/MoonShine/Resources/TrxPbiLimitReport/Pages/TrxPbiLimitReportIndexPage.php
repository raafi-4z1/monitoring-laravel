<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\TrxPbiLimitReport\Pages;

use App\Models\TrxPbiLimitReport;
use App\MoonShine\Resources\TrxPbiLimitReport\TrxPbiLimitReportResource;
use Carbon\Carbon;
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
use MoonShine\UI\Fields\Preview;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Text;
use Throwable;

/**
 * @extends IndexPage<TrxPbiLimitReportResource>
 */
class TrxPbiLimitReportIndexPage extends IndexPage
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
            Date::make('Jam', 'report_hour')
                ->sortable()
                ->withTime()
                ->format('Y-m-d H:i'),
            Text::make('CCY2', 'ccy2')->sortable(),
            Preview::make('Total Transaksi', 'total_trx')
                ->changeFill(fn($item) => number_format($item->total_trx))
                ->sortable(),
            Preview::make('Total Nominal', 'total_nominal')
                ->changeFill(fn($item) => number_format($item->total_nominal, 2, '.', ','))
                ->sortable(),
            Preview::make('NominalEqUSD', 'total_nominal_eq_usd')
                ->changeFill(fn($item) => number_format($item->total_nominal_eq_usd, 2, '.', ','))
                ->sortable(),
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
        $ccyOptions = TrxPbiLimitReport::distinct()
            ->orderBy('ccy2')
            ->pluck('ccy2', 'ccy2')
            ->toArray();

        return [
            DateRange::make('Tanggal', 'report_hour'),
            Select::make('CCY2', 'ccy2')
                ->options($ccyOptions)
                ->nullable(),
        ];
    }

    /**
     * @param TableBuilder $component
     */
    protected function modifyListComponent(ComponentContract $component): ComponentContract
    {
        return $component
            ->columnSelection()
            ->sticky()
            ->stickyButtons()
            ->async(events: [
                AlpineJs::event(JsEvent::FRAGMENT_UPDATED, 'trx-pbi-limit-charts'),
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
            session(['trxPbiLimitPerPage' => $perPage]);
        }

        return JsonResponse::make()->events([
            AlpineJs::event(JsEvent::TABLE_UPDATED, $this->getListComponentName()),
        ]);
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
            ->name('trx-pbi-limit-charts')
            ->withQueryParams(),
        ];
    }

    private function lastUpdateAlert(): Alert
    {
        $latest = TrxPbiLimitReport::latest('report_hour')->first();

        return $latest
            ? Alert::make(type: 'info')
                ->content("Data terakhir: <strong>{$latest->report_hour->format('d M Y H:i')}</strong> — diupdate: {$latest->updated_at->format('d M Y H:i')}")
            : Alert::make(type: 'warning')
                ->content('Belum ada data. Gunakan Fetch Manual.');
    }

    private function getFilteredData(): array
    {
        $fromInput = request()->input('_data.filter.report_hour.from')
                 ?? request()->input('filter.report_hour.from');
        $toInput   = request()->input('_data.filter.report_hour.to')
                 ?? request()->input('filter.report_hour.to');

        $ccy2Filter = request()->input('_data.filter.ccy2')
                   ?? request()->input('filter.ccy2');

        $isDefault = empty($fromInput) && empty($toInput);

        $dateFrom = !empty($fromInput) ? substr($fromInput, 0, 10) : Carbon::yesterday()->format('Y-m-d');
        $dateTo   = !empty($toInput)
            ? substr($toInput, 0, 10)
            : ($isDefault ? Carbon::yesterday()->format('Y-m-d') : Carbon::now()->format('Y-m-d'));

        $period = Carbon::parse($dateFrom)->format('d M Y')
                . ' - '
                . Carbon::parse($dateTo)->format('d M Y');

        $query = TrxPbiLimitReport::query()
            ->whereBetween('report_hour', [
                $dateFrom . ' 00:00:00',
                $dateTo . ' 23:59:59',
            ])
            ->orderBy('report_hour')
            ->orderBy('ccy2');

        if (!empty($ccy2Filter)) {
            $query->where('ccy2', $ccy2Filter);
        }

        $data = $query->get();

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

        $totalTrx = $data->sum('total_trx');
        $totalUsd = $data->sum('total_nominal_eq_usd');
        $totalNom = $data->sum('total_nominal');

        $byCcy2 = $data->groupBy('ccy2');

        $allHours = $data->pluck('report_hour')
            ->map(fn($h) => $h->format('Y-m-d H:i:s'))
            ->unique()->sort()->values()->toArray();

        $isSingleDay = $data->pluck('report_hour')
            ->map(fn($h) => $h->format('Y-m-d'))->unique()->count() === 1;
        $label = $isSingleDay
            ? fn(string $h) => Carbon::parse($h)->format('H:i')
            : fn(string $h) => Carbon::parse($h)->format('d/m H:i');

        // LineChart: Total Transaksi per jam per CCY2
        $trxChart = LineChartMetric::make('Total Transaksi per Jam per CCY2');
        foreach ($byCcy2 as $ccy2 => $rows) {
            $byHour     = $rows->keyBy(fn($r) => $r->report_hour->format('Y-m-d H:i:s'));
            $seriesData = collect($allHours)->mapWithKeys(
                fn($h) => [$label($h) => (int) ($byHour[$h]->total_trx ?? 0)]
            )->toArray();
            $trxChart->series(SeriesItem::make($ccy2, $seriesData)->line());
        }

        // LineChart: NominalEqUSD per jam per CCY2
        $usdChart = LineChartMetric::make('Total NominalEqUSD per Jam per CCY2');
        foreach ($byCcy2 as $ccy2 => $rows) {
            $byHour     = $rows->keyBy(fn($r) => $r->report_hour->format('Y-m-d H:i:s'));
            $seriesData = collect($allHours)->mapWithKeys(
                fn($h) => [$label($h) => round((float) ($byHour[$h]->total_nominal_eq_usd ?? 0), 2)]
            )->toArray();
            $usdChart->series(SeriesItem::make($ccy2, $seriesData)->line());
        }

        // Donut: Distribusi transaksi per CCY2 (total periode)
        $donutTrx = $byCcy2->map(fn($rows) => (int) $rows->sum('total_trx'))->toArray();
        $donutUsd = $byCcy2->map(fn($rows) => round((float) $rows->sum('total_nominal_eq_usd'), 2))->toArray();

        return Grid::make([
            Column::make([Divider::make()])->columnSpan(12),

            Column::make([
                Alert::make(type: 'info')
                    ->content("Data periode: <strong>{$period}</strong>"),
            ])->columnSpan(12),

            // Value Metrics
            Column::make([
                ValueMetric::make("Total Transaksi")
                    ->value(number_format($totalTrx)),
            ])->columnSpan(4),

            Column::make([
                ValueMetric::make("Total NominalEqUSD")
                    ->value(number_format($totalUsd, 2)),
            ])->columnSpan(4),

            Column::make([
                ValueMetric::make("Total Nominal")
                    ->value(number_format($totalNom, 0)),
            ])->columnSpan(4),

            // Line Charts per jam
            Column::make([$trxChart])->columnSpan(12),
            Column::make([$usdChart])->columnSpan(12),

            // Donut distribusi per CCY2
            Column::make([
                DonutChartMetric::make('Distribusi Transaksi per CCY2')
                    ->values($donutTrx)
                    ->decimals(0),
            ])->columnSpan(6),

            Column::make([
                DonutChartMetric::make('Distribusi NominalEqUSD per CCY2')
                    ->values($donutUsd)
                    ->decimals(2),
            ])->columnSpan(6),
        ]);
    }
}
