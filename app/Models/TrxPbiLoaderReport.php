<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrxPbiLoaderReport extends Model
{
    protected $table = 'trx_pbi_loader_reports';

    protected $fillable = [
        'report_source_id',
        'job_type',
        'job_name',
        'trx_date',
        'trx_hour',
        'start_time',
        'end_time',
        'duration_sec',
        'record_processed',
        'throughput_row_per_sec',
        'status_job',
    ];

    protected $casts = [
        'trx_date'               => 'date',
        'trx_hour'               => 'integer',
        'duration_sec'           => 'integer',
        'record_processed'       => 'integer',
        'throughput_row_per_sec' => 'float',
        'report_source_id'       => 'integer',
    ];

    protected $with = ['reportSource'];

    public function reportSource(): BelongsTo
    {
        return $this->belongsTo(ReportSource::class);
    }
}
