<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\ActivityLog\Pages;

use App\MoonShine\Resources\ActivityLog\ActivityLogResource;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Crud\JsonResponse;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\Support\AlpineJs;
use MoonShine\Support\Attributes\AsyncMethod;
use MoonShine\Support\Enums\JsEvent;
use MoonShine\UI\Components\Layout\Div;
use MoonShine\UI\Fields\DateRange;
use MoonShine\UI\Fields\Preview;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Text;

/**
 * @extends IndexPage<ActivityLogResource>
 */
class ActivityLogIndexPage extends IndexPage
{
    protected bool $isLazy = true;

    private const ACTION_LABELS = [
        'login'                 => 'Login',
        'logout'                => 'Logout',
        'login_failed'          => 'Login Gagal',
        'create'                => 'Membuat',
        'update'                => 'Mengubah',
        'delete'                => 'Menghapus',
        'fetch_manual'          => 'Fetch Manual',
        'export'                => 'Export',
    ];

    private const ACTION_COLORS = [
        'login'                  => '#22c55e',
        'logout'                 => '#6b7280',
        'login_failed'           => '#ef4444',
        'create'                 => '#22c55e',
        'update'                 => '#eab308',
        'delete'                 => '#ef4444',
        'fetch_manual'           => '#3b82f6',
        'export'                 => '#8b5cf6',
    ];

    /**
     * @return list<FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            Preview::make('Waktu', 'created_at')
                ->changeFill(fn($i) => $i->created_at?->format('Y-m-d H:i:s') ?? '-')
                ->sortable(),
            // Preview merender HTML mentah (moonshine::fields.preview pakai {!! !!}), jadi nilai
            // yang berasal dari input user WAJIB di-e() manual — user_name bisa diubah sendiri
            // lewat halaman profil, tanpa escape itu jadi stored XSS ke admin yang buka log ini.
            Preview::make('User', 'user_name')
                ->changeFill(fn($i) => e($i->user_name ?: '-')),
            Preview::make('IP', 'ip_address')
                ->changeFill(fn($i) => e($i->ip_address ?: '-')),
            Preview::make('Aksi', 'action')
                ->changeFill(function ($i) {
                    $label = e(self::ACTION_LABELS[$i->action] ?? $i->action);
                    $color = self::ACTION_COLORS[$i->action] ?? '#6b7280';

                    return "<span style=\"color:{$color};font-weight:600;\">{$label}</span>";
                })
                ->sortable(),
            Text::make('Deskripsi', 'description'),
        ];
    }

    /**
     * @return list<FieldContract>
     */
    protected function filters(): iterable
    {
        return [
            DateRange::make('Tanggal', 'created_at'),
            Select::make('Aksi', 'action')->options(self::ACTION_LABELS)->nullable(),
            Text::make('User', 'user_name'),
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
            session(['activityLogPerPage' => $perPage]);
        }

        return JsonResponse::make()->events([
            AlpineJs::event(JsEvent::TABLE_UPDATED, $this->getListComponentName()),
        ]);
    }

    protected function topLayer(): array { return [...parent::topLayer()]; }
    protected function mainLayer(): array { return [...parent::mainLayer()]; }
    protected function bottomLayer(): array { return [...parent::bottomLayer()]; }
}
