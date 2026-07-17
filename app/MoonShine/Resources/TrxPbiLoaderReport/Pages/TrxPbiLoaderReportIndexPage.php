<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\TrxPbiLoaderReport\Pages;

use App\Models\TrxPbiLoaderReport;
use App\MoonShine\Resources\TrxPbiLoaderReport\TrxPbiLoaderReportResource;
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
 * @extends IndexPage<TrxPbiLoaderReportResource>
 */
class TrxPbiLoaderReportIndexPage extends IndexPage
{
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
                ->changeFill(fn($i) => sprintf('%02d', $i->trx_hour))
                ->sortable(fn($q, $_c, $d) => $q->orderBy('trx_date', $d)->orderBy('trx_hour', $d)),
            Text::make('Job Type', 'job_type'),
            Text::make('Job Name', 'job_name'),
            Preview::make('Start', 'start_time')
                ->changeFill(fn($i) => $i->start_time ?: '-'),
            Preview::make('End', 'end_time')
                ->changeFill(fn($i) => $i->end_time ?: '-'),
            Preview::make('Durasi (s)', 'duration_sec')
                ->changeFill(fn($i) => number_format($i->duration_sec))
                ->sortable(),
            Preview::make('Record', 'record_processed')
                ->changeFill(fn($i) => number_format($i->record_processed))
                ->sortable(),
            Preview::make('Throughput (row/s)', 'throughput_row_per_sec')
                ->changeFill(fn($i) => number_format($i->throughput_row_per_sec, 2))
                ->sortable(),
            Preview::make('Status', 'status_job')
                ->changeFill(fn($i) => $i->status_job === 'success'
                    ? '<span style="color:#22c55e;font-weight:600;">success</span>'
                    : '<span style="color:#ef4444;font-weight:600;">failed</span>')
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
            Select::make('Status Job', 'status_job')
                ->options(['success' => 'Success', 'failed' => 'Failed'])
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
                AlpineJs::event(JsEvent::FRAGMENT_UPDATED, 'trx-pbi-loader-charts'),
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
            session(['trxPbiLoaderPerPage' => $perPage]);
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
        [2 => $period, 3 => $data, 4 => $statusFilter] = $this->getFilteredData();

