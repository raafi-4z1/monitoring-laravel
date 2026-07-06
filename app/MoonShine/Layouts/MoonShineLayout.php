<?php

declare(strict_types=1);

namespace App\MoonShine\Layouts;

use App\MoonShine\Resources\AppMetric\AppMetricResource;
use App\MoonShine\Resources\EngineNotifReport\EngineNotifReportResource;
use App\MoonShine\Resources\MasterAplikasi\MasterAplikasiResource;
use App\MoonShine\Resources\MasterMetrik\MasterMetrikResource;
use App\MoonShine\Resources\ReportSource\ReportSourceResource;
use App\MoonShine\Resources\MoonShineUser\MoonShineUserResource;
use App\MoonShine\Resources\MoonShineUserRole\MoonShineUserRoleResource;
use App\MoonShine\Resources\MteleplusReport\MteleplusReportResource;
use App\MoonShine\Resources\TrxPbiLimitReport\TrxPbiLimitReportResource;
use App\MoonShine\Resources\TrxPbiSettlementReport\TrxPbiSettlementReportResource;
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
            // Manajemen user — hanya admin
            MenuGroup::make('Manajemen', [
                MenuItem::make(MoonShineUserResource::class)->icon('users'),
                MenuItem::make(MoonShineUserRoleResource::class)->icon('shield-check'),
            ])->icon('cog-6-tooth')->canSee($isAdmin),

            // App Metric — semua role; master hanya admin
            MenuGroup::make('App Metric', [
                MenuItem::make(AppMetricResource::class, 'Data Metric')->icon('chart-bar'),
                MenuItem::make(MasterAplikasiResource::class)->icon('server')
                    ->canSee($isAdmin),
                MenuItem::make(MasterMetrikResource::class)->icon('beaker')
                    ->canSee($isAdmin),
                MenuItem::make(ReportSourceResource::class, 'Report Sources')->icon('document-text')
                    ->canSee($isAdmin),
            ])->icon('presentation-chart-line'),

            // Elastic reports — semua role
            MenuGroup::make('Elastic', [
                MenuItem::make(EngineNotifReportResource::class, 'Engine Notif')
                    ->icon('chart-bar'),
                MenuItem::make(MteleplusReportResource::class, 'Mteleplus Reports')
                    ->icon('chart-bar'),
                MenuItem::make(TrxPbiLimitReportResource::class, 'TrxPBI Limit')
                    ->icon('banknotes'),
                MenuItem::make(TrxPbiSettlementReportResource::class, 'TrxPBI Settlement')
                    ->icon('banknotes'),
            ])->icon('circle-stack'),
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
