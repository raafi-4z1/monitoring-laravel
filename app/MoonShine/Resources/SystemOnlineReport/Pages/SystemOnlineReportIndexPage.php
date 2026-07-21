<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\SystemOnlineReport\Pages;

use App\MoonShine\Concerns\BuildsHourlyOrDailyChart;
use App\Models\SystemOnlineReport;
use App\MoonShine\Resources\SystemOnlineReport\SystemOnlineReportResource;
use Carbon\Carbon;
use Illuminate\Support\Collection;
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
use MoonShine\UI\Fields\DateRange;
use MoonShine\UI\Fields\Preview;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Text;
use Throwable;

/**
 * @extends IndexPage<SystemOnlineReportResource>
 */
class SystemOnlineReportIndexPage extends IndexPage
{
    use BuildsHourlyOrDailyChart;

    protected bool $isLazy = true;

    protected function assets(): array
    {
        return [...LineChartMetric::make('')->getAssets()];
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
            Text::make('Service', 'service_name')->sortable(),
            Preview::make('Response Time Avg (ms)', 'response_time_avg_ms')
                ->changeFill(fn($i) => number_format($i->response_time_avg_ms, 2))
                ->sortable(),
        ];
    }

    protected function topLeftButtons(): ListOf
    {
        return new ListOf(ActionButtonContract::class, [
            CreateButton::for(label: 'Fetch Manual', resource: $this->getResource()),
        ]);
    }

    /**
     * @return list<FieldContract>
     */
    protected function filters(): iterable
    {
        return [
            DateRange::make('Tanggal', 'trx_date'),
            Select::make('Service', 'service_name')
                ->options(['SVC Service' => 'SVC Service', 'Login' => 'Login'])
                ->nullable(),
        ];
    }

    protected function modifyListComponent(ComponentContract $component): ComponentContract
    {
        return $component
            ->columnSelection()
            ->sticky()
            ->stickyButtons()
            ->async(events: [
                AlpineJs::event(JsEvent::FRAGMENT_UPDATED, 'system-online-charts'),
            ])
            ->topRight(function () {
                return [
                    Div::make([
                        Select::make('Per page')
                            ->onChangeMethod('changePerPage')
                            ->options($this->getResource()->perPageValues())
                            ->withoutWrapper()
                            ->native()
                            ->setValue($this->getResource()->getItemsPerPage()),
                    ]),
                ];
            });
    }

    #[AsyncMethod]
    public function changePerPage(): JsonResponse
    {
        $perPage = request()->integer('value');
        if ($perPage > 0) {
            session(['systemOnlinePerPage' => $perPage]);
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
        [2 => $period, 3 => $data, 4 => $serviceFilter] = $this->getFilteredData();

        return [
            $this->lastUpdateAlert(),
            ...parent::mainLayer(),
            Fragment::make([$this->buildCharts($data, $period, $serviceFilter)])
                ->name('system-online-charts')
                ->withQueryParams(),
        ];
    }

    protected function lastUpdateAlert(): Alert
    {
        $latest = SystemOnlineReport::latest('trx_date')->latest('trx_hour')->first();

        return $latest
            ? Alert::make(type: 'info')
                ->content("Data terakhir: <strong>{$latest->trx_date->format('d M Y')} " . sprintf('%02d', $latest->trx_hour) . ":00</strong> — diupdate: {$latest->updated_at->format('d M Y H:i')}")
            : Alert::make(type: 'warning')
                ->content('Belum ada data. Gunakan Fetch Manual.');
    }

    /**
     * @return array{0: string, 1: string, 2: string, 3: Collection, 4: string|null}
     */
    private function getFilteredData(): array
    {
        $fromInput    = request()->input('_data.filter.trx_date.from')
            ?? request()->input('filter.trx_date.from');
        $toInput      = request()->input('_data.filter.trx_date.to')
            ?? request()->input('filter.trx_date.to');
        $serviceInput = request()->input('_data.filter.service_name')
            ?? request()->input('filter.service_name');

        $isDefault = empty($fromInput) && empty($toInput);
        $dateFrom  = !empty($fromInput) ? substr($fromInput, 0, 10) : Carbon::yesterday()->format('Y-m-d');
        $dateTo    = !empty($toInput)   ? substr($toInput, 0, 10)   : ($isDefault ? Carbon::yesterday()->format('Y-m-d') : Carbon::now()->format('Y-m-d'));

        $period = Carbon::parse($dateFrom)->format('d M Y') . ' - ' . Carbon::parse($dateTo)->format('d M Y');

        $data = SystemOnlineReport::query()
            ->whereBetween('trx_date', [$dateFrom, $dateTo])
            ->orderBy('trx_date')
            ->orderBy('trx_hour')
            ->get();

        return [$dateFrom, $dateTo, $period, $data, $serviceInput ?: null];
    }

    private function buildCharts(Collection $data, string $period, ?string $serviceFilter): Grid
    {
        if ($data->isEmpty()) {
            return Grid::make([
                Column::make([Divider::make()])->columnSpan(12),
                Column::make([Alert::make(type: 'info')->content('Tidak ada data untuk periode ini.')])->columnSpan(12),
            ]);
        }

        $filtered = $serviceFilter !== null
            ? $data->where('service_name', $serviceFilter)
            : $data;

        if ($filtered->isEmpty()) {
            return Grid::make([
                Column::make([Divider::make()])->columnSpan(12),
                Column::make([Alert::make(type: 'info')->content('Tidak ada data untuk filter ini.')])->columnSpan(12),
            ]);
        }

        $sorted = $filtered->sortBy(fn($r) => $r->trx_date->format('Y-m-d') . sprintf('%02d', $r->trx_hour))->values();

        // Granularitas otomatis: per jam untuk rentang sempit, per hari (rata-rata) untuk
        // rentang lebar. Dihitung dari $data (bukan $filtered) supaya keputusannya
        // berdasarkan periode yang sedang dilihat, bukan ikut menyempit saat difilter per service.
        $g     = $this->chartGranularity($data, fn($r) => $r->trx_date, fn($r) => $r->trx_hour);
        $label = $g['label'];

        // Zero-fill: label sumbu-X dibangun sekali dari SEMUA data yang difilter, lalu
        // tiap service di-map ke label yang sama (default 0 kalau slot itu tidak ada
        // datanya) — supaya antar service_name selalu selaras di chart.
        $labels   = $sorted->map($label)->unique()->values()->all();
        $services = $sorted->pluck('service_name')->unique()->sort()->values();

        $chart        = LineChartMetric::make('Response Time Avg (ms) per ' . ($g['isDaily'] ? 'Hari' : 'Jam'));
        $valueMetrics = [];

        foreach ($services as $service) {
            $rows = $sorted->where('service_name', $service)->values();

            if ($g['isDaily']) {
                $byLabel = $rows->groupBy($label)->map(fn($grp) => $grp->avg('response_time_avg_ms'));
                $series  = collect($labels)->mapWithKeys(fn($lbl) => [$lbl => round($byLabel->get($lbl) ?? 0, 2)])->toArray();
            } else {
                $byLabel = $rows->keyBy($label);
                $series  = collect($labels)->mapWithKeys(
                    fn($lbl) => [$lbl => round($byLabel->get($lbl)?->response_time_avg_ms ?? 0, 2)]
                )->toArray();
            }

            $chart->series(SeriesItem::make($service, $series)->line());

            $valueMetrics[] = Column::make([
                ValueMetric::make("Avg {$service} (ms)")->value(number_format($rows->avg('response_time_avg_ms'), 2)),
            ])->columnSpan(6);
        }

        return Grid::make(array_filter([
            Column::make([Divider::make()])->columnSpan(12),
            Column::make([Alert::make(type: 'info')->content("Periode: <strong>{$period}</strong>")])->columnSpan(12),
            ...$valueMetrics,
            Column::make([$chart])->columnSpan(12),
        ]));
    }
}
