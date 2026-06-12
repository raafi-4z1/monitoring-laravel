<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MasterMetrik extends Model
{
    use SoftDeletes;

    protected $table = 'master_metrik';

    protected $fillable = ['nama', 'satuan_default', 'keterangan'];

    public function setNamaAttribute(string $value): void
    {
        $this->attributes['nama'] = strtoupper(trim($value));
    }
}
