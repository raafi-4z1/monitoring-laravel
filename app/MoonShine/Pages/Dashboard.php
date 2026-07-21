<?php

declare(strict_types=1);

namespace App\MoonShine\Pages;

use App\Models\AppMetric;
use App\Models\EngineNotifReport;
use App\Models\MteleplusReport;
use App\Models\TrxPbiLimitReport;
use App\Models\TrxPbiLoaderReport;
use App\Models\SystemOnlineReport;
use App\Models\TrxPbiSettlementReport;
use App\Models\WicAppMetricReport;
use App\Models\WicDbMetricReport;
use App\MoonShine\Resources\AppMetric\AppMetricResource;
use App\MoonShine\Resources\EngineNotifReport\EngineNotifReportResource;
use App\MoonShine\Resources\MteleplusReport\MteleplusReportResource;
use App\MoonShine\Resources\TrxPbiLimitReport\TrxPbiLimitReportResource;
use App\MoonShine\Resources\TrxPbiLoaderReport\TrxPbiLoaderReportResource;
use App\MoonShine\Resources\SystemOnlineReport\SystemOnlineReportResource;
use App\MoonShine\Resources\TrxPbiSettlementReport\TrxPbiSettlementReportResource;
use App\MoonShine\Resources\WicAppMetricReport\WicAppMetricReportResource;
use App\MoonShine\Resources\WicDbMetricReport\WicDbMetricReportResource;
use App\Providers\MoonShineServiceProvider;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use MoonShine\Laravel\Pages\Page;
use MoonShine\UI\Components\Alert;
use MoonShine\UI\Components\Layout\Column;
use MoonShine\UI\Components\Layout\Divider;
use MoonShine\UI\Components\Layout\Grid;
use MoonShine\UI\Components\Metrics\Wrapped\ValueMetric;

#[\MoonShine\MenuManager\Attributes\SkipMenu]
class Dashboard extends Page
{
    public function getBreadcrumbs(): array
    {
        return ['#' => $this->getTitle()];
    }

    public function getTitle(): string
    {
        return $this->title ?: 'Dashboard';
    }

    protected function components(): iterable
    {
        $yesterday = Carbon::yesterday();
        $label     = $yesterday->locale('id')->isoFormat('D MMMM YYYY');

        $components = [
            Alert::make(type: 'info')
                ->content("Menampilkan data <strong>kemarin — {$label}</strong> dari database."),
        ];

        $sections = [
            [EngineNotifReportResource::class, 'Engine Notif Report', fn () => $this->engineNotifSection($yesterday)],
            [MteleplusReportResource::class, 'Mteleplus Report', fn () => $this->mteleplusSection($yesterday)],
            [TrxPbiLimitReportResource::class, 'TrxPBI Limit', fn () => $this->trxPbiLimitSection($yesterday)],
            [TrxPbiSettlementReportResource::class, 'TrxPBI Settlement', fn () => $this->trxPbiSettlementSection($yesterday)],
            [TrxPbiLoaderReportResource::class, 'Batch Job', fn () => $this->trxPbiLoaderSection($yesterday)],
            [SystemOnlineReportResource::class, 'System Online', fn () => $this->systemOnlineSection($yesterday)],
            [AppMetricResource::class, 'App Metric', fn () => $this->appMetricSection($yesterday)],
            [WicDbMetricReportResource::class, 'WIC DB Metric', fn () => $this->wicMetricSection($yesterday, WicDbMetricReport::class)],
            [WicAppMetricReportResource::class, 'WIC APP Metric', fn () => $this->wicMetricSection($yesterday, WicAppMetricReport::class)],
        ];

        foreach ($sections as [$resourceClass, $title, $builder]) {
            if (! MoonShineServiceProvider::canAccessResource($resourceClass)) {
                continue;
            }

            $components[] = Divider::make($title);
            $components[] = $builder();
        }

        if (count($components) === 1) {
            $components[] = Alert::make(type: 'warning')
                ->content('Tidak ada data yang dapat ditampilkan untuk role Anda.');
        }

        return $components;
    }

