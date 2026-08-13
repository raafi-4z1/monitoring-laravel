<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpacexLdnFileArchiveReport extends Model
{
    protected $table = 'spacex_ldn_file_archive_reports';

    protected $fillable = [
        'report_source_id',
        'trx_date',
        'filename',
        'pathfile',
        'row_count',
        'status',
        'timestamp',
    ];

    protected $casts = [
        'trx_date'         => 'date',
        'row_count'        => 'integer',
        'report_source_id' => 'integer',
    ];

    protected $with = ['reportSource'];

    public function reportSource(): BelongsTo
    {
        return $this->belongsTo(ReportSource::class);
    }

    /**
     * Nama file berubah-ubah tanggalnya tiap hari (mis. "gend0872.20260811.1807.prtaa" vs
     * "gend0872.20260810.1809.prtaa") - bagian sebelum titik pertama-lah yang stabil dan
     * dipakai sebagai identitas "grup file" untuk pengelompokan chart (empat grup yang
     * teramati: gend0872, gend0870, F1GLTU1af, bkupdayaftdp).
     */
    public function fileGroup(): string
    {
        return strstr($this->filename, '.', true) ?: $this->filename;
    }
}
