<?php

namespace App\Providers;

use App\Models\AppMetric;
use App\Models\MasterAplikasi;
use App\Models\MasterMetrik;
use App\Models\ReportSource;
use App\MoonShine\Http\Requests\StrongPasswordProfileFormRequest;
use App\Observers\ActivityLogObserver;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use MoonShine\Laravel\Http\Requests\ProfileFormRequest;
use MoonShine\Laravel\Models\MoonshineUser;
use MoonShine\Laravel\Models\MoonshineUserRole;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // MoonShine\Laravel\Http\Requests\ProfileFormRequest (ganti password lewat halaman
        // profil) memvalidasi dengan aturan hardcode 'min:6', tidak memakai Password::defaults().
        // Tanpa binding ini, kebijakan password kuat di bawah hanya berlaku saat admin
        // membuat/mengubah user lewat resource Users — user tetap bisa ganti passwordnya
        // sendiri ke "123456" lewat profil. Lihat StrongPasswordProfileFormRequest.
        $this->app->bind(ProfileFormRequest::class, StrongPasswordProfileFormRequest::class);
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

        $this->configurePasswordPolicy();
    }

    /**
     * Syarat password saat akun dibuat/diubah dari panel.
     *
     * Bawaan Laravel cuma minimal 8 karakter tanpa syarat kompleksitas apa pun, sehingga
     * password seperti "12345678" lolos. Dinaikkan di sini supaya berlaku di form user
     * MoonShine; halaman profil butuh binding terpisah di register() karena request class-nya
     * tidak memakai Password::defaults() sama sekali.
     *
     * Kalau dirasa terlalu ketat, cukup ubah di method ini saja.
     *
     * ->symbols() sengaja dikomentari — aplikasi ini aksesnya terbatas (bukan publik), jadi
     * syarat simbol dianggap kurang perlu dan cuma bikin ribet mengetik password.
     *
     * Catatan: uncompromised() sengaja TIDAK dipakai — pengecekannya menembak API
     * haveibeenpwned lewat internet, sedangkan aplikasi ini jalan di LAN internal, jadi
     * hanya akan bikin proses simpan menggantung lalu gagal.
     */
    private function configurePasswordPolicy(): void
    {
        Password::defaults(
            static fn (): Password => Password::min(12)
                ->mixedCase()
                ->numbers()
                // ->symbols()
        );
    }
}