    private function engineNotifSection(Carbon $date): Grid
    {
        $dateStr = $date->format('Y-m-d');
        $rows    = EngineNotifReport::where('trx_date', $dateStr)->get();

        if ($rows->isEmpty()) {
            return Grid::make([
                Column::make([
                    Alert::make(type: 'warning')->content('Belum ada data Engine Notif untuk kemarin.'),
                ])->columnSpan(12),
            ]);
        }

        $totalSuccess = $rows->sum('mvrk_success') + $rows->sum('sms_success') + $rows->sum('email_success');
        $totalFail    = $rows->sum('mvrk_fail')    + $rows->sum('sms_fail')    + $rows->sum('email_fail');
        $mvrkTotal    = $rows->sum('mvrk_success')  + $rows->sum('mvrk_fail');
        $smsTotal     = $rows->sum('sms_success')   + $rows->sum('sms_fail');
        $emailTotal   = $rows->sum('email_success') + $rows->sum('email_fail');

        return Grid::make([
            Column::make([
                ValueMetric::make('Total Success')
                    ->value(number_format($totalSuccess)),
            ])->columnSpan(3),
            Column::make([
                ValueMetric::make('Total Fail')
                    ->value(number_format($totalFail)),
            ])->columnSpan(3),
            Column::make([
                ValueMetric::make('MVRK Total')
                    ->value(number_format($mvrkTotal)),
            ])->columnSpan(2),
            Column::make([
                ValueMetric::make('SMS Total')
                    ->value(number_format($smsTotal)),
            ])->columnSpan(2),
            Column::make([
                ValueMetric::make('Email Total')
                    ->value(number_format($emailTotal)),
            ])->columnSpan(2),
        ]);
    }

    private function mteleplusSection(Carbon $date): Grid
    {
        $dateStr = $date->format('Y-m-d');
        $rows    = MteleplusReport::whereBetween('report_hour', [
            $dateStr . ' 00:00:00',
            $dateStr . ' 23:59:59',
        ])->get();

        if ($rows->isEmpty()) {
            return Grid::make([
                Column::make([
                    Alert::make(type: 'warning')->content('Belum ada data Mteleplus untuk kemarin.'),
                ])->columnSpan(12),
            ]);
        }

        $totalSuccess  = $rows->sum('akt_success')  + $rows->sum('rpin_success');
        $totalFail     = $rows->sum('akt_fail')     + $rows->sum('rpin_fail');
        $aktTotal      = $rows->sum('akt_success')  + $rows->sum('akt_fail');
        $rpinTotal     = $rows->sum('rpin_success') + $rows->sum('rpin_fail');
        $totalIncoming = $rows->sum('total_incoming');
        $totalOutgoing = $rows->sum('total_outgoing');

        return Grid::make([
            Column::make([
                ValueMetric::make('Total Success')
                    ->value(number_format($totalSuccess)),
            ])->columnSpan(3),
            Column::make([
                ValueMetric::make('Total Fail')
                    ->value(number_format($totalFail)),
            ])->columnSpan(3),
            Column::make([
                ValueMetric::make('AKT Total')
                    ->value(number_format($aktTotal)),
            ])->columnSpan(3),
            Column::make([
                ValueMetric::make('RPIN Total')
                    ->value(number_format($rpinTotal)),
            ])->columnSpan(3),
            Column::make([
                ValueMetric::make('Incoming')
                    ->value(number_format($totalIncoming)),
            ])->columnSpan(6),
            Column::make([
                ValueMetric::make('Outgoing')
                    ->value(number_format($totalOutgoing)),
            ])->columnSpan(6),
        ]);
    }

    private function trxPbiLimitSection(Carbon $date): Grid
    {
        $rows = TrxPbiLimitReport::where('trx_date', $date->format('Y-m-d'))->get();

        if ($rows->isEmpty()) {
            return Grid::make([
                Column::make([
                    Alert::make(type: 'warning')->content('Belum ada data TrxPBI Limit untuk kemarin.'),
                ])->columnSpan(12),
            ]);
        }

        $byCurrency = $rows->groupBy('trx_currency');

        $cols = [
            Column::make([
                ValueMetric::make('Total Transaksi')
                    ->value(number_format($rows->sum('trx_count'))),
            ])->columnSpan(6),
            Column::make([
                ValueMetric::make('Total Nominal')
                    ->value(number_format($rows->sum('trx_amount'), 0)),
            ])->columnSpan(6),
        ];

        foreach ($byCurrency as $currency => $ccyRows) {
            $cols[] = Column::make([
                ValueMetric::make("Trx {$currency}")
                    ->value(number_format($ccyRows->sum('trx_count'))),
            ])->columnSpan(3);
        }

        return Grid::make($cols);
    }

    private function trxPbiSettlementSection(Carbon $date): Grid
    {
        $rows = TrxPbiSettlementReport::where('trx_date', $date->format('Y-m-d'))->get();

        if ($rows->isEmpty()) {
            return Grid::make([
                Column::make([
                    Alert::make(type: 'warning')->content('Belum ada data TrxPBI Settlement untuk kemarin.'),
                ])->columnSpan(12),
            ]);
        }

        $byCurrency = $rows->groupBy('trx_currency');

        $cols = [
            Column::make([
                ValueMetric::make('Total Transaksi')
                    ->value(number_format($rows->sum('trx_count'))),
            ])->columnSpan(6),
            Column::make([
                ValueMetric::make('Total Nominal')
                    ->value(number_format($rows->sum('trx_amount'), 0)),
            ])->columnSpan(6),
        ];

        foreach ($byCurrency as $currency => $ccyRows) {
            $cols[] = Column::make([
                ValueMetric::make("Trx {$currency}")
                    ->value(number_format($ccyRows->sum('trx_count'))),
            ])->columnSpan(3);
        }

        return Grid::make($cols);
    }

