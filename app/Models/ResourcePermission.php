<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResourcePermission extends Model
{
    protected $fillable = [
        'resource_class',
        'label',
    ];
}
