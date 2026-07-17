<?php

declare(strict_types=1);

namespace App\MoonShine\Middleware;

use App\Providers\MoonShineServiceProvider;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Beberapa controller internal MoonShine (ComponentController, ReactiveController, dan lain-lain
 * yang menerima {resourceUri} lewat route) tidak menjalankan authorizationRules sama sekali —
 * hanya CRUD action (index/create/update/delete) resource yang dicek MoonShine secara native.
 *
 * Middleware ini jadi jaring pengaman terakhir: berlaku untuk SEMUA route panel (didaftarkan di
 * config/moonshine.php), memeriksa permission resource induk setiap kali route punya parameter
 * {resourceUri} yang valid — apa pun controller/endpoint yang dituju.
 */
class GuardResourcePermission
{
    public function handle(Request $request, Closure $next): Response
    {
        $resourceUri = $request->route('resourceUri');

        if (is_string($resourceUri)) {
            $resource = moonshine()->getResources()->findByUri($resourceUri);

            if ($resource !== null) {
                abort_unless(
                    MoonShineServiceProvider::canAccessResource($resource::class),
                    403,
                    'Anda tidak memiliki akses ke resource ini.'
                );
            }
        }

        return $next($request);
    }
}
