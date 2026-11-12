<?php
// app/Models/DistribuidorIetdh.php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DistribuidorIetdh extends Model
{
    use HasUuids;

    protected $table = 'distribuidor_ietdh';

    protected $fillable = [
        'distribuidor_id',
        'ietdh_id',
        'tipo_programa',
    ];

    public function distribuidor(): BelongsTo
    {
        return $this->belongsTo(Distribuidor::class);
    }

    public function ietdh(): BelongsTo
    {
        return $this->belongsTo(Ietdh::class);
    }
}
