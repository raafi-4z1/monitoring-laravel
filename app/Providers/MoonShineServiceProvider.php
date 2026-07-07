<?php

declare(strict_types=1);

namespace App\Providers;

use App\MoonShine\Resources\AppMetric\AppMetricResource;
use App\MoonShine\Resources\EngineNotifReport\EngineNotifReportResource;
use App\MoonShine\Resources\EngineNotifReport\Pages\EngineNotifReportFetchPage;
use App\MoonShine\Resources\MasterAplikasi\MasterAplikasiResource;
use App\MoonShine\Resources\MasterMetrik\MasterMetrikResource;
use App\MoonShine\Resources\ReportSource\ReportSourceResource;
use App\MoonShine\Resources\MoonShineUser\MoonShineUserResource;
use App\MoonShine\Resources\MoonShineUserRole\MoonShineUserRoleResource;
use App\MoonShine\Resources\MteleplusReport\MteleplusReportResource;
use App\MoonShine\Resources\MteleplusReport\Pages\MteleplusReportFetchPage;
use App\MoonShine\Resources\TrxPbiLimitReport\TrxPbiLimitReportResource;
use App\MoonShine\Resources\TrxPbiLimitReport\Pages\TrxPbiLimitReportFetchPage;
use App\MoonShine\Resources\TrxPbiSettlementReport\TrxPbiSettlementReportResource;
use App\MoonShine\Resources\TrxPbiSettlementReport\Pages\TrxPbiSettlementReportFetchPage;
use App\MoonShine\Resources\WicDbMetricReport\WicDbMetricReportResource;
use App\MoonShine\Resources\WicDbMetricReport\Pages\WicDbMetricReportFetchPage;
use App\MoonShine\Resources\WicAppMetricReport\WicAppMetricReportResource;
use App\MoonShine\Resources\WicAppMetricReport\Pages\WicAppMetricReportFetchPage;
use Illuminate\Support\ServiceProvider;
use MoonShine\Contracts\Core\DependencyInjection\CoreContract;
use MoonShine\Laravel\DependencyInjection\MoonShineConfigurator;
use MoonShine\Laravel\Models\MoonshineUser;
use MoonShine\Support\Enums\Ability;


class MoonShineServiceProvider extends ServiceProvider
{
    // Resources yang hanya bisa diakses role Admin (id=1)
    private const ADMIN_ONLY_RESOURCES = [
        MoonShineUserResource::class,
        MoonShineUserRoleResource::class,
        MasterAplikasiResource::class,
        MasterMetrikResource::class,
        ReportSourceResource::class,
    ];

    /**
     * @param  CoreContract<MoonShineConfigurator>  $core
     */
    public function boot(CoreContract $core): void
    {
        $core
            ->resources([
                MoonShineUserResource::class,
                MoonShineUserRoleResource::class,
                MasterAplikasiResource::class,
                MasterMetrikResource::class,
                EngineNotifReportResource::class,
                MteleplusReportResource::class,
                TrxPbiLimitReportResource::class,
                TrxPbiSettlementReportResource::class,
                AppMetricResource::class,
                ReportSourceResource::class,
                WicDbMetricReportResource::class,
                WicAppMetricReportResource::class,
            ])
            ->pages([
                ...$core->getConfig()->getPages(),
                EngineNotifReportFetchPage::class,
                MteleplusReportFetchPage::class,
                TrxPbiLimitReportFetchPage::class,
                TrxPbiSettlementReportFetchPage::class,
                WicDbMetricReportFetchPage::class,
                WicAppMetricReportFetchPage::class,
            ]);

        $core->getConfig()->authorizationRules(
            static function ($resource, $user, Ability $ability, $item): bool {
                if (! $user instanceof MoonshineUser) {
                    return true;
                }

                if ($user->isSuperUser()) {
                    return true;
                }

                return ! in_array(get_class($resource), self::ADMIN_ONLY_RESOURCES, true);
            }
        );
    }
}