        return [
            $this->lastUpdateAlert(),
            ...parent::mainLayer(),
            Fragment::make([$this->buildCharts($data, $period, $statusFilter)])
                ->name('trx-pbi-loader-charts')
                ->withQueryParams(),
        ];
    }

    protected function lastUpdateAlert(): Alert
    {
        $latest = TrxPbiLoaderReport::latest('trx_date')->latest('trx_hour')->first();

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
        $fromInput   = request()->input('_data.filter.trx_date.from')
            ?? request()->input('filter.trx_date.from');
        $toInput     = request()->input('_data.filter.trx_date.to')
            ?? request()->input('filter.trx_date.to');
        $statusInput = request()->input('_data.filter.status_job')
            ?? request()->input('filter.status_job');

        $isDefault = empty($fromInput) && empty($toInput);
        $dateFrom  = !empty($fromInput) ? substr($fromInput, 0, 10) : Carbon::yesterday()->format('Y-m-d');
        $dateTo    = !empty($toInput)   ? substr($toInput, 0, 10)   : ($isDefault ? Carbon::yesterday()->format('Y-m-d') : Carbon::now()->format('Y-m-d'));

        $period = Carbon::parse($dateFrom)->format('d M Y') . ' - ' . Carbon::parse($dateTo)->format('d M Y');

        $data = TrxPbiLoaderReport::query()
            ->whereBetween('trx_date', [$dateFrom, $dateTo])
            ->orderBy('trx_date')
            ->orderBy('trx_hour')
            ->get();

        return [$dateFrom, $dateTo, $period, $data, $statusInput ?: null];
    }

    private function buildCharts(Collection $data, string $period, ?string $statusFilter): Grid
    {
        if ($data->isEmpty()) {
            return Grid::make([
                Column::make([Divider::make()])->columnSpan(12),
                Column::make([Alert::make(type: 'info')->content('Tidak ada data untuk periode ini.')])->columnSpan(12),
            ]);
        }

        $filtered = $statusFilter !== null
            ? $data->where('status_job', $statusFilter)
            : $data;

        if ($filtered->isEmpty()) {
            return Grid::make([
                Column::make([Divider::make()])->columnSpan(12),
                Column::make([Alert::make(type: 'info')->content('Tidak ada data untuk filter ini.')])->columnSpan(12),
            ]);
        }

        $sorted = $filtered->sortBy(fn($r) => $r->trx_date->format('Y-m-d') . sprintf('%02d', $r->trx_hour))->values();

        $isSingleDay = $data->pluck('trx_date')->map(fn($d) => $d->format('Y-m-d'))->unique()->count() === 1;
        $label = $isSingleDay
            ? fn($r) => sprintf('%02d:00', $r->trx_hour)
            : fn($r) => $r->trx_date->format('d/m') . ' ' . sprintf('%02d:00', $r->trx_hour);

        $successData = $sorted->where('status_job', 'success')->values();
        $failedData  = $sorted->where('status_job', 'failed')->values();

        // Semua series dibangun dari label (jam) yang SAMA supaya sumbu-X seragam antar
        // series — kalau Success & Failed di-map dari key yang beda-beda, series yang cuma
        // punya sedikit titik bisa "jatuh" ke posisi default 00:00 di chart-nya.
        $labels  = $sorted->map($label)->unique()->values()->all();
        $bySucc  = $successData->keyBy($label);
        $byFail  = $failedData->keyBy($label);

        $recordSuccess = $throughputSuccess = $durationSuccess = [];
        $recordFailed  = $throughputFailed  = $durationFailed  = [];

        foreach ($labels as $lbl) {
            $s = $bySucc->get($lbl);
            $f = $byFail->get($lbl);

            $recordSuccess[$lbl]     = $s?->record_processed ?? 0;
            $recordFailed[$lbl]      = $f?->record_processed ?? 0;
            $throughputSuccess[$lbl] = $s ? round($s->throughput_row_per_sec, 2) : 0;
            $throughputFailed[$lbl]  = $f ? round($f->throughput_row_per_sec, 2) : 0;
            $durationSuccess[$lbl]   = $s?->duration_sec ?? 0;
            $durationFailed[$lbl]    = $f?->duration_sec ?? 0;
        }

        $totalRecord = $filtered->sum('record_processed');
        $avgThrough  = $successData->isNotEmpty() ? $successData->avg('throughput_row_per_sec') : 0;
        $avgDuration = $filtered->avg('duration_sec');
        $failedCount = $data->where('status_job', 'failed')->count();

        return Grid::make(array_filter([
            Column::make([Divider::make()])->columnSpan(12),
            Column::make([Alert::make(type: 'info')->content("Periode: <strong>{$period}</strong>")])->columnSpan(12),

            Column::make([
                ValueMetric::make('Total Record')->value(number_format($totalRecord)),
            ])->columnSpan(3),
            Column::make([
                ValueMetric::make('Avg Throughput (row/s)')->value(number_format($avgThrough, 2)),
            ])->columnSpan(3),
            Column::make([
                ValueMetric::make('Avg Durasi (s)')->value(number_format($avgDuration, 0)),
            ])->columnSpan(3),
            Column::make([
                ValueMetric::make('Job Gagal')->value(number_format($failedCount)),
            ])->columnSpan(3),

            ($successData->isNotEmpty() || $failedData->isNotEmpty())
                ? Column::make([
                    LineChartMetric::make('Record Processed per Jam')
                        ->when($successData->isNotEmpty(), fn($c) => $c->series(SeriesItem::make('Success', $recordSuccess)->line()))
                        ->when($failedData->isNotEmpty(), fn($c) => $c->series(SeriesItem::make('Failed', $recordFailed)->line())),
                ])->columnSpan(12)
                : null,

            ($successData->isNotEmpty() || $failedData->isNotEmpty())
                ? Column::make([
                    LineChartMetric::make('Throughput (row/detik) per Jam')
                        ->when($successData->isNotEmpty(), fn($c) => $c->series(SeriesItem::make('Success', $throughputSuccess)->line()))
                        ->when($failedData->isNotEmpty(), fn($c) => $c->series(SeriesItem::make('Failed', $throughputFailed)->line())),
                ])->columnSpan(12)
                : null,

            ($successData->isNotEmpty() || $failedData->isNotEmpty())
                ? Column::make([
                    LineChartMetric::make('Durasi (detik) per Jam')
                        ->when($successData->isNotEmpty(), fn($c) => $c->series(SeriesItem::make('Success', $durationSuccess)->line()))
                        ->when($failedData->isNotEmpty(), fn($c) => $c->series(SeriesItem::make('Failed', $durationFailed)->line())),
                ])->columnSpan(12)
                : null,
        ]));
    }
}
