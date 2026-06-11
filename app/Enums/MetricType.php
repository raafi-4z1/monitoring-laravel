<?php

declare(strict_types=1);

namespace App\Enums;

enum MetricType: string
{
    case CPU           = 'CPU';
    case MEMORY        = 'MEMORY';
    case DISK          = 'DISK';
    case NETWORK_IN    = 'NETWORK_IN';
    case NETWORK_OUT   = 'NETWORK_OUT';
    case LOAD_1M       = 'LOAD_1M';
    case LOAD_5M       = 'LOAD_5M';
    case LOAD_15M      = 'LOAD_15M';
    case RESPONSE_TIME = 'RESPONSE_TIME';

    public function label(): string
    {
        return match($this) {
            self::CPU           => 'CPU',
            self::MEMORY        => 'Memory',
            self::DISK          => 'Disk',
            self::NETWORK_IN    => 'Network In',
            self::NETWORK_OUT   => 'Network Out',
            self::LOAD_1M       => 'Load Avg (1m)',
            self::LOAD_5M       => 'Load Avg (5m)',
            self::LOAD_15M      => 'Load Avg (15m)',
            self::RESPONSE_TIME => 'Response Time',
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
