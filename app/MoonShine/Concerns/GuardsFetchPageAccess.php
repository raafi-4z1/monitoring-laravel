<?php

declare(strict_types=1);

namespace App\MoonShine\Concerns;

use App\Providers\MoonShineServiceProvider;

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
}
