<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\EngineNotifReport\Pages;

use App\MoonShine\Resources\EngineNotifReport\EngineNotifReportResource;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Crud\JsonResponse;
use MoonShine\Crud\QueryTags\QueryTag;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\Support\AlpineJs;
use MoonShine\Support\Attributes\AsyncMethod;
use MoonShine\Support\Enums\JsEvent;
use MoonShine\UI\Components\Layout\Div;
use MoonShine\UI\Components\Metrics\Wrapped\Metric;
use MoonShine\UI\Components\Table\TableBuilder;
use MoonShine\UI\Fields\Date;
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
            ID::make()->sortable(),

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
            Date::make('Report Date', 'report_date'),
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
            ...parent::bottomLayer()
        ];
    }
}