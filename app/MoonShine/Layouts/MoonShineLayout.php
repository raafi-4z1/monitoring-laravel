<?php

declare(strict_types=1);

namespace App\MoonShine\Layouts;

use App\MoonShine\Resources\AppMetric\AppMetricResource;
use App\MoonShine\Resources\EngineNotifReport\EngineNotifReportResource;
use App\MoonShine\Resources\MoonShineUser\MoonShineUserResource;
use App\MoonShine\Resources\MoonShineUserRole\MoonShineUserRoleResource;
use App\MoonShine\Resources\MteleplusReport\MteleplusReportResource;
use MoonShine\ColorManager\ColorManager;
use MoonShine\ColorManager\Palettes\PurplePalette;
use MoonShine\Contracts\ColorManager\ColorManagerContract;
use MoonShine\Contracts\ColorManager\PaletteContract;
use MoonShine\Laravel\Layouts\AppLayout;
use MoonShine\MenuManager\MenuGroup;
use MoonShine\MenuManager\MenuItem;


final class MoonShineLayout extends AppLayout
{
    /**
     * @var null|class-string<PaletteContract>
     */
    protected ?string $palette = PurplePalette::class;

    protected function assets(): array
    {
        return [
            ...parent::assets(),
        ];
    }

    protected function menu(): array
    {
        $isAdmin = static fn (): bool =>
            auth(moonshineConfig()->getGuard())->user()?->isSuperUser() ?? false;

        return [
            // Manajemen users & roles — hanya admin
            MenuGroup::make('Manajemen', [
                MenuItem::make(MoonShineUserResource::class)->icon('users'),
                MenuItem::make(MoonShineUserRoleResource::class)->icon('shield-check'),
            ])->icon('users')->canSee($isAdmin),

            // Elastic reports — semua role
            MenuGroup::make('Elastic', [
                MenuItem::make(EngineNotifReportResource::class, 'Engine Notif')
                    ->Icon('chart-bar'),
                MenuItem::make(MteleplusReportResource::class, 'Mteleplus Reports')
                    ->Icon('chart-bar'),
            ])->icon('circle-stack'),

            // App Metrics — semua role
            MenuItem::make(AppMetricResource::class, 'App Metric')
                ->Icon('chart-bar'),
        ];
    }

    /**
     * @param ColorManager $colorManager
     */
    protected function colors(ColorManagerContract $colorManager): void
    {
        parent::colors($colorManager);

        // $colorManager->primary('#00000');
    }
}
