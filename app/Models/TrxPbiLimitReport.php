<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrxPbiLimitReport extends Model
{
    protected $table = 'trx_pbi_limit_reports';

    protected $fillable = [
        'report_hour',
        'ccy2',
        'total_trx',
        'total_nominal',
        'total_nominal_eq_usd',
    ];

    protected $casts = [
        'report_hour'          => 'datetime',
        'total_trx'            => 'integer',
        'total_nominal'        => 'float',
        'total_nominal_eq_usd' => 'float',
    ];
}
