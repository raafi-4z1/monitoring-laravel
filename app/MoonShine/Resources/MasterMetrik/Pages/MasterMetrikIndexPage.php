<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\MasterMetrik\Pages;

use App\Models\MasterMetrik;
use App\MoonShine\Resources\MasterMetrik\MasterMetrikResource;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Crud\JsonResponse;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\Laravel\QueryTags\QueryTag;
use MoonShine\Support\AlpineJs;
use MoonShine\Support\Attributes\AsyncMethod;
use MoonShine\Support\Enums\JsEvent;
use MoonShine\Support\ListOf;
use MoonShine\UI\Components\ActionButton;
use MoonShine\UI\Components\Layout\Div;
use MoonShine\UI\Components\Table\TableBuilder;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Preview;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Text;
use Throwable;

/**
 * @extends IndexPage<MasterMetrikResource>
 */
class MasterMetrikIndexPage extends IndexPage
{
    protected bool $isLazy = true;

    protected function fields(): iterable
    {
        return [
            ID::make()->sortable(),
            Text::make('Nama Metrik', 'nama')->sortable(),
            Preview::make('Satuan Default', 'satuan_default'),
            Text::make('Keterangan', 'keterangan'),
        ];
    }

    protected function filters(): iterable
    {
        return [
            Text::make('Nama Metrik', 'nama'),
        ];
    }

    protected function queryTags(): array
    {
        return [
            QueryTag::make('Aktif', fn ($q) => $q->whereNull('deleted_at'))->default(),
            QueryTag::make('Sampah', fn ($q) => $q->withTrashed()->whereNotNull('deleted_at')),
        ];
    }

    protected function itemButtons(): ListOf
    {
        return parent::itemButtons()->prepend(
            ActionButton::make('Pulihkan')
                ->method('restore')
                ->icon('arrow-uturn-left')
                ->withConfirm(message: 'Pulihkan metrik ini?')
                ->success()
                ->canSee(fn ($item) => $item instanceof MasterMetrik && $item->trashed()),
        );
    }

    #[AsyncMethod]
    public function restore(mixed $item): JsonResponse
    {
        MasterMetrik::withTrashed()->findOrFail((int) request()->input('itemID'))->restore();

        return JsonResponse::make()
            ->toast('Berhasil dipulihkan', 'success')
            ->events([AlpineJs::event(JsEvent::TABLE_UPDATED, $this->getListComponentName())]);
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
            session(['masterMetrikPerPage' => $perPage]);
        }

        return JsonResponse::make()->events([
            AlpineJs::event(JsEvent::TABLE_UPDATED, $this->getListComponentName()),
        ]);
    }

    protected function topLayer(): array { return [...parent::topLayer()]; }
    protected function mainLayer(): array { return [...parent::mainLayer()]; }
    protected function bottomLayer(): array { return [...parent::bottomLayer()]; }
}
