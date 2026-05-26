<?php

declare(strict_types=1);

namespace App\MoonShine\Pages;

use App\Services\ElasticsearchService;
use Illuminate\Pagination\LengthAwarePaginator;
use MoonShine\Contracts\Core\DependencyInjection\CrudRequestContract;
use MoonShine\Crud\JsonResponse;
use MoonShine\Crud\TypeCasts\PaginatorCaster;
use MoonShine\Laravel\Pages\Page;
use MoonShine\Support\AlpineJs;
use MoonShine\Support\Attributes\AsyncMethod;
use MoonShine\Support\Enums\JsEvent;
use MoonShine\UI\Components\ActionButton;
use MoonShine\UI\Components\FormBuilder;
use MoonShine\UI\Components\Heading;
use MoonShine\UI\Components\Layout\Div;
use MoonShine\UI\Components\Layout\Divider;
use MoonShine\UI\Components\Layout\Flex;
use MoonShine\UI\Components\Table\TableBuilder;
use MoonShine\UI\Fields\DateRange;
use MoonShine\UI\Fields\Preview;

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
        return [
            Heading::make('Engine Notification Report'),

            Div::make([
                FormBuilder::make()
                    ->asyncMethod('filterReport')
                    ->name('report-filter-form')
                    ->fields([
                        Flex::make([
                            DateRange::make('Tanggal', 'date')
                                ->withoutWrapper(),

                            ActionButton::make('Terapkan')
                                ->dispatchEvent([
                                    AlpineJs::event(JsEvent::FORM_SUBMIT, 'report-filter-form'),
                                ]),
                        ])->unwrap(),
                    ])
                    ->hideSubmit(),

                Divider::make(),

                Div::make([
                    $this->reportTable(),
                ])->class('async-report-table'),
            ]),
        ];
    }

    protected function reportTable(
        string $dateFrom = null,
        string $dateTo = null,
    ): TableBuilder {
        $dateFrom = $dateFrom ?? now()->subDays(14)->format('Y-m-d');
        $dateTo   = $dateTo   ?? now()->format('Y-m-d');

        $es = new ElasticsearchService();

        [$mvrkS, $mvrkF]   = $es->parseStatusBuckets($es->queryBySendingType(4, $dateFrom, $dateTo));
        [$smsS,  $smsF]    = $es->parseStatusBuckets($es->queryBySendingType(1, $dateFrom, $dateTo));
        [$emailS, $emailF] = $es->parseStatusBuckets($es->queryBySendingType(2, $dateFrom, $dateTo));
        $avgRt             = $es->parseAvgRtBuckets($es->queryAvgResponseTime($dateFrom, $dateTo));

        $allDates = collect(array_keys(array_merge($mvrkS, $mvrkF, $smsS, $smsF, $emailS, $emailF)))
            ->unique()
            ->sort()
            ->values();

        $allItems = $allDates->map(function ($date, $i) use ($mvrkS, $mvrkF, $smsS, $smsF, $emailS, $emailF, $avgRt) {
            $ms = $mvrkS[$date]  ?? 0;
            $mf = $mvrkF[$date]  ?? 0;
            $ss = $smsS[$date]   ?? 0;
            $sf = $smsF[$date]   ?? 0;
            $es = $emailS[$date] ?? 0;
            $ef = $emailF[$date] ?? 0;

            return [
                'no'            => $i + 1,
                'date'          => $date,
                'mvrk_success'  => $ms,
                'mvrk_fail'     => $mf,
                'mvrk_total'    => $ms + $mf,
                'sms_success'   => $ss,
                'sms_fail'      => $sf,
                'sms_total'     => $ss + $sf,
                'email_success' => $es,
                'email_fail'    => $ef,
                'email_total'   => $es + $ef,
                'total_success' => $ms + $ss + $es,
                'total_fail'    => $mf + $sf + $ef,
                'avg_rt'        => number_format($avgRt[$date] ?? 0, 2) . 's',
            ];
        });

        // Sort
        $sortRaw       = request()->string('sort', 'date')->toString();
        $sortDirection = str_starts_with($sortRaw, '-') ? 'desc' : 'asc';
        $sortField     = ltrim($sortRaw, '-');
        $allowedSorts  = [
            'date', 'mvrk_success', 'mvrk_fail', 'mvrk_total',
            'sms_success', 'sms_fail', 'sms_total',
            'email_success', 'email_fail', 'email_total',
            'total_success', 'total_fail', 'avg_rt',
        ];
        $sortField = in_array($sortField, $allowedSorts) ? $sortField : 'date';

        $allItems = $sortDirection === 'asc'
            ? $allItems->sortBy($sortField)->values()
            : $allItems->sortByDesc($sortField)->values();

        $allItems = $allItems->values()->map(function ($item, $i) {
            $item['no'] = $i + 1;
            return $item;
        });

        // ✅ Pagination fixed 10
        $perPage     = 10;
        $currentPage = request()->integer('r_page', 1);
        $total       = $allItems->count();
        $offset      = ($currentPage - 1) * $perPage;
        $pagedItems  = $allItems->slice($offset, $perPage)->values()->toArray();

        $paginator = new LengthAwarePaginator(
            items:       $pagedItems,
            total:       $total,
            perPage:     $perPage,
            currentPage: $currentPage,
            options:     ['path' => request()->url(), 'pageName' => 'r_page'],
        );

        $castedPaginator = (new PaginatorCaster(
            $paginator->appends(
                collect(request()->only(['method', 'sort', 'date']))
                    ->filter()
                    ->toArray()
            )->toArray(),
            $pagedItems,
        ))->cast();

        return TableBuilder::make(
            fields: [
                Preview::make('No',            'no'),
                Preview::make('Date',          'date')->sortable(),
                Preview::make('MVRK Success',  'mvrk_success')->sortable(),
                Preview::make('MVRK Fail',     'mvrk_fail')->sortable(),
                Preview::make('MVRK Total',    'mvrk_total')->sortable(),
                Preview::make('SMS Success',   'sms_success')->sortable(),
                Preview::make('SMS Fail',      'sms_fail')->sortable(),
                Preview::make('SMS Total',     'sms_total')->sortable(),
                Preview::make('Email Success', 'email_success')->sortable(),
                Preview::make('Email Fail',    'email_fail')->sortable(),
                Preview::make('Email Total',   'email_total')->sortable(),
                Preview::make('Total Success', 'total_success')->sortable(),
                Preview::make('Total Fail',    'total_fail')->sortable(),
                Preview::make('Avg RT (s)',     'avg_rt'),
            ],
        )
        ->withNotFound()
        ->simple()
        ->items($castedPaginator);
    }

    #[AsyncMethod]
    public function filterReport(CrudRequestContract $request): JsonResponse
    {
        $dateFrom = $request->input('date.from');
        $dateTo   = $request->input('date.to');

        return JsonResponse::make()->html([
            '.async-report-table' => (string) $this->reportTable($dateFrom, $dateTo),
        ]);
    }
}