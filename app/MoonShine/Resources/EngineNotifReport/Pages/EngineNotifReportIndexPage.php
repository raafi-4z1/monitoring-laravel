<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\EngineNotifReport\Pages;

use App\Models\EngineNotifReport;
use App\MoonShine\Resources\EngineNotifReport\EngineNotifReportResource;
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
 * @extends IndexPage<EngineNotifReportResource>
 */
class EngineNotifReportIndexPage extends IndexPage
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
            Date::make('Tanggal', 'report_date')
                ->sortable()
                ->format('Y-m-d'),

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

            Preview::make('Avg RT (s)',       'avg_response_time')
                ->changeFill(fn($item) => number_format((float) $item->avg_response_time, 2) . 's')
                ->sortable(),

            Preview::make('Avg Lifespan (ms)', 'avg_lifespan')
                ->changeFill(fn($item) => number_format((float) $item->avg_lifespan, 2) . 's')
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
     * @param TableBuilder $component
     * @return TableBuilder
     */
    protected function modifyListComponent(ComponentContract $component): ComponentContract
    {
        return $component
            ->columnSelection()
            ->sticky()
            ->stickyButtons()
            // ✅ Saat filter submit → table reload → dispatch FRAGMENT_UPDATED
            // Fragment akan reload dengan withQueryParams() membawa filter dari URL
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
            session(['perPage' => $perPage]);
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

        $alerts = [$this->lastUpdateAlert()];

        if (now()->day === 1) {
            $alerts[] = Alert::make(type: 'warning')
                ->content(
                    'Hari ini awal bulan — data bulan ini belum tersedia. '
                    . 'Data kemarin (<strong>' . now()->subDay()->format('d M Y') . '</strong>) '
                    . 'tersimpan di bulan sebelumnya. Gunakan filter tanggal untuk melihat data bulan lalu.'
                );
        }

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
        $latest = EngineNotifReport::latest('report_date')->first();

        return $latest
            ? Alert::make(type: 'info')
                ->content("Data terakhir: <strong>{$latest->report_date->format('d M Y')}</strong> — diupdate: {$latest->updated_at->format('d M Y H:i')}")
            : Alert::make(type: 'warning')
                ->content('Belum ada data. Gunakan Fetch Manual.');
    }
    
    /**
     * @return array{0: string, 1: string, 2: string, 3: \Illuminate\Support\Collection}
     */
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

        $data = EngineNotifReport::query()
            ->whereBetween('report_date', [$dateFrom, $dateTo])
            ->orderBy('report_date')
            ->get();

        return [$dateFrom, $dateTo, $period, $data];
    }

    /**
     * Render semua chart berdasarkan data yang sudah difilter.
     */
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

        $successByDate = $data->mapWithKeys(fn($row) => [
            $row->report_date->format('Y-m-d') => (int) $row->total_success,
        ])->toArray();

        $failByDate = $data->mapWithKeys(fn($row) => [
            $row->report_date->format('Y-m-d') => (int) $row->total_fail,
        ])->toArray();

        $avgRtByDate = $data->mapWithKeys(fn($row) => [
            $row->report_date->format('Y-m-d') => (float) $row->avg_response_time,
        ])->toArray();

        $avgLsByDate = $data->mapWithKeys(fn($row) => [
            $row->report_date->format('Y-m-d') => (float) $row->avg_lifespan,
        ])->toArray();

        return Grid::make([
            Column::make([Divider::make()])->columnSpan(12),

            // ✅ Info periode
            Column::make([
                Alert::make(type: 'info')
                    ->content("Data periode: <strong>{$period}</strong>"),
            ])->columnSpan(12),

            // ✅ ValueMetric
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

            // ✅ Line Chart: Success vs Fail
            Column::make([
                LineChartMetric::make('Success vs Fail per Hari')
                    ->series(SeriesItem::make('Total Success', $successByDate)->line())
                    ->series(SeriesItem::make('Total Fail', $failByDate)->line()),
            ])->columnSpan(12),

            // ✅ Line Chart: Avg Response Time
            Column::make([
                LineChartMetric::make('Avg Response Time (s)')
                    ->series(SeriesItem::make('Avg RT', $avgRtByDate)->line()),
            ])->columnSpan(6),

            // ✅ Line Chart: Avg Lifespan
            Column::make([
                LineChartMetric::make('Avg Lifespan (ms)')
                    ->series(SeriesItem::make('Avg Lifespan', $avgLsByDate)->line()),
            ])->columnSpan(6),

            // ✅ Donut Chart: per Channel
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
