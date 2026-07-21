<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\TrxPbiSettlementReport\Pages;

use App\MoonShine\Concerns\BuildsHourlyOrDailyChart;
use App\Models\TrxPbiSettlementReport;
use App\MoonShine\Resources\TrxPbiSettlementReport\TrxPbiSettlementReportResource;
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
use MoonShine\UI\Fields\DateRange;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Preview;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Text;
use Throwable;

/**
 * @extends IndexPage<TrxPbiSettlementReportResource>
 */
class TrxPbiSettlementReportIndexPage extends IndexPage
{
    use BuildsHourlyOrDailyChart;

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
            Preview::make('Tanggal', 'trx_date')
                ->changeFill(fn($item) => $item->trx_date?->format('Y-m-d'))
                ->sortable(fn($q, $_c, $d) => $q->orderBy('trx_date', $d)->orderBy('trx_hour', $d)),
            Preview::make('Jam', 'trx_hour')
                ->changeFill(fn($item) => sprintf('%02d:00', $item->trx_hour))
                ->sortable(fn($query, $_col, $dir) => $query->orderBy('trx_date', $dir)->orderBy('trx_hour', $dir)),
            Text::make('Mata Uang', 'trx_currency')->sortable(),
            Preview::make('Total Transaksi', 'trx_count')
                ->changeFill(fn($item) => number_format($item->trx_count))
                ->sortable(),
            Preview::make('Success', 'success_count')
                ->changeFill(fn($item) => number_format($item->success_count))
                ->sortable(),
            Preview::make('Total Nominal', 'trx_amount')
                ->changeFill(fn($item) => number_format($item->trx_amount, 2, '.', ','))
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
        $currencyOptions = TrxPbiSettlementReport::distinct()
            ->orderBy('trx_currency')
            ->pluck('trx_currency', 'trx_currency')
            ->toArray();

