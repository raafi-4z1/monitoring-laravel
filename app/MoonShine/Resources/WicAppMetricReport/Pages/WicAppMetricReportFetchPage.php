<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\WicAppMetricReport\Pages;

use App\MoonShine\Concerns\GuardsFetchPageAccess;
use App\MoonShine\Resources\WicAppMetricReport\WicAppMetricReportResource;
use App\Services\WicAppMetricReportService;
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
use MoonShine\UI\Components\FlexibleRender;
use MoonShine\UI\Components\FormBuilder;
use MoonShine\UI\Components\Heading;
use MoonShine\UI\Components\Layout\Div;
use MoonShine\UI\Components\Layout\Divider;
use MoonShine\UI\Components\Layout\Flex;
use MoonShine\UI\Components\Spinner;
use MoonShine\UI\Fields\Date;
use Throwable;

/**
 * @extends FormPage<WicAppMetricReportResource>
 */
class WicAppMetricReportFetchPage extends FormPage
{
    use GuardsFetchPageAccess;

    public function getTitle(): string
    {
        return 'Fetch Data WIC APP Metric dari Elasticsearch';
    }

    public function getBreadcrumbs(): array
    {
        return [
            $this->getResource()->getIndexPageUrl() => $this->getResource()->getTitle(),
            '#' => $this->getTitle(),
        ];
    }

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
        $this->guardResourceAccess();

        $loadingXData = '{ loading: false, init() { const t = this; const f = this.$el.querySelector(\'form\'); if (f) { f.addEventListener(\'submit\', () => { t.loading = true; }); } const r = this.$el.querySelector(\'.async-fetch-result\'); if (r) { new MutationObserver(() => { t.loading = false; }).observe(r, { childList: true, subtree: true }); } const applyTheme = () => { const dark = t.$store.darkMode.on; const card = t.$el.querySelector(\'.loading-card\'); const txt = t.$el.querySelector(\'.loading-text\'); if (card) { card.style.background = dark ? \'rgba(30,30,40,.95)\' : \'rgba(255,255,255,.98)\'; card.style.boxShadow = dark ? \'0 25px 50px rgba(0,0,0,.5)\' : \'0 25px 50px rgba(0,0,0,.15)\'; } if (txt) { txt.style.color = dark ? \'white\' : \'#1f2937\'; } }; this.$nextTick(() => { applyTheme(); }); window.addEventListener(\'darkMode:toggle\', () => { applyTheme(); }); } }';

        return [
            Heading::make('Fetch Data WIC APP Metric dari Elasticsearch'),

            Alert::make(type: 'warning')
                ->content('Gunakan form ini untuk mengambil data dari Elasticsearch berdasarkan rentang tanggal tertentu dan menyimpannya ke database. Maksimal 90 hari per fetch.'),

            Divider::make(),

            Div::make([
                Div::make([
                    Div::make([
                        Spinner::make('lg'),
                        FlexibleRender::make('<p class="loading-text" style="font-size: 0.875rem; font-weight: 600; margin-top: 0.75rem;">Sedang mengambil data, mohon tunggu...</p>'),
                    ])->class('loading-card')
                      ->customAttributes(['style' => 'display: flex; flex-direction: column; align-items: center; padding: 2.5rem; border-radius: 1rem;']),
                ])
                ->class('flex items-center justify-center')
                ->customAttributes([
                    'x-show'       => 'loading',
                    'x-cloak'      => '',
                    'x-transition' => '',
                    'style'        => 'position: fixed; top: 0; right: 0; bottom: 0; left: 0; z-index: 9999; background: rgba(0,0,0,0.6);',
                ]),

                FormBuilder::make()
                    ->asyncMethod('fetchManual')
                    ->name('wic-app-metric-fetch-form')
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

                            ActionButton::make()
                                ->icon('arrow-down-tray')
                                ->warning()
                                ->customAttributes([
                                    ':disabled' => 'loading',
                                    ':class'    => "{ 'opacity-50 cursor-not-allowed': loading }",
                                ])
                                ->dispatchEvent([
                                    AlpineJs::event(JsEvent::FORM_SUBMIT, 'wic-app-metric-fetch-form'),
                                ]),
                        ])->unwrap(),
                    ])
                    ->hideSubmit(),

                Div::make([])->class('async-fetch-result'),
            ])
            ->customAttributes([
                'x-data'                  => $loadingXData,
                '@moonshine:toast.window' => 'loading = false',
            ]),
        ];
    }

    protected function bottomLayer(): array
    {
        return [...parent::bottomLayer()];
    }

    #[AsyncMethod]
    public function fetchManual(): JsonResponse
    {
        $this->guardResourceAccess();

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
            $service = app(WicAppMetricReportService::class);
            $success = 0;
            $failed  = 0;
            $current = $from->copy();

            while ($current->lte($to)) {
                $result = $service->fetchAndStore($current->copy());
                $result ? $success++ : $failed++;
                $current->addDay();
            }

            $total   = $success + $failed;
            $this->logFetchManual($dateFrom, $dateTo, $success, $failed);
            $message = "✅ Selesai fetch <strong>{$total} hari</strong> "
                . "({$dateFrom} s/d {$dateTo}): "
                . "<strong>{$success} berhasil</strong>"
                . ($failed > 0 ? ", <strong>{$failed} gagal/kosong</strong>." : ".");

            $type = $failed > 0 ? ToastType::WARNING : ToastType::SUCCESS;
            toast($message, $type);

        } catch (\Throwable $e) {
            $message = "❌ Error: {$e->getMessage()}";
            $type    = ToastType::ERROR;
            toast($message, $type);
        }

        return JsonResponse::make()->html([
            '.async-fetch-result' => (string) Alert::make(type: $type->value)->content($message),
        ]);
    }
}
