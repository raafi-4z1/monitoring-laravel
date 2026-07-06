<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrxPbiSettlementReport extends Model
{
    protected $table = 'trx_pbi_settlement_reports';

    protected $fillable = [
        'report_source_id',
        'trx_date',
        'trx_hour',
        'trx_currency',
        'trx_count',
        'success_count',
        'trx_amount',
    ];

    protected $casts = [
        'trx_date'         => 'date',
        'trx_hour'         => 'integer',
        'trx_count'        => 'integer',
        'success_count'    => 'integer',
        'trx_amount'       => 'float',
        'report_source_id' => 'integer',
    ];

    protected $with = ['reportSource'];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model): void {
            if (is_null($model->report_source_id)) {
                $model->report_source_id = ReportSource::where('service_name', 'trx_pbi_settlement')->value('id');
            }
        });
    }

    public function reportSource(): BelongsTo
    {
        return $this->belongsTo(ReportSource::class);
    }
}
