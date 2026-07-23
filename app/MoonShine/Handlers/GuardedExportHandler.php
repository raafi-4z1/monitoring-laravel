<?php

declare(strict_types=1);

namespace App\MoonShine\Handlers;

use App\Providers\MoonShineServiceProvider;
use App\Services\ActivityLogger;
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
    protected ?string $forcedSort = null;

    /**
     * Export selalu terurut sesuai ini, terlepas dari sort yang sedang aktif di tabel
     * (mis. tabel ditampilkan terbaru dulu, tapi export tetap kronologis 00-23).
     */
    public function forceSort(string $column, string $direction = 'asc'): static
    {
        $this->forcedSort = ($direction === 'desc' ? '-' : '') . $column;

        return $this;
    }

    public function handle(): Response
    {
        $resource = $this->getResource();

        abort_unless(
            $resource !== null && MoonShineServiceProvider::canAccessResource($resource::class),
            403,
            'Anda tidak memiliki akses ke resource ini.'
        );

        if ($this->forcedSort !== null) {
            request()->query->set('sort', $this->forcedSort);
        }

        ActivityLogger::log(
            'export',
            "Export {$this->getLabel()}: {$resource->getTitle()}",
            null,
            [
                'resource' => $resource::class,
                'format'   => $this->isCsv() ? 'csv' : 'xlsx',
                'filter'   => request()->input('filter', []),
            ]
        );

        return parent::handle();
    }

    public function getDisk(): string
    {
        return 'local';
    }
}
