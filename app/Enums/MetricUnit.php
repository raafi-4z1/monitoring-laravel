<?php

declare(strict_types=1);

namespace App\Enums;

enum MetricUnit: string
{
    case PERCENT      = '%';
    case GIGABYTE     = 'GB';
    case MEGABYTE     = 'MB';
    case KILOBYTE     = 'KB';
    case MBPS         = 'MB/s';
    case KBPS         = 'Kbps';
    case MILLISECOND  = 'ms';
    case SECOND       = 's';
    case NONE         = '-';

    public function label(): string
    {
        return match($this) {
            self::PERCENT     => '% — Persen',
            self::GIGABYTE    => 'GB — Gigabyte',
            self::MEGABYTE    => 'MB — Megabyte',
            self::KILOBYTE    => 'KB — Kilobyte',
            self::MBPS        => 'MB/s — Megabyte per detik',
            self::KBPS        => 'Kbps — Kilobit per detik',
            self::MILLISECOND => 'ms — Milidetik',
            self::SECOND      => 's — Detik',
            self::NONE        => '— Tanpa satuan',
        };
    }

    /** Untuk Select::make()->options() */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn($c) => [$c->value => $c->label()])
            ->toArray();
    }

    /** Untuk Rule::in() di validasi */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
