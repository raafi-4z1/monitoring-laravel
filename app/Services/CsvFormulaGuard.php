<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Collection;

/**
 * Penetral CSV formula injection.
 *
 * Excel/LibreOffice memperlakukan isi sel yang diawali '=', '+', '-', atau '@' sebagai RUMUS,
 * bukan teks. Kalau nilai tersebut berasal dari input yang bisa dikendalikan orang lain, membuka
 * file CSV-nya saja sudah cukup untuk menjalankan rumus itu di komputer yang membukanya —
 * termasuk memanggil program lain atau mengirim isi sel ke URL eksternal.
 *
 * Dipakai di DUA jalur export yang berbeda dan harus tetap konsisten:
 *  - App\MoonShine\Handlers\GuardedExportHandler — export manual dari panel admin.
 *  - App\Console\Commands\Export*Csv — export terjadwal yang menulis CSV harian ke folder
 *    bersama. Jalur ini justru lebih rawan karena filenya dibuka orang lain, bukan yang
 *    menekan tombol export.
 */
class CsvFormulaGuard
{
    /**
     * Tab & carriage return ikut dihitung karena spreadsheet memangkasnya lebih dulu,
     * sehingga "\t=cmd|..." tetap berakhir sebagai rumus.
     */
    private const FORMULA_TRIGGERS = ['=', '+', '-', '@', "\t", "\r"];

    /**
     * Nilai berawalan '-' yang isinya cuma angka/pemisah desimal, atau tanda hubung tunggal
     * sebagai penanda "kosong". Ini BUKAN rumus, jadi sengaja dibiarkan apa adanya supaya
     * isi laporan tidak berubah — mis. kolom called_app yang memang diisi "-", atau nilai
     * metrik yang negatif.
     */
    private const NOT_A_FORMULA = '/^-[\d.,]*$/';

    /**
     * Diawali kutip satu supaya spreadsheet membacanya sebagai teks biasa. Nilai aslinya
     * tidak diubah, jadi isi laporan tetap utuh dan tetap terbaca manusia.
     */
    public static function value(mixed $value): mixed
    {
        if (! \is_string($value) || $value === '') {
            return $value;
        }

        if (! \in_array($value[0], self::FORMULA_TRIGGERS, true)) {
            return $value;
        }

        if (preg_match(self::NOT_A_FORMULA, $value) === 1) {
            return $value;
        }

        return "'" . $value;
    }

    /**
     * Terapkan ke setiap sel pada koleksi baris siap-export (array asosiatif kolom => nilai).
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    public static function rows(Collection $rows): Collection
    {
        return $rows->map(
            static fn (array $row): array => array_map(
                static fn (mixed $cell): mixed => self::value($cell),
                $row
            )
        );
    }
}
