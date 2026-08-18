<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Daftar kota/server Space-X yang didukung. Server baru ditambahkan di sini dulu, lalu
 * dikonfigurasi di konstanta CITIES pada masing-masing service (SpacexJobExecutionReportService,
 * SpacexFileArchiveReportService, SpacexDcAppMetricReportService, SpacexDcDbMetricReportService) -
 * Model/Migration/Resource/Command tetap dibuat terpisah per server seperti biasa.
 */
enum SpacexCity: string
{
    case Ldn = 'ldn';
    case Nyc = 'nyc';

    public function label(): string
    {
        return match($this) {
            self::Ldn => 'London',
            self::Nyc => 'New York',
        };
    }
}
