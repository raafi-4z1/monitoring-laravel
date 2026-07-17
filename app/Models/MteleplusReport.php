<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MteleplusReport extends Model
{
    protected $fillable = [
        'report_hour',
        'akt_success', 'akt_fail',
        'rpin_success', 'rpin_fail',
        'total_incoming', 'total_outgoing',
    ];

    protected $casts = [
        'report_hour' => 'datetime',
    ];

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