        return [
            DateRange::make('Tanggal', 'trx_date'),
            Number::make('Jam (0-23)', 'trx_hour'),
            Select::make('Mata Uang', 'trx_currency')
                ->options($currencyOptions)
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
                AlpineJs::event(JsEvent::FRAGMENT_UPDATED, 'trx-pbi-settlement-charts'),
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
            session(['trxPbiSettlementPerPage' => $perPage]);
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
            ->name('trx-pbi-settlement-charts')
            ->withQueryParams(),
        ];
    }

    private function lastUpdateAlert(): Alert
    {
        $latest = TrxPbiSettlementReport::latest('trx_date')->latest('trx_hour')->first();

        return $latest
            ? Alert::make(type: 'info')
                ->content("Data terakhir: <strong>{$latest->trx_date->format('d M Y')} {$latest->trx_hour}:00</strong> — diupdate: {$latest->updated_at->format('d M Y H:i')}")
            : Alert::make(type: 'warning')
                ->content('Belum ada data. Gunakan Fetch Manual.');
    }

    private function getFilteredData(): array
    {
        $fromInput = request()->input('_data.filter.trx_date.from')
                 ?? request()->input('filter.trx_date.from');
        $toInput   = request()->input('_data.filter.trx_date.to')
                 ?? request()->input('filter.trx_date.to');

        $currencyFilter = request()->input('_data.filter.trx_currency')
                       ?? request()->input('filter.trx_currency');

        $hourFilter = request()->input('_data.filter.trx_hour')
                   ?? request()->input('filter.trx_hour');

        $isDefault = empty($fromInput) && empty($toInput);

        $dateFrom = !empty($fromInput) ? substr($fromInput, 0, 10) : Carbon::yesterday()->format('Y-m-d');
        $dateTo   = !empty($toInput)
            ? substr($toInput, 0, 10)
            : ($isDefault ? Carbon::yesterday()->format('Y-m-d') : Carbon::now()->format('Y-m-d'));

        $period = Carbon::parse($dateFrom)->format('d M Y')
                . ' - '
                . Carbon::parse($dateTo)->format('d M Y');

        $query = TrxPbiSettlementReport::query()
            ->whereBetween('trx_date', [$dateFrom, $dateTo])
            ->orderBy('trx_date')
            ->orderBy('trx_hour')
            ->orderBy('trx_currency');

        if (!empty($currencyFilter)) {
            $query->where('trx_currency', $currencyFilter);
        }

        if ($hourFilter !== null && $hourFilter !== '') {
            $query->where('trx_hour', (int) $hourFilter);
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

        $totalTrx = $data->sum('trx_count');
        $totalNom = $data->sum('trx_amount');

        $byCurrency = $data->groupBy('trx_currency');

        $sorted = $data->sortBy(fn($r) => $r->trx_date->format('Y-m-d') . sprintf('%02d', $r->trx_hour))->values();

        // Granularitas otomatis: per jam untuk rentang sempit, per hari (jumlah) untuk
        // rentang lebar — supaya chart tidak terlalu padat/noisy saat difilter berhari-hari.
        $g      = $this->chartGranularity($sorted, fn($r) => $r->trx_date, fn($r) => $r->trx_hour);
        $label  = $g['label'];
        $labels = $sorted->map($label)->unique()->values()->all();

        $unit = $g['isDaily'] ? 'Hari' : 'Jam';

        $trxChart = LineChartMetric::make("Total Transaksi per {$unit} per Mata Uang");
        $nomChart = LineChartMetric::make("Total Nominal per {$unit} per Mata Uang");

        foreach ($byCurrency as $currency => $rows) {
            if ($g['isDaily']) {
                $byLabel   = $rows->groupBy($label);
                $trxSeries = collect($labels)->mapWithKeys(fn($lbl) => [$lbl => (int) ($byLabel->get($lbl)?->sum('trx_count') ?? 0)])->toArray();
                $nomSeries = collect($labels)->mapWithKeys(fn($lbl) => [$lbl => round((float) ($byLabel->get($lbl)?->sum('trx_amount') ?? 0), 2)])->toArray();
            } else {
                $byLabel   = $rows->keyBy($label);
                $trxSeries = collect($labels)->mapWithKeys(fn($lbl) => [$lbl => (int) ($byLabel->get($lbl)?->trx_count ?? 0)])->toArray();
                $nomSeries = collect($labels)->mapWithKeys(fn($lbl) => [$lbl => round((float) ($byLabel->get($lbl)?->trx_amount ?? 0), 2)])->toArray();
            }

            $trxChart->series(SeriesItem::make($currency, $trxSeries)->line());
            $nomChart->series(SeriesItem::make($currency, $nomSeries)->line());
        }

        $donutTrx = $byCurrency->map(fn($rows) => (int) $rows->sum('trx_count'))->toArray();
        $donutNom = $byCurrency->map(fn($rows) => round((float) $rows->sum('trx_amount'), 2))->toArray();

        return Grid::make([
            Column::make([Divider::make()])->columnSpan(12),

            Column::make([
                Alert::make(type: 'info')
                    ->content("Data periode: <strong>{$period}</strong>"),
            ])->columnSpan(12),

            Column::make([
                ValueMetric::make('Total Transaksi')
                    ->value(number_format($totalTrx)),
            ])->columnSpan(6),

            Column::make([
                ValueMetric::make('Total Nominal')
                    ->value(number_format($totalNom, 0)),
            ])->columnSpan(6),

            Column::make([$trxChart])->columnSpan(12),
            Column::make([$nomChart])->columnSpan(12),

            Column::make([
                DonutChartMetric::make('Distribusi Transaksi per Mata Uang')
                    ->values($donutTrx)
                    ->decimals(0),
            ])->columnSpan(6),

            Column::make([
                DonutChartMetric::make('Distribusi Nominal per Mata Uang')
                    ->values($donutNom)
                    ->decimals(2),
            ])->columnSpan(6),
        ]);
    }
}
