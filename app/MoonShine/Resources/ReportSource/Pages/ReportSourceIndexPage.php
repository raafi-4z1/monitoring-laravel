<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\ReportSource\Pages;

use App\MoonShine\Resources\ReportSource\ReportSourceResource;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Crud\JsonResponse;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\Support\AlpineJs;
use MoonShine\Support\Attributes\AsyncMethod;
use MoonShine\Support\Enums\JsEvent;
use MoonShine\UI\Components\Layout\Div;
use MoonShine\UI\Components\Table\TableBuilder;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Text;

/**
 * @extends IndexPage<ReportSourceResource>
 */
class ReportSourceIndexPage extends IndexPage
{
    protected bool $isLazy = true;

    /**
     * @return list<FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            ID::make()->sortable(),
            Text::make('Service Name',        'service_name')->sortable(),
            Text::make('App ID',              'app_id')->sortable(),
            Text::make('Data Source',         'data_source')->sortable(),
            Text::make('Data Source Name',    'data_source_name'),
            Text::make('Service Integrator',  'service_integrator')->sortable(),
            Text::make('Kode Prefix',         'kode_prefix')->sortable(),
        ];
    }

    protected function filters(): iterable
    {
        return [
            Text::make('Service Name',       'service_name'),
            Text::make('Data Source',        'data_source'),
            Text::make('Service Integrator', 'service_integrator'),
        ];
    }

    /**
     * @param TableBuilder $component
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
            session(['reportSourcePerPage' => $perPage]);
        }

        return JsonResponse::make()->events([
            AlpineJs::event(JsEvent::TABLE_UPDATED, $this->getListComponentName()),
        ]);
    }

    protected function topLayer(): array    { return [...parent::topLayer()]; }
    protected function mainLayer(): array   { return [...parent::mainLayer()]; }
    protected function bottomLayer(): array { return [...parent::bottomLayer()]; }
}