    private function trxPbiLoaderSection(Carbon $date): Grid
    {
        $rows = TrxPbiLoaderReport::where('trx_date', $date->format('Y-m-d'))->get();

        if ($rows->isEmpty()) {
            return Grid::make([
                Column::make([
                    Alert::make(type: 'warning')->content('Belum ada data Batch Job untuk kemarin.'),
                ])->columnSpan(12),
            ]);
        }

        $success = $rows->where('status_job', 'success');

        return Grid::make([
            Column::make([
                ValueMetric::make('Total Record')
                    ->value(number_format($rows->sum('record_processed'))),
            ])->columnSpan(3),
            Column::make([
                ValueMetric::make('Avg Throughput (row/s)')
                    ->value($success->isNotEmpty() ? number_format($success->avg('throughput_row_per_sec'), 2) : '-'),
            ])->columnSpan(3),
            Column::make([
                ValueMetric::make('Avg Durasi (s)')
                    ->value(number_format($rows->avg('duration_sec'), 0)),
            ])->columnSpan(3),
            Column::make([
                ValueMetric::make('Job Gagal')
                    ->value(number_format($rows->where('status_job', 'failed')->count())),
            ])->columnSpan(3),
        ]);
    }

    private function systemOnlineSection(Carbon $date): Grid
    {
        $rows = SystemOnlineReport::where('trx_date', $date->format('Y-m-d'))->get();

        if ($rows->isEmpty()) {
            return Grid::make([
                Column::make([
                    Alert::make(type: 'warning')->content('Belum ada data System Online untuk kemarin.'),
                ])->columnSpan(12),
            ]);
        }

        $services = $rows->pluck('service_name')->unique()->sort()->values();
        $span     = $services->isNotEmpty() ? max(intdiv(12, $services->count()), 3) : 12;

        $cols = $services->map(fn ($service) => Column::make([
            ValueMetric::make("Avg {$service} (ms)")
                ->value(number_format($rows->where('service_name', $service)->avg('response_time_avg_ms'), 2)),
        ])->columnSpan($span))->all();

        return Grid::make($cols);
    }

    private function appMetricSection(Carbon $date): Grid
    {
        $dateStr = $date->format('Y-m-d');
        $rows    = AppMetric::whereBetween('recorded_at', [
            $dateStr . ' 00:00:00',
            $dateStr . ' 23:59:59',
        ])->get();

        if ($rows->isEmpty()) {
            return Grid::make([
                Column::make([
                    Alert::make(type: 'warning')->content('Belum ada data App Metric untuk kemarin.'),
                ])->columnSpan(12),
            ]);
        }

        return Grid::make([
            Column::make([
                ValueMetric::make('Total Data Tercatat')
                    ->value(number_format($rows->count())),
            ])->columnSpan(4),
            Column::make([
                ValueMetric::make('Jumlah Aplikasi')
                    ->value(number_format($rows->pluck('master_aplikasi_id')->unique()->count())),
            ])->columnSpan(4),
            Column::make([
                ValueMetric::make('Jumlah Jenis Metrik')
                    ->value(number_format($rows->pluck('master_metrik_id')->unique()->count())),
            ])->columnSpan(4),
        ]);
    }

    /**
     * @param class-string<Model> $modelClass WicDbMetricReport atau WicAppMetricReport (skema identik)
     */
    private function wicMetricSection(Carbon $date, string $modelClass): Grid
    {
        $rows = $modelClass::where('trx_date', $date->format('Y-m-d'))->get();

        if ($rows->isEmpty()) {
            return Grid::make([
                Column::make([
                    Alert::make(type: 'warning')->content('Belum ada data untuk kemarin.'),
                ])->columnSpan(12),
            ]);
        }

        $cpuAvg  = $rows->where('metric_type', 'cpu')->avg('avg_pct');
        $memAvg  = $rows->where('metric_type', 'memory')->avg('avg_pct');
        $diskAvg = $rows->where('metric_type', 'disk')->avg('last_pct');

        return Grid::make([
            Column::make([
                ValueMetric::make('Avg CPU')
                    ->value($cpuAvg !== null ? number_format($cpuAvg * 100, 1) . '%' : '-'),
            ])->columnSpan(4),
            Column::make([
                ValueMetric::make('Avg Memory')
                    ->value($memAvg !== null ? number_format($memAvg * 100, 1) . '%' : '-'),
            ])->columnSpan(4),
            Column::make([
                ValueMetric::make('Avg Disk Usage')
                    ->value($diskAvg !== null ? number_format($diskAvg * 100, 1) . '%' : '-'),
            ])->columnSpan(4),
        ]);
    }
}
