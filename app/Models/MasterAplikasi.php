<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MasterAplikasi extends Model
{
    use SoftDeletes;

    protected $table = 'master_aplikasi';

    protected $fillable = ['nama', 'keterangan'];

    public function setNamaAttribute(string $value): void
    {
        $this->attributes['nama'] = strtoupper(trim($value));
    }
}
