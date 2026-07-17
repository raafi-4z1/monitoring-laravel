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
use App\MoonShine\Resources\TrxPbiLoaderReport\TrxPbiLoaderReportResource;
use App\MoonShine\Resources\WicAppMetricReport\WicAppMetricReportResource;
use App\MoonShine\Resources\WicDbMetricReport\WicDbMetricReportResource;
use App\MoonShine\Pages\RolePermissionsPage;
use App\Providers\MoonShineServiceProvider;
use MoonShine\AssetManager\InlineCss;
use MoonShine\ColorManager\ColorManager;
use MoonShine\ColorManager\Palettes\OrangePalette;
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
    protected ?string $palette = OrangePalette::class;

    protected function assets(): array
    {
        return [
            ...parent::assets(),
            InlineCss::make(<<<'CSS'
                :root:not(.dark) {
                    --monitoring-orange: #f97316;
                    --monitoring-orange-dark: #ea580c;
                    --monitoring-orange-soft: #fed7aa;
                    --monitoring-text: #1f2937;
                }

                body {
                    background: #fff;
                    color: var(--monitoring-text);
                }

                .layout-page,
                .layout-content,
                .authentication {
                    background: #fff;
                }

                .layout-menu,
                .layout-menu-horizontal,
                .layout-bottom-bar-content,
                .authentication-content,
                .box,
                .card {
                    background: #fff;
                }

                .layout-menu,
                .authentication-content,
                .box,
                .card {
                    border-color: var(--monitoring-orange-soft);
                }

                .table thead,
                table thead {
                    background: var(--monitoring-orange);
                    color: #fff;
                }

                .btn-primary,
                [type='submit'],
                button.primary {
                    background-color: var(--monitoring-orange);
                    border-color: var(--monitoring-orange);
                    color: #fff;
                }

                .btn-primary:hover,
                [type='submit']:hover,
                button.primary:hover {
                    background-color: var(--monitoring-orange-dark);
                    border-color: var(--monitoring-orange-dark);
                    color: #fff;
                }

                a,
                .link,
                .menu-icon,
                .icon-wrapper {
                    color: var(--monitoring-orange);
                }
            CSS),
        ];
    }

    protected function menu(): array
    {
        $isAdmin = static fn (): bool =>
            auth(moonshineConfig()->getGuard())->user()?->isSuperUser() ?? false;

        $canSee = static fn (string $resourceClass): \Closure => static fn (): bool
            => MoonShineServiceProvider::canAccessResource($resourceClass);

        $anyCanSee = static fn (array $resourceClasses): \Closure => static function () use ($resourceClasses): bool {
            foreach ($resourceClasses as $resourceClass) {
                if (MoonShineServiceProvider::canAccessResource($resourceClass)) {
                    return true;
                }
            }

            return false;
        };

        return [
            // Manajemen user — hanya admin
            MenuGroup::make('Manajemen', [
                MenuItem::make(MoonShineUserResource::class)->icon('users'),
                MenuItem::make(MoonShineUserRoleResource::class)->icon('shield-check'),
                MenuItem::make(RolePermissionsPage::class, 'Hak Akses Role')->icon('lock-closed'),
            ])->icon('cog-6-tooth')->canSee($isAdmin),

            // App Metric — sesuai permission role; master hanya admin
            MenuGroup::make('App Metric', [
                MenuItem::make(AppMetricResource::class, 'Data Metric')->icon('chart-bar')
                    ->canSee($canSee(AppMetricResource::class)),
                MenuItem::make(MasterAplikasiResource::class)->icon('server')
                    ->canSee($isAdmin),
                MenuItem::make(MasterMetrikResource::class)->icon('beaker')
                    ->canSee($isAdmin),
                MenuItem::make(ReportSourceResource::class, 'Report Sources')->icon('document-text')
                    ->canSee($isAdmin),
            ])->icon('presentation-chart-line')->canSee($anyCanSee([
                AppMetricResource::class,
                MasterAplikasiResource::class,
                MasterMetrikResource::class,
                ReportSourceResource::class,
            ])),

            // Elastic reports — sesuai permission role
            MenuGroup::make('Elastic', [
                MenuItem::make(EngineNotifReportResource::class, 'Engine Notif')
                    ->icon('chart-bar')
                    ->canSee($canSee(EngineNotifReportResource::class)),
                MenuItem::make(MteleplusReportResource::class, 'Mteleplus Reports')
                    ->icon('chart-bar')
                    ->canSee($canSee(MteleplusReportResource::class)),
                MenuItem::make(TrxPbiLimitReportResource::class, 'TrxPBI Limit')
                    ->icon('banknotes')
                    ->canSee($canSee(TrxPbiLimitReportResource::class)),
                MenuItem::make(TrxPbiSettlementReportResource::class, 'TrxPBI Settlement')
                    ->icon('banknotes')
                    ->canSee($canSee(TrxPbiSettlementReportResource::class)),
                MenuItem::make(TrxPbiLoaderReportResource::class, 'TrxPBI Loader')
                    ->icon('cpu-chip')
                    ->canSee($canSee(TrxPbiLoaderReportResource::class)),
            ])->icon('circle-stack')->canSee($anyCanSee([
                EngineNotifReportResource::class,
                MteleplusReportResource::class,
                TrxPbiLimitReportResource::class,
                TrxPbiSettlementReportResource::class,
                TrxPbiLoaderReportResource::class,
            ])),

            // WIC Metric — sesuai permission role
            MenuGroup::make('WIC Metric', [
                MenuItem::make(WicDbMetricReportResource::class, 'WIC DB (WICADBDC)')
                    ->icon('server-stack')
                    ->canSee($canSee(WicDbMetricReportResource::class)),
                MenuItem::make(WicAppMetricReportResource::class, 'WIC APP (HQWIC)')
                    ->icon('computer-desktop')
                    ->canSee($canSee(WicAppMetricReportResource::class)),
            ])->icon('cpu-chip')->canSee($anyCanSee([
                WicDbMetricReportResource::class,
                WicAppMetricReportResource::class,
            ])),
        ];
    }

    /**
     * @param ColorManager $colorManager
     */
    protected function colors(ColorManagerContract $colorManager): void
    {
        parent::colors($colorManager);

        $colorManager
            ->background('#ffffff', '#ffffff')
            ->text('#1f2937')
            ->primary('#f97316', '#ffffff')
            ->secondary('#fed7aa', '#1f2937')
            ->button('#f97316', '#ffffff', '#ea580c', '#ffffff', '#fed7aa', '#1f2937')
            ->borders('#fed7aa')
            ->menu('#fed7aa', '#1f2937', '#fed7aa')
            ->card('#ffffff', '#1f2937')
            ->box('#ffffff', '#1f2937')
            ->table('#ffffff', '#f97316')
            ->form('#ffffff', '#1f2937', '#f97316')
            ->scrollbar('#fb923c', '#f97316');
    }
}
