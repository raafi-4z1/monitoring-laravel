<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\AppMetric\Pages;

use App\Models\AppMetric;
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
use MoonShine\UI\Fields\Text;
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

            Text::make('Aplikasi', 'nama_aplikasi')->sortable(),
            Text::make('Metrik', 'metric')->sortable(),
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
            Text::make('Aplikasi', 'nama_aplikasi'),
            Text::make('Metrik', 'metric'),
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
        $latest = AppMetric::latest('recorded_at')->first();

        return $latest
            ? Alert::make(type: 'info')
                ->content("Data terakhir: <strong>{$latest->nama_aplikasi} / {$latest->metric}</strong> — {$latest->recorded_at->format('d M Y H:i')}")
            : Alert::make(type: 'warning')
                ->content('Belum ada data. Gunakan tombol <strong>Tambah Metrik</strong> untuk input manual.');
    }

    private function getFilteredData(): array
    {
        $from = request()->input('_data.filter.recorded_at.from')
             ?? request()->input('filter.recorded_at.from')
             ?? now()->subDays(6)->format('Y-m-d');

        $to = request()->input('_data.filter.recorded_at.to')
           ?? request()->input('filter.recorded_at.to')
           ?? now()->format('Y-m-d');

        $namaAplikasi = request()->input('_data.filter.nama_aplikasi')
                     ?? request()->input('filter.nama_aplikasi');

        $metric = request()->input('_data.filter.metric')
               ?? request()->input('filter.metric');

        $dateFrom = !empty($from) ? $from : now()->subDays(6)->format('Y-m-d');
        $dateTo   = !empty($to)   ? $to   : now()->format('Y-m-d');

        $period = Carbon::parse($dateFrom)->format('d M Y')
                . ' – '
                . Carbon::parse($dateTo)->format('d M Y');

        $query = AppMetric::query()
            ->whereBetween('recorded_at', [
                Carbon::parse($dateFrom)->startOfDay(),
                Carbon::parse($dateTo)->endOfDay(),
            ]);

        if (!empty($namaAplikasi)) {
            $query->where('nama_aplikasi', 'LIKE', '%' . strtoupper(trim($namaAplikasi)) . '%');
        }

        if (!empty($metric)) {
            $query->where('metric', 'LIKE', '%' . strtoupper(trim($metric)) . '%');
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

        // Kelompokkan per jenis metrik (CPU, MEMORY, dll.)
        $byMetric     = $data->groupBy('metric');
        $chartColumns = [];

        foreach ($byMetric as $metricName => $records) {
            $byApp  = $records->groupBy('nama_aplikasi');
            $satuan = $records->first()?->satuan ?? '';
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
