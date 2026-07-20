<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\WicDbMetricReport\Pages;

use App\MoonShine\Concerns\BuildsHourlyOrDailyChart;
use App\Models\WicDbMetricReport;
use App\MoonShine\Resources\WicDbMetricReport\WicDbMetricReportResource;
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
 * @extends IndexPage<WicDbMetricReportResource>
 */
class WicDbMetricReportIndexPage extends IndexPage
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
                ->sortable('trx_date'),
            Preview::make('Jam', 'trx_hour')
                ->changeFill(fn($i) => sprintf('%02d:00', $i->trx_hour))
                ->sortable(fn($q, $_c, $d) => $q->orderBy('trx_date', $d)->orderBy('trx_hour', $d)),
            Text::make('Tipe', 'metric_type')->sortable(),
            Preview::make('Disk', 'disk_path')
                ->changeFill(fn($i) => $i->disk_path ?: '-'),
            Preview::make('Max %', 'max_pct')
                ->changeFill(fn($i) => $i->max_pct !== null ? number_format($i->max_pct * 100, 2) . '%' : '-')
                ->sortable(),
            Preview::make('Min %', 'min_pct')
                ->changeFill(fn($i) => $i->min_pct !== null ? number_format($i->min_pct * 100, 2) . '%' : '-')
                ->sortable(),
            Preview::make('Avg %', 'avg_pct')
                ->changeFill(fn($i) => $i->avg_pct !== null ? number_format($i->avg_pct * 100, 2) . '%' : '-')
                ->sortable(),
            Preview::make('Last %', 'last_pct')
                ->changeFill(fn($i) => $i->last_pct !== null ? number_format($i->last_pct * 100, 2) . '%' : '-'),
            Preview::make('Used', 'last_used_bytes')
                ->changeFill(fn($i) => $i->last_used_bytes ? $this->formatBytes($i->last_used_bytes) : '-'),
            Preview::make('Total', 'last_total_bytes')
                ->changeFill(fn($i) => $i->last_total_bytes ? $this->formatBytes($i->last_total_bytes) : '-'),
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
            Select::make('Tipe Metrik', 'metric_type')
                ->options(['cpu' => 'CPU', 'memory' => 'Memory', 'disk' => 'Disk'])
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
                AlpineJs::event(JsEvent::FRAGMENT_UPDATED, 'wic-db-metric-charts'),
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
            session(['wicDbMetricPerPage' => $perPage]);
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
        [2 => $period, 3 => $data, 4 => $metricTypeFilter] = $this->getFilteredData();

        return [
            $this->lastUpdateAlert(),
            ...parent::mainLayer(),
            Fragment::make([$this->buildCharts($data, $period, $metricTypeFilter)])
                ->name('wic-db-metric-charts')
                ->withQueryParams(),
        ];
    }

    protected function lastUpdateAlert(): Alert
    {
        $latest = WicDbMetricReport::latest('trx_date')->latest('trx_hour')->first();
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
        $fromInput       = request()->input('_data.filter.trx_date.from')
            ?? request()->input('filter.trx_date.from');
        $toInput         = request()->input('_data.filter.trx_date.to')
            ?? request()->input('filter.trx_date.to');
        $metricTypeInput = request()->input('_data.filter.metric_type')
            ?? request()->input('filter.metric_type');

        $isDefault = empty($fromInput) && empty($toInput);
        $dateFrom  = !empty($fromInput) ? substr($fromInput, 0, 10) : Carbon::yesterday()->format('Y-m-d');
        $dateTo    = !empty($toInput)   ? substr($toInput, 0, 10)   : ($isDefault ? Carbon::yesterday()->format('Y-m-d') : Carbon::now()->format('Y-m-d'));

        $period = Carbon::parse($dateFrom)->format('d M Y') . ' - ' . Carbon::parse($dateTo)->format('d M Y');

        $data = WicDbMetricReport::query()
            ->whereBetween('trx_date', [$dateFrom, $dateTo])
            ->orderBy('trx_date')
            ->orderBy('trx_hour')
            ->get();

        return [$dateFrom, $dateTo, $period, $data, $metricTypeInput ?: null];
    }

    private function buildCharts(Collection $data, string $period, ?string $metricTypeFilter): Grid
    {
        if ($data->isEmpty()) {
            return Grid::make([
                Column::make([Divider::make()])->columnSpan(12),
                Column::make([Alert::make(type: 'info')->content('Tidak ada data untuk periode ini.')])->columnSpan(12),
            ]);
        }

        $cpuData  = $data->where('metric_type', 'cpu')->sortBy(fn($r) => $r->trx_date->format('Y-m-d') . sprintf('%02d', $r->trx_hour))->values();
        $memData  = $data->where('metric_type', 'memory')->sortBy(fn($r) => $r->trx_date->format('Y-m-d') . sprintf('%02d', $r->trx_hour))->values();
        $diskData = $data->where('metric_type', 'disk')->sortBy(fn($r) => $r->trx_date->format('Y-m-d') . sprintf('%02d', $r->trx_hour))->values();

        // Granularitas otomatis: per jam untuk rentang sempit, per hari untuk rentang
        // lebar — supaya chart tidak terlalu padat/noisy saat difilter berhari-hari.
        $sorted = $data->sortBy(fn($r) => $r->trx_date->format('Y-m-d') . sprintf('%02d', $r->trx_hour))->values();
        $g      = $this->chartGranularity($sorted, fn($r) => $r->trx_date, fn($r) => $r->trx_hour);
        $label  = $g['label'];
        $unit   = $g['isDaily'] ? 'Hari' : 'Jam';

        $showCpu  = $metricTypeFilter === null || $metricTypeFilter === 'cpu';
        $showMem  = $metricTypeFilter === null || $metricTypeFilter === 'memory';
        $showDisk = $metricTypeFilter === null || $metricTypeFilter === 'disk';

        // Max/Avg/Min: per hari, max = MAX dari max per-jam, min = MIN dari min per-jam,
        // avg = AVG dari avg per-jam (bukan dihitung ulang dari raw data, karena raw data
        // ES tidak lagi tersedia di layer ini — cukup akurat untuk keperluan tren chart).
        $buildStats = function (Collection $rows) use ($label, $g) {
            $labels = $rows->map($label)->unique()->values()->all();

            if ($g['isDaily']) {
                $byLabel = $rows->groupBy($label)->map(fn($grp) => [
                    'avg_pct' => $grp->avg('avg_pct'),
                    'max_pct' => $grp->max('max_pct'),
                    'min_pct' => $grp->min('min_pct'),
                ]);

                return [
                    collect($labels)->mapWithKeys(fn($lbl) => [$lbl => round(($byLabel->get($lbl, [])['avg_pct'] ?? 0) * 100, 2)])->toArray(),
                    collect($labels)->mapWithKeys(fn($lbl) => [$lbl => round(($byLabel->get($lbl, [])['max_pct'] ?? 0) * 100, 2)])->toArray(),
                    collect($labels)->mapWithKeys(fn($lbl) => [$lbl => round(($byLabel->get($lbl, [])['min_pct'] ?? 0) * 100, 2)])->toArray(),
                ];
            }

            $byLabel = $rows->keyBy($label);

            return [
                collect($labels)->mapWithKeys(fn($lbl) => [$lbl => round(($byLabel->get($lbl)?->avg_pct ?? 0) * 100, 2)])->toArray(),
                collect($labels)->mapWithKeys(fn($lbl) => [$lbl => round(($byLabel->get($lbl)?->max_pct ?? 0) * 100, 2)])->toArray(),
                collect($labels)->mapWithKeys(fn($lbl) => [$lbl => round(($byLabel->get($lbl)?->min_pct ?? 0) * 100, 2)])->toArray(),
            ];
        };

        [$cpuAvg, $cpuMax, $cpuMin] = $buildStats($cpuData);
        [$memAvg, $memMax, $memMin] = $buildStats($memData);

        $diskPaths   = $diskData->pluck('disk_path')->unique()->sort()->values();
        $diskColumns = [];
        if ($showDisk && $diskPaths->isNotEmpty()) {
            // Zero-fill: tiap disk_path bisa punya jam yang beda-beda, samakan label
            // sumbu-X across semua path supaya tidak ada series yang tidak selaras.
            $diskLabels = $diskData->map($label)->unique()->values()->all();

            $diskChart = LineChartMetric::make("Disk Usage (%) per {$unit}");
            foreach ($diskPaths as $path) {
                $pathRows = $diskData->where('disk_path', $path);

                if ($g['isDaily']) {
                    $byLabel = $pathRows->groupBy($label)->map(fn($grp) => $grp->avg('last_pct'));
                    $series  = collect($diskLabels)->mapWithKeys(
                        fn($lbl) => [$lbl => round(($byLabel->get($lbl) ?? 0) * 100, 2)]
                    )->toArray();
                } else {
                    $byLabel = $pathRows->keyBy($label);
                    $series  = collect($diskLabels)->mapWithKeys(
                        fn($lbl) => [$lbl => round(($byLabel->get($lbl)?->last_pct ?? 0) * 100, 2)]
                    )->toArray();
                }

                $diskChart->series(SeriesItem::make($path, $series)->line());
            }
            $diskColumns[] = Column::make([$diskChart])->columnSpan(12);
        }

        $latestCpu = $cpuData->last()?->avg_pct;
        $latestMem = $memData->last()?->avg_pct;

        return Grid::make(array_filter([
            Column::make([Divider::make()])->columnSpan(12),
            Column::make([Alert::make(type: 'info')->content("Periode: <strong>{$period}</strong>")])->columnSpan(12),

            ($showCpu && $latestCpu !== null)
                ? Column::make([ValueMetric::make('CPU Avg (terbaru)')->value(number_format($latestCpu * 100, 1) . '%')])->columnSpan(6)
                : null,
            ($showMem && $latestMem !== null)
                ? Column::make([ValueMetric::make('Memory Avg (terbaru)')->value(number_format($latestMem * 100, 1) . '%')])->columnSpan(6)
                : null,

            ($showCpu && !empty($cpuAvg))
                ? Column::make([
                    LineChartMetric::make("CPU (%) per {$unit}")
                        ->series(SeriesItem::make('Max', $cpuMax)->line())
                        ->series(SeriesItem::make('Avg', $cpuAvg)->line())
                        ->series(SeriesItem::make('Min', $cpuMin)->line()),
                ])->columnSpan(12)
                : null,

            ($showMem && !empty($memAvg))
                ? Column::make([
                    LineChartMetric::make("Memory (%) per {$unit}")
                        ->series(SeriesItem::make('Max', $memMax)->line())
                        ->series(SeriesItem::make('Avg', $memAvg)->line())
                        ->series(SeriesItem::make('Min', $memMin)->line()),
                ])->columnSpan(12)
                : null,

            ...$diskColumns,
        ]));
    }

    private function formatBytes(int $bytes): string
    {
        $gb = $bytes / (1024 ** 3);
        return $gb >= 1
            ? number_format($gb, 1) . ' GB'
            : number_format($bytes / (1024 ** 2), 0) . ' MB';
    }
}
