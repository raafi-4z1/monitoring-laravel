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
use App\MoonShine\Resources\TrxPbiLoaderReport\TrxPbiLoaderReportResource;
use App\MoonShine\Resources\TrxPbiLoaderReport\Pages\TrxPbiLoaderReportFetchPage;
use App\MoonShine\Resources\SystemOnlineReport\SystemOnlineReportResource;
use App\MoonShine\Resources\SystemOnlineReport\Pages\SystemOnlineReportFetchPage;
use App\MoonShine\Resources\WicDbMetricReport\WicDbMetricReportResource;
use App\MoonShine\Resources\WicDbMetricReport\Pages\WicDbMetricReportFetchPage;
use App\MoonShine\Resources\WicAppMetricReport\WicAppMetricReportResource;
use App\MoonShine\Resources\WicAppMetricReport\Pages\WicAppMetricReportFetchPage;
use App\MoonShine\Pages\RolePermissionsPage;
use App\Models\ResourcePermission;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\ServiceProvider;
use MoonShine\Contracts\Core\DependencyInjection\CoreContract;
use MoonShine\Laravel\DependencyInjection\MoonShineConfigurator;
use MoonShine\Laravel\Models\MoonshineUser;
use MoonShine\Support\Enums\Ability;


class MoonShineServiceProvider extends ServiceProvider
{
    // Resources yang hanya bisa diakses role Admin (id=1) - guardrail permanen, sengaja tidak dipindah ke DB
    public const ADMIN_ONLY_RESOURCES = [
        MoonShineUserResource::class,
        MoonShineUserRoleResource::class,
        MasterAplikasiResource::class,
        MasterMetrikResource::class,
        ReportSourceResource::class,
    ];

    private const MANAGEABLE_RESOURCES_CACHE_KEY = 'resource_permissions.manageable_classes';

    /**
     * Resources yang aksesnya bisa diatur per role (dikelola dari DB tabel resource_permissions,
     * lihat RolePermissionsPage). Di-cache karena dipanggil pada setiap pengecekan otorisasi.
     *
     * @return list<string>
     */
    public static function manageableResourceClasses(): array
    {
        return Cache::rememberForever(
            self::MANAGEABLE_RESOURCES_CACHE_KEY,
            static fn (): array => ResourcePermission::pluck('resource_class')->all(),
        );
    }

    public static function forgetManageableResourcesCache(): void
    {
        Cache::forget(self::MANAGEABLE_RESOURCES_CACHE_KEY);
    }

    /**
     * Dipakai baik oleh authorizationRules (blokir akses) maupun menu sidebar (sembunyikan menu),
     * supaya keduanya selalu konsisten.
     */
    public static function canAccessResource(string $resourceClass): bool
    {
        $user = auth(moonshineConfig()->getGuard())->user();

        if (! $user instanceof MoonshineUser) {
            return true;
        }

        if ($user->isSuperUser()) {
            return true;
        }

        if (in_array($resourceClass, self::ADMIN_ONLY_RESOURCES, true)) {
            return false;
        }

        // Resource belum ditambahkan ke "Kelola Resource" -> default tertutup (admin-only)
        // sampai admin secara eksplisit menambahkannya dan mengatur akses per role.
        if (! in_array($resourceClass, self::manageableResourceClasses(), true)) {
            return false;
        }

        $permissions = $user->moonshineUserRole?->permissions;

        if (is_string($permissions)) {
            $permissions = json_decode($permissions, true) ?? [];
        }

        if (! is_array($permissions)) {
            $permissions = [];
        }

        // Role belum dicentang untuk resource ini (termasuk role yang belum pernah diatur sama sekali) -> tertutup
        return in_array($resourceClass, $permissions, true);
    }

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
                TrxPbiLoaderReportResource::class,
                SystemOnlineReportResource::class,
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
                TrxPbiLoaderReportFetchPage::class,
                SystemOnlineReportFetchPage::class,
                WicDbMetricReportFetchPage::class,
                WicAppMetricReportFetchPage::class,
                RolePermissionsPage::class,
            ]);

        $core->getConfig()->authorizationRules(
            static fn ($resource, $user, Ability $ability, $item): bool => self::canAccessResource(get_class($resource))
        );
    }
}
