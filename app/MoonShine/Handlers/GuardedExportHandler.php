<?php

declare(strict_types=1);

namespace App\MoonShine\Handlers;

use App\Providers\MoonShineServiceProvider;
use MoonShine\ImportExport\ExportHandler;
use Symfony\Component\HttpFoundation\Response;

/**
 * HandlerController (route admin/resource/{resourceUri}/handler/{handlerUri}) tidak menjalankan
 * authorizationRules sama sekali. Subclass ini menutup celah tsb: guard manual sebelum export
 * benar-benar dijalankan, berdasarkan permission resource induknya.
 *
 * Disk export juga dipaksa ke 'local' (privat, storage/app/private) — BUKAN disk 'public' default
 * MoonShine. Disk 'public' bisa diakses langsung lewat symlink storage:link tanpa login sama
 * sekali, terlepas dari otorisasi di atas.
 */
class GuardedExportHandler extends ExportHandler
{
    public function handle(): Response
    {
        $resource = $this->getResource();

        abort_unless(
            $resource !== null && MoonShineServiceProvider::canAccessResource($resource::class),
            403,
            'Anda tidak memiliki akses ke resource ini.'
        );

        return parent::handle();
    }

    public function getDisk(): string
    {
        return 'local';
    }
}
