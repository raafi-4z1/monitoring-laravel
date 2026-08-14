<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\SpacexNycFileArchiveReport\Pages;

use App\Models\SpacexNycFileArchiveReport;
use App\MoonShine\Resources\SpacexNycFileArchiveReport\SpacexNycFileArchiveReportResource;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use MoonShine\Apexcharts\Components\LineChartMetric;
use MoonShine\Apexcharts\Support\SeriesItem;
use MoonShine\Contracts\UI\ActionButtonContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Crud\Buttons\CreateButton;
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
use MoonShine\UI\Fields\DateRange;
use MoonShine\UI\Fields\Preview;
use MoonShine\UI\Fields\Select;

/**
 * @extends IndexPage<SpacexNycFileArchiveReportResource>
 */
class SpacexNycFileArchiveReportIndexPage extends IndexPage
{
    protected bool $isLazy = true;

    /**
     * Berapa hari terakhir yang ditampilkan di chart tren row_count - data ini masuk sekali
     * per hari (bukan per jam seperti resource metrik lain), jadi 30 hari sudah cukup lebar
     * tanpa bikin chart terlalu padat.
     */
    private const CHART_DAYS = 30;

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
                ->sortable(),
            Preview::make('Filename', 'filename')->sortable(),
            Preview::make('Pathfile', 'pathfile')
                ->changeFill(fn($i) => $i->pathfile ?: '-'),
            Preview::make('Row Count', 'row_count')
                ->changeFill(fn($i) => $i->row_count !== null ? number_format($i->row_count, 0, ',', '.') : '-')
                ->sortable(),
            Preview::make('Status', 'status')
                ->changeFill(fn($i) => $i->status === 'FOUND'
                    ? '<span style="color:#22c55e;font-weight:600;">' . e($i->status) . '</span>'
                    : '<span style="color:#ef4444;font-weight:600;">' . e((string) $i->status) . '</span>')
                ->sortable(),
            Preview::make('Timestamp', 'timestamp')
                ->changeFill(fn($i) => $i->timestamp ?: '-'),
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
        ];
    }

    protected function modifyListComponent(ComponentContract $component): ComponentContract
    {
        return $component
            ->columnSelection()
            ->sticky()
            ->stickyButtons()
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
            session(['spacexNycFileArchivePerPage' => $perPage]);
        }

        return JsonResponse::make()->events([
            AlpineJs::event(JsEvent::TABLE_UPDATED, $this->getListComponentName()),
        ]);
    }

    /**
     * @return list<ComponentContract>
     */
    protected function mainLayer(): array
    {
        return [
            $this->lastUpdateAlert(),
            ...parent::mainLayer(),
            $this->buildRowCountChart(),
        ];
    }

    protected function lastUpdateAlert(): Alert
    {
        $latest = SpacexNycFileArchiveReport::latest('trx_date')->first();

        return $latest
            ? Alert::make(type: 'info')
                ->content("Data terakhir: <strong>{$latest->trx_date->format('d M Y')}</strong> — diupdate: {$latest->updated_at->format('d M Y H:i')}")
            : Alert::make(type: 'warning')
                ->content('Belum ada data. Gunakan Fetch Manual.');
    }

    /**
     * Row count per grup file per hari, dalam CHART_DAYS hari terakhir. Nama file berubah
     * setiap hari (tanggal tertempel di nama), makanya dikelompokkan pakai
     * SpacexNycFileArchiveReport::fileGroup() (bagian stabil sebelum titik pertama), bukan
     * nama file mentah. Tidak terpengaruh filter tabel (DateRange) di bawahnya - independen,
     * sama seperti chart mingguan Job Execution.
     */
    private function buildRowCountChart(): Grid
    {
        $since = Carbon::now()->subDays(self::CHART_DAYS - 1)->format('Y-m-d');

        $rows = SpacexNycFileArchiveReport::query()
            ->where('trx_date', '>=', $since)
            ->orderBy('trx_date')
            ->get();

        if ($rows->isEmpty()) {
            return Grid::make([
                Column::make([Divider::make()])->columnSpan(12),
                Column::make([Alert::make(type: 'info')->content('Belum ada data untuk chart row count.')])->columnSpan(12),
            ]);
        }

        $labels = $rows->pluck('trx_date')->map(fn($d) => $d->format('Y-m-d'))->unique()->sort()->values();
        $labelDisplay = $labels->mapWithKeys(fn($d) => [$d => Carbon::parse($d)->format('d M')]);

        $groups = $rows->groupBy(fn(SpacexNycFileArchiveReport $r) => $r->fileGroup())->sortKeys();

        $chart = LineChartMetric::make('Row Count per Tanggal (' . self::CHART_DAYS . ' hari terakhir)')
            ->withoutSortKeys();

        foreach ($groups as $groupName => $groupRows) {
            $byDate = $groupRows->keyBy(fn(SpacexNycFileArchiveReport $r) => $r->trx_date->format('Y-m-d'));

            $series = $labels->mapWithKeys(fn($d) => [
                $labelDisplay[$d] => $byDate->get($d)?->row_count,
            ])->toArray();

            $chart->series(SeriesItem::make($groupName, $series)->line());
        }

        return Grid::make([
            Column::make([Divider::make()])->columnSpan(12),
            Column::make([$chart])->columnSpan(12),
        ]);
    }
}
