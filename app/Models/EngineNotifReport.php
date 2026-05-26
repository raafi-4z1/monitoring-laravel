<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class EngineNotifReport extends Model
{
    protected $table = 'engine_notif_reports';

    protected $fillable = [
        'report_date',
        'mvrk_success',
        'mvrk_fail',
        'sms_success',
        'sms_fail',
        'email_success',
        'email_fail',
        'avg_response_time',
    ];

    protected $casts = [
        'report_date' => 'date',
    ];

    // ✅ Accessor - dihitung otomatis saat akses $model->mvrk_total
    protected function mvrkTotal(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->mvrk_success + $this->mvrk_fail,
        );
    }

    protected function smsTotal(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->sms_success + $this->sms_fail,
        );
    }

    protected function emailTotal(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->email_success + $this->email_fail,
        );
    }

    protected function totalSuccess(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->mvrk_success + $this->sms_success + $this->email_success,
        );
    }

    protected function totalFail(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->mvrk_fail + $this->sms_fail + $this->email_fail,
        );
    }
}