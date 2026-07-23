<?php

declare(strict_types=1);

namespace App\MoonShine\Concerns;

use App\Providers\MoonShineServiceProvider;
use App\Services\ActivityLogger;

/**
 * MoonShine tidak menjalankan authorizationRules untuk custom Page (termasuk FetchPage) yang
 * diakses lewat route `resource.page` / `method` — hanya CRUD action (index/create/update/delete)
 * yang dicek. Trait ini menutup celah tsb: guard manual berdasarkan permission resource induknya.
 */
trait GuardsFetchPageAccess
{
    protected function guardResourceAccess(): void
    {
        $resource = $this->getResource();

        // Resource semestinya selalu ter-bind dari route pada request nyata.
        // Kalau null (request tidak wajar), fail-closed demi keamanan.
        abort_unless(
            $resource !== null && MoonShineServiceProvider::canAccessResource($resource::class),
            403,
            'Anda tidak memiliki akses ke resource ini.'
        );
    }

    /**
     * Catat aktivitas fetch manual — dipanggil tiap FetchPage setelah proses fetch selesai
     * (baik sukses maupun sebagian gagal), supaya ketahuan siapa yang narik data rentang mana.
     */
    protected function logFetchManual(string $dateFrom, string $dateTo, int $success, int $failed): void
    {
        $resource = $this->getResource();
        $label    = $resource?->getTitle() ?? static::class;

        ActivityLogger::log(
            'fetch_manual',
            "Fetch manual {$label}: {$dateFrom} s/d {$dateTo} ({$success} berhasil, {$failed} gagal/kosong)",
            null,
            [
                'resource'  => $resource !== null ? $resource::class : null,
                'date_from' => $dateFrom,
                'date_to'   => $dateTo,
                'success'   => $success,
                'failed'    => $failed,
            ]
        );
    }
}
