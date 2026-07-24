<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MteleplusReport extends Model
{
    protected $fillable = [
        'report_source_id',
        'trx_date',
        'trx_hour',
        'akt_success', 'akt_fail',
        'rpin_success', 'rpin_fail',
        'total_incoming', 'total_outgoing',
    ];

    protected $casts = [
        'trx_date'         => 'date',
        'trx_hour'         => 'integer',
        'report_source_id' => 'integer',
    ];

    protected $with = ['reportSource'];

    public function reportSource(): BelongsTo
    {
        return $this->belongsTo(ReportSource::class);
    }

    public function getAktTotalAttribute(): int
    {
        return $this->akt_success + $this->akt_fail;
    }

    public function getRpinTotalAttribute(): int
    {
        return $this->rpin_success + $this->rpin_fail;
    }

    public function getTotalSuccessAttribute(): int
    {
        return $this->akt_success + $this->rpin_success;
    }

    public function getTotalFailAttribute(): int
    {
        return $this->akt_fail + $this->rpin_fail;
    }
}
