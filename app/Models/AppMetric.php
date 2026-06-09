<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class AppMetric extends Model
{
    protected $fillable = [
        'recorded_at',
        'nama_aplikasi',
        'metric',
        'value',
        'satuan',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
    ];

    public function getDateFormat(): string
    {
        return 'Y-m-d H:i:s.u';
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            $now = now();
            if (empty($model->recorded_at)) {
                $model->recorded_at = $now;
            } else {
                $base = Carbon::parse($model->recorded_at);
                // Preserve user's date, hour, minute — inject current second+microsecond
                $model->recorded_at = $base->setTime(
                    $base->hour,
                    $base->minute,
                    $now->second,
                    $now->microsecond,
                );
            }
        });
    }

    public function setNamaAplikasiAttribute(string $value): void
    {
        $this->attributes['nama_aplikasi'] = strtoupper(trim($value));
    }

    public function setMetricAttribute(string $value): void
    {
        $this->attributes['metric'] = strtoupper(trim($value));
    }
}
