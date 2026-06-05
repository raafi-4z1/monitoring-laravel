<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\EngineNotifReport\Pages;

use App\MoonShine\Resources\EngineNotifReport\EngineNotifReportResource;
use App\Services\EngineNotifReportService;
use Carbon\Carbon;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Crud\JsonResponse;
use MoonShine\Laravel\Pages\Crud\FormPage;
use MoonShine\Support\AlpineJs;
use MoonShine\Support\Attributes\AsyncMethod;
use MoonShine\Support\Enums\JsEvent;
use MoonShine\Support\Enums\ToastType;
use MoonShine\UI\Components\ActionButton;
use MoonShine\UI\Components\Alert;
use MoonShine\UI\Components\FormBuilder;
use MoonShine\UI\Components\Heading;
use MoonShine\UI\Components\Layout\Div;
use MoonShine\UI\Components\Layout\Divider;
use MoonShine\UI\Components\Layout\Flex;
use MoonShine\UI\Fields\Date;
use Throwable;


/**
 * @extends FormPage<EngineNotifReportResource>
 */
class EngineNotifReportFetchPage extends FormPage
{
    public function getTitle(): string
    {
        return 'Fetch Data dari Elasticsearch';
    }

    public function getBreadcrumbs(): array
    {
        return [
            $this->getResource()->getIndexPageUrl() => $this->getResource()->getTitle(),
            '#' => $this->getTitle(),
        ];
    }

    /**
     * @return list<ComponentContract>
     * @throws Throwable
     */
    protected function topLayer(): array
    {
        return [];
    }

    /**
     * @return list<ComponentContract>
     * @throws Throwable
     */
    protected function mainLayer(): array
    {
        return [
            Heading::make('Fetch Data dari Elasticsearch'),

            Alert::make(type: 'warning')
                ->content('Gunakan form ini untuk mengambil data dari Elasticsearch berdasarkan rentang tanggal tertentu dan menyimpannya ke database. Maksimal 90 hari per fetch.'),
            Alert::make(type: 'info')
                ->content('⏳ Proses fetch bisa memakan waktu beberapa saat tergantung rentang tanggal. Mohon tunggu hingga muncul notifikasi selesai.'),

            Divider::make(),
            
            FormBuilder::make()
                ->asyncMethod('fetchManual')
                ->name('fetch-manual-form')
                ->fields([
                    Flex::make([
                        Date::make('Dari Tanggal', 'fetch_date_from')
                            ->withoutWrapper()
                            ->required()
                            ->placeholder('Tanggal awal'),

                        Date::make('Sampai Tanggal', 'fetch_date_to')
                            ->withoutWrapper()
                            ->required()
                            ->placeholder('Tanggal akhir'),

                        ActionButton::make('Fetch & Simpan ke DB')
                            ->icon('arrow-down-tray')
                            ->warning()
                            ->customAttributes([
                                // ✅ Tombol berubah saat loading
                                'x-data'     => '{ loading: false }',
                                '@click'     => 'loading = true',
                                ':disabled'  => 'loading',
                                ':class'     => "{ 'opacity-50 cursor-not-allowed': loading }",
                            ])
                            ->dispatchEvent([
                                AlpineJs::event(JsEvent::FORM_SUBMIT, 'fetch-manual-form'),
                            ]),
                    ])->unwrap(),
                ])
                ->hideSubmit(),

            Div::make([])->class('async-fetch-result'),
        ];
    }

    /**
     * @return list<ComponentContract>
     * @throws Throwable
     */
    protected function bottomLayer(): array
    {
        return [];
    }

    #[AsyncMethod]
    public function fetchManual(): JsonResponse
    {
        $dateFrom = request()->input('fetch_date_from');
        $dateTo   = request()->input('fetch_date_to');

        if (!$dateFrom || !$dateTo) {
            return JsonResponse::make()->html([
                '.async-fetch-result' => (string) Alert::make(type: 'error')
                    ->content('❌ Tanggal awal dan akhir wajib diisi!'),
            ]);
        }

        $from = Carbon::parse($dateFrom);
        $to   = Carbon::parse($dateTo);

        if ($from->gt($to)) {
            return JsonResponse::make()->html([
                '.async-fetch-result' => (string) Alert::make(type: 'error')
                    ->content('❌ Tanggal awal tidak boleh lebih besar dari tanggal akhir!'),
            ]);
        }

        if ($from->diffInDays($to) > 90) {
            return JsonResponse::make()->html([
                '.async-fetch-result' => (string) Alert::make(type: 'error')
                    ->content('❌ Rentang tanggal maksimal 90 hari sekaligus!'),
            ]);
        }

        try {
            $service = app(EngineNotifReportService::class);
            $success = 0;
            $failed  = 0;
            $current = $from->copy();

            while ($current->lte($to)) {
                $result = $service->fetchAndStore($current->copy());
                $result ? $success++ : $failed++;
                $current->addDay();
            }

            $total   = $success + $failed;
            $message = "✅ Selesai fetch <strong>{$total} hari</strong> "
                . "({$dateFrom} s/d {$dateTo}): "
                . "<strong>{$success} berhasil</strong>"
                . ($failed > 0 ? ", <strong>{$failed} gagal</strong>." : ".");

            $type = $failed > 0 ? ToastType::WARNING : ToastType::SUCCESS;

            // ✅ Toast notifikasi selesai
            toast($message, $type);

        } catch (\Throwable $e) {
            $message = "❌ Error: {$e->getMessage()}";
            $type    = ToastType::ERROR;

            toast($message, $type);
        }

        return JsonResponse::make()->html([
            '.async-fetch-result' => (string) Alert::make(type: $type->value)
                ->content($message),
        ]);
    }
}
