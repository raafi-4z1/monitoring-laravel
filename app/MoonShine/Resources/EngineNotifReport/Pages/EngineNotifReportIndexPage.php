<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\EngineNotifReport\Pages;

use App\MoonShine\Concerns\BuildsHourlyOrDailyChart;
use App\Models\EngineNotifReport;
use App\MoonShine\Resources\EngineNotifReport\EngineNotifReportResource;
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
 * @extends IndexPage<EngineNotifReportResource>
 */
class EngineNotifReportIndexPage extends IndexPage
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

            Number::make('MVRK Success', 'mvrk_success')->sortable(),
            Number::make('MVRK Fail',    'mvrk_fail')->sortable(),
            Number::make('MVRK Total',   'mvrk_total')->sortable(),

            Number::make('SMS Success',  'sms_success')->sortable(),
            Number::make('SMS Fail',     'sms_fail')->sortable(),
            Number::make('SMS Total',    'sms_total')->sortable(),

            Number::make('Email Success','email_success')->sortable(),
            Number::make('Email Fail',   'email_fail')->sortable(),
            Number::make('Email Total',  'email_total')->sortable(),

            Number::make('Total Success','total_success')->sortable(),
            Number::make('Total Fail',   'total_fail')->sortable(),

            Preview::make('Avg RT (s)',        'avg_response_time')
                ->changeFill(fn($item) => number_format((float) $item->avg_response_time, 2) . 's')
                ->sortable(),

            Preview::make('Avg Lifespan (ms)', 'avg_lifespan')
                ->changeFill(fn($item) => number_format((float) $item->avg_lifespan, 2) . 'ms')
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
                AlpineJs::event(JsEvent::FRAGMENT_UPDATED, 'engine-notif-charts'),
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
            session(['engineNotifPerPage' => $perPage]);
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
            ->name('engine-notif-charts')
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
            ...parent::bottomLayer(),
        ];
    }

    protected function lastUpdateAlert(): Alert
    {
        $latest = EngineNotifReport::latest('trx_date')->latest('trx_hour')->first();

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

        $data = EngineNotifReport::query()
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
                    Alert::make(type: 'info')
                        ->content('Tidak ada data untuk periode ini.'),
                ])->columnSpan(12),
            ]);
        }

        $totalSuccess = $data->sum('total_success');
        $totalFail    = $data->sum('total_fail');
        $totalMvrk    = $data->sum('mvrk_success') + $data->sum('mvrk_fail');
        $totalSms     = $data->sum('sms_success')   + $data->sum('sms_fail');
        $totalEmail   = $data->sum('email_success')  + $data->sum('email_fail');

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
                'total_success'     => $grp->sum('total_success'),
                'total_fail'        => $grp->sum('total_fail'),
                'avg_response_time' => $grp->avg('avg_response_time'),
                'avg_lifespan'      => $grp->avg('avg_lifespan'),
            ]);

            $successByHour = collect($labels)->mapWithKeys(fn($lbl) => [$lbl => (int) ($byLabel->get($lbl, [])['total_success'] ?? 0)])->toArray();
            $failByHour    = collect($labels)->mapWithKeys(fn($lbl) => [$lbl => (int) ($byLabel->get($lbl, [])['total_fail'] ?? 0)])->toArray();
            $avgRtByHour   = collect($labels)->mapWithKeys(fn($lbl) => [$lbl => (float) ($byLabel->get($lbl, [])['avg_response_time'] ?? 0)])->toArray();
            $avgLsByHour   = collect($labels)->mapWithKeys(fn($lbl) => [$lbl => (float) ($byLabel->get($lbl, [])['avg_lifespan'] ?? 0)])->toArray();
        } else {
            $byLabel = $sorted->keyBy($label);

            $successByHour = collect($labels)->mapWithKeys(fn($lbl) => [$lbl => (int) ($byLabel->get($lbl)?->total_success ?? 0)])->toArray();
            $failByHour    = collect($labels)->mapWithKeys(fn($lbl) => [$lbl => (int) ($byLabel->get($lbl)?->total_fail ?? 0)])->toArray();
            $avgRtByHour   = collect($labels)->mapWithKeys(fn($lbl) => [$lbl => (float) ($byLabel->get($lbl)?->avg_response_time ?? 0)])->toArray();
            $avgLsByHour   = collect($labels)->mapWithKeys(fn($lbl) => [$lbl => (float) ($byLabel->get($lbl)?->avg_lifespan ?? 0)])->toArray();
        }

        return Grid::make([
            Column::make([Divider::make()])->columnSpan(12),

            Column::make([
                Alert::make(type: 'info')
                    ->content("Data periode: <strong>{$period}</strong>"),
            ])->columnSpan(12),

            Column::make([
                ValueMetric::make('Total Transaksi')
                    ->value(number_format($totalSuccess + $totalFail)),
            ])->columnSpan(4),

            Column::make([
                ValueMetric::make('Total Berhasil')
                    ->value(number_format($totalSuccess)),
            ])->columnSpan(4),

            Column::make([
                ValueMetric::make('Total Gagal')
                    ->value(number_format($totalFail)),
            ])->columnSpan(4),

            Column::make([
                LineChartMetric::make("Success vs Fail per {$unit}")
                    ->series(SeriesItem::make('Total Success', $successByHour)->line())
                    ->series(SeriesItem::make('Total Fail', $failByHour)->line()),
            ])->columnSpan(12),

            Column::make([
                LineChartMetric::make("Avg Response Time (s) per {$unit}")
                    ->series(SeriesItem::make('Avg RT', $avgRtByHour)->line()),
            ])->columnSpan(6),

            Column::make([
                LineChartMetric::make("Avg Lifespan (ms) per {$unit}")
                    ->series(SeriesItem::make('Avg Lifespan', $avgLsByHour)->line()),
            ])->columnSpan(6),

            Column::make([
                DonutChartMetric::make('Distribusi per Channel')
                    ->values([
                        'MVRK'  => $totalMvrk,
                        'SMS'   => $totalSms,
                        'Email' => $totalEmail,
                    ])
                    ->decimals(0),
            ])->columnSpan(6),
        ]);
    }
}
