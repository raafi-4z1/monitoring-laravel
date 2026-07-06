<?php

declare(strict_types=1);

namespace App\MoonShine\Pages;

use App\Models\EngineNotifReport;
use App\Models\MteleplusReport;
use App\Models\TrxPbiLimitReport;
use App\Models\TrxPbiSettlementReport;
use Carbon\Carbon;
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

        return [
            Alert::make(type: 'info')
                ->content("Menampilkan data <strong>kemarin — {$label}</strong> dari database."),

            Divider::make('Engine Notif Report'),
            $this->engineNotifSection($yesterday),

            Divider::make('Mteleplus Report'),
            $this->mteleplusSection($yesterday),

            Divider::make('TrxPBI Limit'),
            $this->trxPbiLimitSection($yesterday),

            Divider::make('TrxPBI Settlement'),
            $this->trxPbiSettlementSection($yesterday),
        ];
    }

    private function engineNotifSection(Carbon $date): Grid
    {
        $dateStr = $date->format('Y-m-d');
        $rows    = EngineNotifReport::whereBetween('report_hour', [
            $dateStr . ' 00:00:00',
            $dateStr . ' 23:59:59',
        ])->get();

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
}
