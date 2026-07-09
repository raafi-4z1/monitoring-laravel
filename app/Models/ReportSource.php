<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportSource extends Model
{
    protected $table = 'report_sources';

    protected $fillable = [
        'service_name',
        'app_id',
        'data_source',
        'data_source_name',
        'service_integrator',
        'host_ip',
        'kode_prefix',
    ];
}
