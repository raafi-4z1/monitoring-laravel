<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobExecutionReport extends Model
{
    protected $table = 'job_execution_reports';

    protected $fillable = [
        'report_source_id',
        'trx_date',
        'date_parameter',
        'job_name',
        'start_time',
        'end_time',
        'duration',
        'filename',
        'log_type',
        'status',
    ];

    protected $casts = [
        'trx_date'         => 'date',
        'start_time'       => 'datetime',
        'end_time'         => 'datetime',
        'report_source_id' => 'integer',
    ];

    protected $with = ['reportSource'];

    public function reportSource(): BelongsTo
    {
        return $this->belongsTo(ReportSource::class);
    }
}
