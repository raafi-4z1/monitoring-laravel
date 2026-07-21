<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemOnlineReport extends Model
{
    protected $table = 'system_online_reports';

    protected $fillable = [
        'report_source_id',
        'trx_date',
        'trx_hour',
        'service_name',
        'response_time_avg_ms',
    ];

    protected $casts = [
        'trx_date'             => 'date',
        'trx_hour'              => 'integer',
        'response_time_avg_ms'  => 'float',
        'report_source_id'      => 'integer',
    ];

    protected $with = ['reportSource'];

    public function reportSource(): BelongsTo
    {
        return $this->belongsTo(ReportSource::class);
    }
}