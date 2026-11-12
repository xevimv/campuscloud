<?php
// app/Models/IetdhFee.php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IetdhFee extends Model
{
    use HasUuids;

    protected $table = 'ietdh_fees';

    protected $fillable = [
        'ietdh_id',
        'tipo_programa',
        'fee_por_certificado',
        'vigente_desde',
    ];

    protected $casts = [
        'fee_por_certificado' => 'decimal:2',
        'vigente_desde'       => 'date',
    ];

    public function ietdh(): BelongsTo
    {
        return $this->belongsTo(Ietdh::class);
    }
}
