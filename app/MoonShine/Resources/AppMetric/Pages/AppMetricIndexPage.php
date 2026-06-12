<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\AppMetric\Pages;

use App\Models\AppMetric;
use App\Models\MasterAplikasi;
use App\Models\MasterMetrik;
use App\MoonShine\Resources\AppMetric\AppMetricResource;
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
use MoonShine\UI\Components\Table\TableBuilder;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\DateRange;
use MoonShine\UI\Fields\Preview;
use MoonShine\UI\Fields\Select;
use Throwable;


/**
 * @extends IndexPage<AppMetricResource>
 */
class AppMetricIndexPage extends IndexPage
{
    protected bool $isLazy = true;

    protected function assets(): array
    {
        return [
            ...LineChartMetric::make('')->getAssets(),
        ];
    }

    /**
     * @return list<FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            Date::make('Timestamp', 'recorded_at')
                ->withTime()
                ->format('d M Y H:i:s')
                ->sortable(),

            Preview::make('Aplikasi', 'master_aplikasi_id', static fn($original) => $original?->masterAplikasi?->nama ?? '-'),
            Preview::make('Metrik', 'master_metrik_id', static fn($original) => $original?->masterMetrik?->nama ?? '-'),
            Preview::make('Value', 'value'),
            Preview::make('Satuan', 'satuan'),
        ];
    }

    protected function topLeftButtons(): ListOf
    {
        return new ListOf(ActionButtonContract::class, [
            CreateButton::for(
                label: 'Tambah Metrik',
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
            DateRange::make('Timestamp', 'recorded_at'),
            Select::make('Aplikasi', 'master_aplikasi_id')
                ->options(MasterAplikasi::pluck('nama', 'id')->toArray())
                ->nullable(),
            Select::make('Metrik', 'master_metrik_id')
                ->options(MasterMetrik::pluck('nama', 'id')->toArray())
                ->nullable(),
        ];
    }

    /**
     * @param  TableBuilder  $component
     */
    protected function modifyListComponent(ComponentContract $component): ComponentContract
    {
        return $component
            ->columnSelection()
            ->sticky()
            ->stickyButtons()
            ->async(events: [
                AlpineJs::event(JsEvent::FRAGMENT_UPDATED, 'app-metric-charts'),
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
            session(['appMetricPerPage' => $perPage]);
        }

        return JsonResponse::make()->events([
            AlpineJs::event(JsEvent::TABLE_UPDATED, $this->getListComponentName()),
        ]);
    }

    /**
     * @return list<ComponentContract>
     * @throws Throwable
     */
    protected function topLayer(): array
    {
        return [...parent::topLayer()];
    }

    /**
     * @return list<ComponentContract>
     * @throws Throwable
     */
    protected function mainLayer(): array
    {
        [, , $period, $data] = $this->getFilteredData();

        return [
            $this->latestEntryAlert(),
            ...parent::mainLayer(),

            Fragment::make([
                $this->buildCharts($data, $period),
            ])
            ->name('app-metric-charts')
            ->withQueryParams(),
        ];
    }

    /**
     * @return list<ComponentContract>
     * @throws Throwable
     */
    protected function bottomLayer(): array
    {
        return [...parent::bottomLayer()];
    }

    private function latestEntryAlert(): Alert
    {
        $latest = AppMetric::with(['masterAplikasi', 'masterMetrik'])->latest('recorded_at')->first();

        if (! $latest) {
            return Alert::make(type: 'warning')
                ->content('Belum ada data. Gunakan tombol <strong>Tambah Metrik</strong> untuk input manual.');
        }

        $appName    = $latest->masterAplikasi?->nama ?? '-';
        $metrikName = $latest->masterMetrik?->nama ?? '-';
        $time       = $latest->recorded_at->format('d M Y H:i');

        return Alert::make(type: 'info')
            ->content("Data terakhir: <strong>{$appName} / {$metrikName}</strong> — {$time}");
    }

    private function getFilteredData(): array
    {
        $from = request()->input('_data.filter.recorded_at.from')
             ?? request()->input('filter.recorded_at.from')
             ?? now()->subDays(6)->format('Y-m-d');

        $to = request()->input('_data.filter.recorded_at.to')
           ?? request()->input('filter.recorded_at.to')
           ?? now()->format('Y-m-d');

        $masterAplikasiId = request()->input('_data.filter.master_aplikasi_id')
                         ?? request()->input('filter.master_aplikasi_id');

        $masterMetrikId = request()->input('_data.filter.master_metrik_id')
                       ?? request()->input('filter.master_metrik_id');

        $dateFrom = !empty($from) ? $from : now()->subDays(6)->format('Y-m-d');
        $dateTo   = !empty($to)   ? $to   : now()->format('Y-m-d');

        $period = Carbon::parse($dateFrom)->format('d M Y')
                . ' – '
                . Carbon::parse($dateTo)->format('d M Y');

        $query = AppMetric::with(['masterAplikasi', 'masterMetrik'])
            ->whereBetween('recorded_at', [
                Carbon::parse($dateFrom)->startOfDay(),
                Carbon::parse($dateTo)->endOfDay(),
            ]);

        if (!empty($masterAplikasiId)) {
            $query->where('master_aplikasi_id', (int) $masterAplikasiId);
        }

        if (!empty($masterMetrikId)) {
            $query->where('master_metrik_id', (int) $masterMetrikId);
        }

        $data = $query->orderBy('recorded_at')->get();

        return [$dateFrom, $dateTo, $period, $data];
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

        // Ambil satuan dari master_metrik sebagai sumber utama
        $masterSatuan = MasterMetrik::pluck('satuan_default', 'nama')->toArray();

        // Group by nama metrik dari relasi
        $byMetric     = $data->groupBy(static fn($r) => $r->masterMetrik?->nama ?? 'UNKNOWN');
        $chartColumns = [];

        foreach ($byMetric as $metricName => $records) {
            // Group by nama aplikasi dari relasi
            $byApp  = $records->groupBy(static fn($r) => $r->masterAplikasi?->nama ?? 'UNKNOWN');
            $satuan = $masterSatuan[$metricName] ?? $records->first()?->satuan ?? '';
            $title  = $metricName . ($satuan !== '' ? " ({$satuan})" : '');

            $chart = LineChartMetric::make($title);

            foreach ($byApp as $appName => $appRecords) {
                $seriesData = $appRecords->mapWithKeys(fn($r) => [
                    $r->recorded_at->format('Y-m-d H:i:s') => (float) $r->value,
                ])->toArray();

                $chart->series(SeriesItem::make($appName, $seriesData)->line());
            }

            $chartColumns[] = Column::make([$chart])->columnSpan(6);
        }

        return Grid::make([
            Column::make([Divider::make()])->columnSpan(12),
            Column::make([
                Alert::make(type: 'info')
                    ->content("Grafik metrik periode: <strong>{$period}</strong>"),
            ])->columnSpan(12),
            ...$chartColumns,
        ]);
    }
}
