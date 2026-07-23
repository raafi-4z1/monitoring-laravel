<?php

namespace App\Providers;

use App\Models\AppMetric;
use App\Models\MasterAplikasi;
use App\Models\MasterMetrik;
use App\Models\ReportSource;
use App\Observers\ActivityLogObserver;
use Illuminate\Support\ServiceProvider;
use MoonShine\Laravel\Models\MoonshineUser;
use MoonShine\Laravel\Models\MoonshineUserRole;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Activity log — CRUD standar (create/update/delete lewat $model->save()/->delete() asli).
        // Mutasi lewat query builder mentah (RolePermissionsPage, dsb) dicatat manual di tempatnya.
        foreach ([
            MoonshineUser::class,
            MoonshineUserRole::class,
            MasterAplikasi::class,
            MasterMetrik::class,
            ReportSource::class,
            AppMetric::class,
        ] as $model) {
            $model::observe(ActivityLogObserver::class);
        }

        // App\Listeners\LogAuthActivity ter-daftar otomatis lewat Laravel event auto-discovery
        // (method dengan parameter bertipe event di app/Listeners) — tidak perlu Event::subscribe()
        // manual di sini, itu malah bikin listener-nya kepanggil dua kali.
    }
}
