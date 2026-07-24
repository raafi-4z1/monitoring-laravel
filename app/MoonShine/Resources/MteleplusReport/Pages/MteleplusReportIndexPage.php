<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\MteleplusReport\Pages;

use App\MoonShine\Concerns\BuildsHourlyOrDailyChart;
use App\Models\MteleplusReport;
use App\MoonShine\Resources\MteleplusReport\MteleplusReportResource;
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
                ->changeFill(fn($i) => $i->trx_date?->format('Y-m-d') ?? '-')
                ->sortable(fn($q, $_c, $d) => $q->orderBy('trx_date', $d)->orderBy('trx_hour', $d)),
            Preview::make('Jam', 'trx_hour')
                ->changeFill(fn($i) => sprintf('%02d:00', $i->trx_hour))
                ->sortable(fn($q, $_c, $d) => $q->orderBy('trx_date', $d)->orderBy('trx_hour', $d)),
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
            DateRange::make('Tanggal', 'trx_date'),
        ];
    }

    /**
     * @return list<QueryTag>
     */
    protected function queryTags(): array
    {
        return [];
    }

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
        [$dateFrom, $dateTo, $period, $data, $isDefault] = $this->getFilteredData();

        $alerts = [$this->lastUpdateAlert()];

        return [
            ...$alerts,
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
        $latest = MteleplusReport::latest('trx_date')->latest('trx_hour')->first();

        return $latest
            ? Alert::make(type: 'info')
                ->content("Data terakhir: <strong>{$latest->trx_date->format('d M Y')} " . sprintf('%02d', $latest->trx_hour) . ":00</strong> — diupdate: {$latest->updated_at->format('d M Y H:i')}")
            : Alert::make(type: 'warning')
                ->content('Belum ada data. Gunakan Fetch Manual.');
    }

    /**
     * @return array{0: string, 1: string, 2: string, 3: \Illuminate\Support\Collection, 4: bool}
     */
    private function getFilteredData(): array
    {
        $fromInput = request()->input('_data.filter.trx_date.from')
            ?? request()->input('filter.trx_date.from');
        $toInput   = request()->input('_data.filter.trx_date.to')
            ?? request()->input('filter.trx_date.to');

        $isDefault = empty($fromInput) && empty($toInput);

        $dateFrom = !empty($fromInput) ? substr($fromInput, 0, 10) : Carbon::yesterday()->format('Y-m-d');
        $dateTo   = !empty($toInput)
            ? substr($toInput, 0, 10)
            : ($isDefault ? Carbon::yesterday()->format('Y-m-d') : Carbon::now()->format('Y-m-d'));

        $period = Carbon::parse($dateFrom)->format('d M Y')
                . ' - '
                . Carbon::parse($dateTo)->format('d M Y');

        $data = MteleplusReport::query()
            ->whereBetween('trx_date', [$dateFrom, $dateTo])
            ->orderBy('trx_date')
            ->orderBy('trx_hour')
            ->get();

        return [$dateFrom, $dateTo, $period, $data, $isDefault];
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

        // Granularitas otomatis: per jam untuk rentang sempit, per hari untuk rentang
        // lebar — supaya chart tidak terlalu padat/noisy saat difilter berhari-hari.
        $sorted = $data->sortBy(fn($r) => $r->trx_date->format('Y-m-d') . sprintf('%02d', $r->trx_hour))->values();
        $g      = $this->chartGranularity($sorted, fn($r) => $r->trx_date, fn($r) => $r->trx_hour);
        $label  = $g['label'];
        $unit   = $g['isDaily'] ? 'Hari' : 'Jam';

        // Zero-fill: kumpulkan semua slot (jam/hari) yang ada, isi slot kosong dengan 0
        $labels = $sorted->map($label)->unique()->values()->all();

        if ($g['isDaily']) {
            $byLabel = $sorted->groupBy($label)->map(fn($grp) => [
                'akt_success'     => $grp->sum('akt_success'),
                'akt_fail'        => $grp->sum('akt_fail'),
                'rpin_success'    => $grp->sum('rpin_success'),
                'rpin_fail'       => $grp->sum('rpin_fail'),
                'total_incoming'  => $grp->sum('total_incoming'),
                'total_outgoing'  => $grp->sum('total_outgoing'),
            ]);

            $aktSuccessByHour  = collect($labels)->mapWithKeys(fn($lbl) => [$lbl => (int) ($byLabel->get($lbl, [])['akt_success'] ?? 0)])->toArray();
            $aktFailByHour     = collect($labels)->mapWithKeys(fn($lbl) => [$lbl => (int) ($byLabel->get($lbl, [])['akt_fail'] ?? 0)])->toArray();
            $rpinSuccessByHour = collect($labels)->mapWithKeys(fn($lbl) => [$lbl => (int) ($byLabel->get($lbl, [])['rpin_success'] ?? 0)])->toArray();
            $rpinFailByHour    = collect($labels)->mapWithKeys(fn($lbl) => [$lbl => (int) ($byLabel->get($lbl, [])['rpin_fail'] ?? 0)])->toArray();
            $incomingByHour    = collect($labels)->mapWithKeys(fn($lbl) => [$lbl => (int) ($byLabel->get($lbl, [])['total_incoming'] ?? 0)])->toArray();
            $outgoingByHour    = collect($labels)->mapWithKeys(fn($lbl) => [$lbl => (int) ($byLabel->get($lbl, [])['total_outgoing'] ?? 0)])->toArray();
        } else {
            $byLabel = $sorted->keyBy($label);

            $aktSuccessByHour  = collect($labels)->mapWithKeys(fn($lbl) => [$lbl => (int) ($byLabel->get($lbl)?->akt_success  ?? 0)])->toArray();
            $aktFailByHour     = collect($labels)->mapWithKeys(fn($lbl) => [$lbl => (int) ($byLabel->get($lbl)?->akt_fail     ?? 0)])->toArray();
            $rpinSuccessByHour = collect($labels)->mapWithKeys(fn($lbl) => [$lbl => (int) ($byLabel->get($lbl)?->rpin_success ?? 0)])->toArray();
            $rpinFailByHour    = collect($labels)->mapWithKeys(fn($lbl) => [$lbl => (int) ($byLabel->get($lbl)?->rpin_fail    ?? 0)])->toArray();
            $incomingByHour    = collect($labels)->mapWithKeys(fn($lbl) => [$lbl => (int) ($byLabel->get($lbl)?->total_incoming ?? 0)])->toArray();
            $outgoingByHour    = collect($labels)->mapWithKeys(fn($lbl) => [$lbl => (int) ($byLabel->get($lbl)?->total_outgoing ?? 0)])->toArray();
        }

        return Grid::make([
            Column::make([Divider::make()])->columnSpan(12),

            Column::make([
                Alert::make(type: 'info')
                    ->content("Data periode: <strong>{$period}</strong>"),
            ])->columnSpan(12),

            Column::make([
                ValueMetric::make('Total Success')
                    ->value(number_format($totalSuccess)),
            ])->columnSpan(3),

            Column::make([
                ValueMetric::make('Total Fail')
                    ->value(number_format($totalFail)),
            ])->columnSpan(3),

            Column::make([
                ValueMetric::make('Total Incoming')
                    ->value(number_format($totalIncoming)),
            ])->columnSpan(3),

            Column::make([
                ValueMetric::make('Total Outgoing')
                    ->value(number_format($totalOutgoing)),
            ])->columnSpan(3),

            Column::make([
                LineChartMetric::make("AKT per {$unit}")
                    ->series(SeriesItem::make('AKT Success', $aktSuccessByHour)->line())
                    ->series(SeriesItem::make('AKT Fail',    $aktFailByHour)->line()),
            ])->columnSpan(6),

            Column::make([
                LineChartMetric::make("RPIN per {$unit}")
                    ->series(SeriesItem::make('RPIN Success', $rpinSuccessByHour)->line())
                    ->series(SeriesItem::make('RPIN Fail',    $rpinFailByHour)->line()),
            ])->columnSpan(6),

            Column::make([
                LineChartMetric::make("Incoming vs Outgoing per {$unit}")
                    ->series(SeriesItem::make('Incoming', $incomingByHour)->line())
                    ->series(SeriesItem::make('Outgoing', $outgoingByHour)->line()),
            ])->columnSpan(12),

            Column::make([
                DonutChartMetric::make('Distribusi AKT vs RPIN')
                    ->values(['AKT' => $totalAkt, 'RPIN' => $totalRpin])
                    ->decimals(0),
            ])->columnSpan(6),

            Column::make([
                DonutChartMetric::make('Incoming vs Outgoing')
                    ->values(['Incoming' => $totalIncoming, 'Outgoing' => $totalOutgoing])
                    ->decimals(0),
            ])->columnSpan(6),
        ]);
    }
}
