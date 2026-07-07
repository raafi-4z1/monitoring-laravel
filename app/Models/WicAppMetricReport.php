<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WicAppMetricReport extends Model
{
    protected $table = 'wic_app_metric_reports';

    protected $fillable = [
        'report_source_id',
        'trx_date',
        'trx_hour',
        'metric_type',
        'disk_path',
        'max_pct',
        'min_pct',
        'avg_pct',
        'last_pct',
        'last_used_bytes',
        'last_total_bytes',
    ];

    protected $casts = [
        'trx_date'         => 'date',
        'trx_hour'         => 'integer',
        'max_pct'          => 'float',
        'min_pct'          => 'float',
        'avg_pct'          => 'float',
        'last_pct'         => 'float',
        'last_used_bytes'  => 'integer',
        'last_total_bytes' => 'integer',
        'report_source_id' => 'integer',
    ];

    protected $with = ['reportSource'];

    public function reportSource(): BelongsTo
    {
        return $this->belongsTo(ReportSource::class);
    }
}
