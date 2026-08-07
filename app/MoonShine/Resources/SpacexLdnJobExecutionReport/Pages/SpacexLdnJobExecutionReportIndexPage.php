<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\SpacexLdnJobExecutionReport\Pages;

use App\Models\SpacexLdnJobExecutionReport;
use App\MoonShine\Resources\SpacexLdnJobExecutionReport\SpacexLdnJobExecutionReportResource;
use Carbon\Carbon;
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
 * @extends IndexPage<SpacexLdnJobExecutionReportResource>
 */
class SpacexLdnJobExecutionReportIndexPage extends IndexPage
{
    protected bool $isLazy = true;

    /**
     * Nama hari Indonesia, indeks = Carbon::dayOfWeek (0=Minggu..6=Sabtu). Jendela 7-hari
     * dipatok ke tanggal data terakhir (bukan Senin kalender), jadi hari mulainya bisa jatuh
     * di hari apa saja - label harus dihitung dari tanggal asli, bukan diasumsikan mulai Senin.
     */
    private const DAY_NAMES_ID = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

    /**
     * Cuma 2 job yang dipantau (lihat SpacexLdnJobExecutionReportService::JOB_NAMES) - status selalu
     * SUCCESS (job yang gagal tidak masuk log Grafana sama sekali), jadi tidak ada chart status.
     */
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
            Preview::make('Job Name', 'job_name')->sortable(),
            Preview::make('Start', 'start_time')
                ->changeFill(fn($i) => $i->start_time?->format('Y-m-d H:i:s') ?? '-')
                ->sortable(),
            Preview::make('End', 'end_time')
                ->changeFill(fn($i) => $i->end_time?->format('Y-m-d H:i:s') ?? '-')
                ->sortable(),
            Preview::make('Duration', 'duration')
                ->changeFill(fn($i) => $i->duration ?: '-'),
            Preview::make('Date Parameter', 'date_parameter'),
            Preview::make('Filename', 'filename')
                ->changeFill(fn($i) => $i->filename ?: '-'),
            Preview::make('Log Type', 'log_type')
                ->changeFill(fn($i) => $i->log_type ?: '-'),
            Preview::make('Status', 'status')
                ->changeFill(fn($i) => $i->status === 'SUCCESS'
                    ? '<span style="color:#22c55e;font-weight:600;">' . e($i->status) . '</span>'
                    : '<span style="color:#ef4444;font-weight:600;">' . e((string) $i->status) . '</span>')
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
            Select::make('Job Name', 'job_name')
                ->options(['Batch_edw.sh' => 'Batch_edw.sh', 'run_edw_dblink.sh' => 'run_edw_dblink.sh'])
                ->nullable(),
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
            session(['spacexLdnJobExecutionPerPage' => $perPage]);
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
            $this->buildWeeklyDurationComparison(),
        ];
    }

    /**
     * Perbandingan durasi minggu terbaru vs minggu sebelum itu, per job. Dipatok ke tanggal
     * data TERBARU yang ada (bukan "hari ini") supaya tidak bergantung jadwal fetch - dan
     * supaya kedua jendela 7 hari selalu genap terisi kalau datanya memang lengkap 14 hari.
     *
     * Tidak terpengaruh filter tabel (DateRange/Job Name) di bawahnya - query & rentangnya
     * independen.
     */
    private function buildWeeklyDurationComparison(): Grid
    {
        $latestDate = SpacexLdnJobExecutionReport::max('trx_date');

        if ($latestDate === null) {
            return Grid::make([
                Column::make([Divider::make()])->columnSpan(12),
                Column::make([Alert::make(type: 'info')->content('Belum ada data untuk perbandingan mingguan.')])->columnSpan(12),
            ]);
        }

        $weekBEnd   = Carbon::parse($latestDate);
        $weekBStart = $weekBEnd->copy()->subDays(6);
        $weekAEnd   = $weekBStart->copy()->subDay();
        $weekAStart = $weekAEnd->copy()->subDays(6);

        $rows = SpacexLdnJobExecutionReport::query()
            ->whereBetween('trx_date', [$weekAStart->format('Y-m-d'), $weekBEnd->format('Y-m-d')])
            ->get();

        if ($rows->isEmpty()) {
            return Grid::make([
                Column::make([Divider::make()])->columnSpan(12),
                Column::make([Alert::make(type: 'info')->content('Belum cukup data untuk perbandingan mingguan (minimal 2 minggu).')])->columnSpan(12),
            ]);
        }

        // Label posisi 0..6 dihitung dari hari asli tanggal mulai minggu A (bisa jatuh di
        // hari apa saja, tidak selalu Senin). Minggu B otomatis punya urutan hari yang SAMA
        // di posisi yang sama, karena selalu digeser tepat 7 hari (1 minggu penuh) dari
        // minggu A - itu sebabnya dua minggu bisa ditumpuk pada label hari yang sama.
        $labelSequence = [];

        for ($i = 0; $i < 7; $i++) {
            $labelSequence[] = self::DAY_NAMES_ID[$weekAStart->copy()->addDays($i)->dayOfWeek];
        }

        $jobNames = $rows->pluck('job_name')->unique()->sort()->values();
        $charts   = [];

        foreach ($jobNames as $jobName) {
            $jobRows = $rows->where('job_name', $jobName);

            $weekA = array_fill_keys($labelSequence, null);
            $weekB = array_fill_keys($labelSequence, null);

            foreach ($jobRows as $r) {
                $seconds = $this->durationToSeconds($r->duration);

                if ($r->trx_date->between($weekAStart, $weekAEnd)) {
                    $weekA[$labelSequence[$weekAStart->diffInDays($r->trx_date)]] = $seconds;
                } elseif ($r->trx_date->between($weekBStart, $weekBEnd)) {
                    $weekB[$labelSequence[$weekBStart->diffInDays($r->trx_date)]] = $seconds;
                }
            }

            $charts[] = Column::make([
                LineChartMetric::make("Durasi {$jobName} (detik)")
                    ->withoutSortKeys()
                    ->series(SeriesItem::make("{$weekAStart->format('d M')} - {$weekAEnd->format('d M')}", $weekA)->line())
                    ->series(SeriesItem::make("{$weekBStart->format('d M')} - {$weekBEnd->format('d M')}", $weekB)->line()),
            ])->columnSpan(12);
        }

        return Grid::make([
            Column::make([Divider::make()])->columnSpan(12),
            Column::make([
                Alert::make(type: 'info')->content(
                    "Perbandingan mingguan (per hari-dalam-minggu): <strong>{$weekAStart->format('d M')} - {$weekAEnd->format('d M')}</strong> vs <strong>{$weekBStart->format('d M')} - {$weekBEnd->format('d M')}</strong>"
                ),
            ])->columnSpan(12),
            ...$charts,
        ]);
    }

    private function durationToSeconds(?string $duration): int
    {
        if (blank($duration)) {
            return 0;
        }

        $parts = array_pad(explode(':', $duration), 3, '0');
        [$h, $m, $s] = array_map('intval', $parts);

        return $h * 3600 + $m * 60 + $s;
    }

    protected function lastUpdateAlert(): Alert
    {
        $latest = SpacexLdnJobExecutionReport::latest('trx_date')->first();

        return $latest
            ? Alert::make(type: 'info')
                ->content("Data terakhir: <strong>{$latest->trx_date->format('d M Y')}</strong> — diupdate: {$latest->updated_at->format('d M Y H:i')}")
            : Alert::make(type: 'warning')
                ->content('Belum ada data. Gunakan Fetch Manual.');
    }
}
