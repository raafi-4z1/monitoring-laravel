<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\EngineNotifReport\Pages;

use App\Models\EngineNotifReport;
use App\MoonShine\Resources\EngineNotifReport\EngineNotifReportResource;
use App\Services\EngineNotifReportService;
use Carbon\Carbon;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Crud\JsonResponse;
use MoonShine\Crud\QueryTags\QueryTag;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\Support\AlpineJs;
use MoonShine\Support\Attributes\AsyncMethod;
use MoonShine\Support\Enums\JsEvent;
use MoonShine\UI\Components\ActionButton;
use MoonShine\UI\Components\Alert;
use MoonShine\UI\Components\FormBuilder;
use MoonShine\UI\Components\Layout\Div;
use MoonShine\UI\Components\Layout\Divider;
use MoonShine\UI\Components\Layout\Flex;
use MoonShine\UI\Components\Metrics\Wrapped\Metric;
use MoonShine\UI\Components\Table\TableBuilder;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\DateRange;
use MoonShine\UI\Fields\ID;
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

            Preview::make('Avg Response Time', 'avg_response_time')
                ->changeFill(fn($item) => number_format((float) $item->avg_response_time, 2) . 's')
                ->sortable(),
        ];
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
     * @return list<Metric>
     */
    protected function metrics(): array
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
                AlpineJs::event(JsEvent::CARDS_UPDATED, $this->getListComponentName()),
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
        return [
            $this->lastUpdateAlert(),
            ...parent::mainLayer()
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
            $this->fetchManualForm(),
        ];
    }

    protected function lastUpdateAlert(): Alert
    {
        $latest = EngineNotifReport::latest('report_date')->first();

        return $latest
            ? Alert::make(type: 'info')
                ->content("Data terakhir: <strong>{$latest->report_date->format('d M Y')}</strong> — diupdate: {$latest->updated_at->format('d M Y H:i')}")
            : Alert::make(type: 'warning')
                ->content('Belum ada data. Gunakan Fetch Manual di bawah.');
    }

    protected function fetchManualForm(): Div
    {
        return Div::make([
            Divider::make('Fetch Manual dari Elasticsearch'),

            Alert::make(type: 'warning')
                ->content('Gunakan form ini untuk mengambil data dari Elasticsearch berdasarkan rentang tanggal tertentu dan menyimpannya ke database.'),
            Divider::make(),

            FormBuilder::make()
                ->asyncMethod('fetchManual')
                ->name('fetch-manual-form')
                ->fields([
                    Flex::make([
                        // ✅ Tanggal awal
                        Date::make('Dari Tanggal', 'fetch_date_from')
                            ->withoutWrapper()
                            ->required()
                            ->placeholder('Tanggal awal'),

                        // ✅ Tanggal akhir
                        Date::make('Sampai Tanggal', 'fetch_date_to')
                            ->withoutWrapper()
                            ->required()
                            ->placeholder('Tanggal akhir'),

                        ActionButton::make('Fetch & Simpan ke DB')
                            ->icon('arrow-down-tray')
                            ->warning()
                            ->dispatchEvent([
                                AlpineJs::event(JsEvent::FORM_SUBMIT, 'fetch-manual-form'),
                            ]),
                    ])->unwrap(),
                ])
                ->hideSubmit(),

            // ✅ Area untuk menampilkan hasil fetch
            Div::make([])->class('async-fetch-result'),
        ]);
    }

     #[AsyncMethod]
    public function fetchManual(): JsonResponse
    {
        $dateFrom = request()->input('fetch_date_from');
        $dateTo   = request()->input('fetch_date_to');

        // Validasi
        if (!$dateFrom || !$dateTo) {
            return JsonResponse::make()->html([
                '.async-fetch-result' => (string) 
                    Alert::make(type: 'error')->content('❌ Tanggal awal dan akhir wajib diisi!'),
            ]);
        }

        $from = Carbon::parse($dateFrom);
        $to   = Carbon::parse($dateTo);

        if ($from->gt($to)) {
            return JsonResponse::make()->html([
                '.async-fetch-result' => (string) 
                    Alert::make(type: 'error')->content('❌ Tanggal awal tidak boleh lebih besar dari tanggal akhir!'),
            ]);
        }

        // ✅ Batasi maksimal 90 hari sekaligus agar tidak timeout
        if ($from->diffInDays($to) > 90) {
            return JsonResponse::make()->html([
                '.async-fetch-result' => (string) 
                    Alert::make(type: 'error')->content('❌ Rentang tanggal maksimal 90 hari sekaligus!'),
            ]);
        }

        try {
            $service  = app(EngineNotifReportService::class);
            $success  = 0;
            $failed   = 0;
            $current  = $from->copy();

            // ✅ Loop setiap hari dari tanggal awal ke akhir
            while ($current->lte($to)) {
                $result = $service->fetchAndStore($current->copy());
                $result ? $success++ : $failed++;
                $current->addDay();
            }

            $total   = $success + $failed;
            $message = "✅ Selesai fetch <strong>{$total} hari</strong> "
                . "({$dateFrom} s/d {$dateTo}): "
                . "<strong>{$success} berhasil</strong>"
                . ($failed > 0 ? ", <strong class='text-red-500'>{$failed} gagal</strong>." : ".");

            $type = $failed > 0 ? 'warning' : 'success';

        } catch (\Throwable $e) {
            $message = "❌ Error: {$e->getMessage()}";
            $type    = 'error';
        }

        return JsonResponse::make()->html([
            '.async-fetch-result' => (string) Alert::make(type: $type)->content($message),
        ]);
    }
}