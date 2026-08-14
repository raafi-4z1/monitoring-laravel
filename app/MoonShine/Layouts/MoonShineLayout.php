<?php

declare(strict_types=1);

namespace App\MoonShine\Layouts;

use App\MoonShine\Resources\ActivityLog\ActivityLogResource;
use App\MoonShine\Resources\AppMetric\AppMetricResource;
use App\MoonShine\Resources\EngineNotifReport\EngineNotifReportResource;
use App\MoonShine\Resources\SpacexLdnJobExecutionReport\SpacexLdnJobExecutionReportResource;
use App\MoonShine\Resources\SpacexLdnFileArchiveReport\SpacexLdnFileArchiveReportResource;
use App\MoonShine\Resources\SpacexLdnDcAppMetricReport\SpacexLdnDcAppMetricReportResource;
use App\MoonShine\Resources\SpacexLdnDcDbMetricReport\SpacexLdnDcDbMetricReportResource;
use App\MoonShine\Resources\SpacexNycJobExecutionReport\SpacexNycJobExecutionReportResource;
use App\MoonShine\Resources\MasterAplikasi\MasterAplikasiResource;
use App\MoonShine\Resources\MasterMetrik\MasterMetrikResource;
use App\MoonShine\Resources\ReportSource\ReportSourceResource;
use App\MoonShine\Resources\MoonShineUser\MoonShineUserResource;
use App\MoonShine\Resources\MoonShineUserRole\MoonShineUserRoleResource;
use App\MoonShine\Resources\MteleplusReport\MteleplusReportResource;
use App\MoonShine\Resources\TrxPbiLimitReport\TrxPbiLimitReportResource;
use App\MoonShine\Resources\TrxPbiSettlementReport\TrxPbiSettlementReportResource;
use App\MoonShine\Resources\TrxPbiLoaderReport\TrxPbiLoaderReportResource;
use App\MoonShine\Resources\SystemOnlineReport\SystemOnlineReportResource;
use App\MoonShine\Resources\WicAppMetricReport\WicAppMetricReportResource;
use App\MoonShine\Resources\WicDbMetricReport\WicDbMetricReportResource;
use App\MoonShine\Pages\RolePermissionsPage;
use App\Providers\MoonShineServiceProvider;
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
                MenuItem::make(ActivityLogResource::class, 'Activity Log')->icon('clipboard-document-list'),
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

            // Elastic reports (non-WIC) — sesuai permission role
            MenuGroup::make('Elastic', [
                MenuItem::make(EngineNotifReportResource::class, 'Engine Notif')
                    ->icon('chart-bar')
                    ->canSee($canSee(EngineNotifReportResource::class)),
                MenuItem::make(MteleplusReportResource::class, 'Mteleplus Reports')
                    ->icon('chart-bar')
                    ->canSee($canSee(MteleplusReportResource::class)),
            ])->icon('circle-stack')->canSee($anyCanSee([
                EngineNotifReportResource::class,
                MteleplusReportResource::class,
            ])),

            // WIC — gabungan semua resource yang sumbernya WIC (sebelumnya terpecah di
            // "Elastic" dan "WIC Metric"), urutan sesuai permintaan user
            MenuGroup::make('WIC', [
                MenuItem::make(TrxPbiLimitReportResource::class, 'TrxPBI Limit')
                    ->icon('banknotes')
                    ->canSee($canSee(TrxPbiLimitReportResource::class)),
                MenuItem::make(TrxPbiSettlementReportResource::class, 'TrxPBI Settlement')
                    ->icon('banknotes')
                    ->canSee($canSee(TrxPbiSettlementReportResource::class)),
                MenuItem::make(SystemOnlineReportResource::class, 'System Online')
                    ->icon('signal')
                    ->canSee($canSee(SystemOnlineReportResource::class)),
                MenuItem::make(WicAppMetricReportResource::class, 'WIC APP (HQWIC)')
                    ->icon('computer-desktop')
                    ->canSee($canSee(WicAppMetricReportResource::class)),
                MenuItem::make(WicDbMetricReportResource::class, 'WIC DB (WICADBDC)')
                    ->icon('server-stack')
                    ->canSee($canSee(WicDbMetricReportResource::class)),
                MenuItem::make(TrxPbiLoaderReportResource::class, 'Batch Job')
                    ->icon('cpu-chip')
                    ->canSee($canSee(TrxPbiLoaderReportResource::class)),
            ])->icon('building-office-2')->canSee($anyCanSee([
                TrxPbiLimitReportResource::class,
                TrxPbiSettlementReportResource::class,
                SystemOnlineReportResource::class,
                WicAppMetricReportResource::class,
                WicDbMetricReportResource::class,
                TrxPbiLoaderReportResource::class,
            ])),

            // Space-X (Reporting Luar Negeri) — Job Execution & File Archive ada di dalam
            // Server London. Server Tokyo masih kosong, placeholder untuk server lain nanti.
            //
            // Server London TIDAK perlu ->canSee(fn () => true) eksplisit: begitu isinya ada
            // (Job Execution), visibility-nya otomatis ikut permission item di dalamnya lewat
            // MenuElements::onlyVisible() (grup disembunyikan kalau semua isinya tersaring).
            // Server Tokyo dipaksa tetap tampil karena masih benar-benar kosong.
            MenuGroup::make('Space-X', [
                MenuGroup::make('Server London', [
                    MenuItem::make(SpacexLdnJobExecutionReportResource::class, 'Job Execution')
                        ->icon('cog-6-tooth')
                        ->canSee($canSee(SpacexLdnJobExecutionReportResource::class)),
                    MenuItem::make(SpacexLdnFileArchiveReportResource::class, 'File Archive')
                        ->icon('archive-box')
                        ->canSee($canSee(SpacexLdnFileArchiveReportResource::class)),
                    MenuItem::make(SpacexLdnDcAppMetricReportResource::class, 'APP Metric (DC)')
                        ->icon('cpu-chip')
                        ->canSee($canSee(SpacexLdnDcAppMetricReportResource::class)),
                    MenuItem::make(SpacexLdnDcDbMetricReportResource::class, 'DB Metric (DC)')
                        ->icon('circle-stack')
                        ->canSee($canSee(SpacexLdnDcDbMetricReportResource::class)),
                ])->icon('server'),
                MenuGroup::make('Server New York', [
                    MenuItem::make(SpacexNycJobExecutionReportResource::class, 'Job Execution')
                        ->icon('cog-6-tooth')
                        ->canSee($canSee(SpacexNycJobExecutionReportResource::class)),
                ])->icon('server'),
                MenuGroup::make('Server Tokyo', [])->icon('server')->canSee(fn () => true),
            ])->icon('rocket-launch')->canSee($anyCanSee([
                SpacexLdnJobExecutionReportResource::class,
                SpacexLdnFileArchiveReportResource::class,
                SpacexLdnDcAppMetricReportResource::class,
                SpacexLdnDcDbMetricReportResource::class,
                SpacexNycJobExecutionReportResource::class,
            ])),
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
