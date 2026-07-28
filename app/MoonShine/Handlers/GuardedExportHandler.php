<?php

declare(strict_types=1);

namespace App\MoonShine\Handlers;

use App\Providers\MoonShineServiceProvider;
use App\Services\ActivityLogger;
use App\Services\CsvFormulaGuard;
use Generator;
use Illuminate\Support\Facades\Storage;
use MoonShine\Contracts\Core\ResourceContract;
use MoonShine\Core\Exceptions\ResourceException;
use MoonShine\Crud\Notifications\NotificationButton;
use MoonShine\ImportExport\Contracts\HasImportExportContract;
use MoonShine\ImportExport\ExportHandler;
use MoonShine\Laravel\Notifications\MoonShineNotification;
use Rap2hpoutre\FastExcel\FastExcel;
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

    /**
     * Menyalin MoonShine\ImportExport\ExportHandler::process() apa adanya, hanya menyisipkan
     * CsvFormulaGuard saat mengisi sel. Di-override di sini (bukan di tiap Resource) supaya
     * seluruh export panel ikut terlindungi, termasuk resource yang ditambahkan nanti.
     *
     * Nilai yang diekspor bisa berasal dari input user — mis. kolom user di Activity Log,
     * yang namanya bisa diubah sendiri lewat halaman profil, dan username percobaan login
     * gagal yang bahkan masuk tanpa perlu autentikasi.
     *
     * Dipanggil lewat late static binding dari parent::handle() (`static::process(...)`).
     *
     * PERHATIAN kalau nanti export dijalankan lewat antrian: MoonShine\ImportExport\Jobs\
     * ExportHandlerJob memanggil `ExportHandler::process()` dengan nama kelas induk secara
     * literal, sehingga override ini TIDAK ikut terpakai dan penetralan rumus hilang. Saat ini
     * tidak ada handler yang memakai ->queue(), jadi jalur itu tidak aktif. Kalau suatu saat
     * diaktifkan, penetralannya harus dipasang ulang di jalur job tersebut.
     */
    public static function process(
        string $path,
        ResourceContract $resource,
        array $query,
        string $disk = 'public',
        string $dir = '/',
        string $delimiter = ',',
        array $notifyUsers = [],
    ): string {
        if (! $resource instanceof HasImportExportContract) {
            throw new ResourceException('The resource must implement the HasImportExportContract interface.');
        }

        $resource->setQueryParams($query);

        $items = static function (ResourceContract $resource): Generator {
            foreach ($resource->getQuery()->cursor() as $index => $item) {
                $row = [];

                $fields = $resource->getExportFields();

                $fields->fill(
                    $item->toArray(),
                    $resource->getCaster()->cast($item),
                    $index
                );

                foreach ($fields as $field) {
                    $row[$field->getLabel()] = CsvFormulaGuard::value(
                        $field->rawMode()->preview()
                    );
                }

                yield $row;
            }
        };

        $fastExcel = new FastExcel($items($resource));

        if (str($path)->contains('.csv')) {
            $fastExcel->configureCsv($delimiter);
        }

        $result = $fastExcel->export($path);

        $url = str($path)
            ->remove(Storage::disk($disk)->path($dir))
            ->value();

        MoonShineNotification::send(
            __('moonshine::ui.resource.export.exported'),
            new NotificationButton(
                label: __('moonshine::ui.download'),
                link: Storage::disk($disk)->url(trim($dir, '/') . $url)
            ),
            ids: $notifyUsers,
        );

        return $result;
    }
}
