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
        $row = EngineNotifReport::whereDate('report_date', $date)->first();

        if (!$row) {
            return Grid::make([
                Column::make([
                    Alert::make(type: 'warning')->content('Belum ada data Engine Notif untuk kemarin.'),
                ])->columnSpan(12),
            ]);
        }

        return Grid::make([
            Column::make([
                ValueMetric::make('Total Success')
                    ->value(number_format($row->total_success)),
            ])->columnSpan(3),
            Column::make([
                ValueMetric::make('Total Fail')
                    ->value(number_format($row->total_fail)),
            ])->columnSpan(3),
            Column::make([
                ValueMetric::make('MVRK Total')
                    ->value(number_format($row->mvrk_total)),
            ])->columnSpan(2),
            Column::make([
                ValueMetric::make('SMS Total')
                    ->value(number_format($row->sms_total)),
            ])->columnSpan(2),
            Column::make([
                ValueMetric::make('Email Total')
                    ->value(number_format($row->email_total)),
            ])->columnSpan(2),
        ]);
    }

    private function mteleplusSection(Carbon $date): Grid
    {
        $row = MteleplusReport::whereDate('report_date', $date)->first();

        if (!$row) {
            return Grid::make([
                Column::make([
                    Alert::make(type: 'warning')->content('Belum ada data Mteleplus untuk kemarin.'),
                ])->columnSpan(12),
            ]);
        }

        return Grid::make([
            Column::make([
                ValueMetric::make('Total Success')
                    ->value(number_format($row->total_success)),
            ])->columnSpan(3),
            Column::make([
                ValueMetric::make('Total Fail')
                    ->value(number_format($row->total_fail)),
            ])->columnSpan(3),
            Column::make([
                ValueMetric::make('AKT Total')
                    ->value(number_format($row->akt_total)),
            ])->columnSpan(3),
            Column::make([
                ValueMetric::make('RPIN Total')
                    ->value(number_format($row->rpin_total)),
            ])->columnSpan(3),
            Column::make([
                ValueMetric::make('Incoming')
                    ->value(number_format($row->total_incoming)),
            ])->columnSpan(6),
            Column::make([
                ValueMetric::make('Outgoing')
                    ->value(number_format($row->total_outgoing)),
            ])->columnSpan(6),
        ]);
    }

    private function trxPbiLimitSection(Carbon $date): Grid
    {
        $rows = TrxPbiLimitReport::whereBetween('report_hour', [
            $date->format('Y-m-d') . ' 00:00:00',
            $date->format('Y-m-d') . ' 23:59:59',
        ])->get();

        if ($rows->isEmpty()) {
            return Grid::make([
                Column::make([
                    Alert::make(type: 'warning')->content('Belum ada data TrxPBI Limit untuk kemarin.'),
                ])->columnSpan(12),
            ]);
        }

        $byCcy2 = $rows->groupBy('ccy2');

        $cols = [
            Column::make([
                ValueMetric::make('Total Transaksi')
                    ->value(number_format($rows->sum('total_trx'))),
            ])->columnSpan(4),
            Column::make([
                ValueMetric::make('Total NominalEqUSD')
                    ->value(number_format($rows->sum('total_nominal_eq_usd'), 2)),
            ])->columnSpan(4),
            Column::make([
                ValueMetric::make('Total Nominal')
                    ->value(number_format($rows->sum('total_nominal'), 0)),
            ])->columnSpan(4),
        ];

        foreach ($byCcy2 as $ccy2 => $ccyRows) {
            $cols[] = Column::make([
                ValueMetric::make("Trx {$ccy2}")
                    ->value(number_format($ccyRows->sum('total_trx'))),
            ])->columnSpan(3);
        }

        return Grid::make($cols);
    }

    private function trxPbiSettlementSection(Carbon $date): Grid
    {
        $rows = TrxPbiSettlementReport::whereBetween('report_hour', [
            $date->format('Y-m-d') . ' 00:00:00',
            $date->format('Y-m-d') . ' 23:59:59',
        ])->get();

        if ($rows->isEmpty()) {
            return Grid::make([
                Column::make([
                    Alert::make(type: 'warning')->content('Belum ada data TrxPBI Settlement untuk kemarin.'),
                ])->columnSpan(12),
            ]);
        }

        $byCcy2 = $rows->groupBy('ccy2');

        $cols = [
            Column::make([
                ValueMetric::make('Total Transaksi')
                    ->value(number_format($rows->sum('total_trx'))),
            ])->columnSpan(4),
            Column::make([
                ValueMetric::make('Total NominalEqUSD')
                    ->value(number_format($rows->sum('total_nominal_eq_usd'), 2)),
            ])->columnSpan(4),
            Column::make([
                ValueMetric::make('Total Nominal')
                    ->value(number_format($rows->sum('total_nominal'), 0)),
            ])->columnSpan(4),
        ];

        foreach ($byCcy2 as $ccy2 => $ccyRows) {
            $cols[] = Column::make([
                ValueMetric::make("Trx {$ccy2}")
                    ->value(number_format($ccyRows->sum('total_trx'))),
            ])->columnSpan(3);
        }

        return Grid::make($cols);
    }
}
