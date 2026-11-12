<?php
// app/Models/Liquidacion.php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Liquidacion extends Model
{
    use HasUuids;

    protected $fillable = [
        'ietdh_id', 'periodo_anio', 'periodo_mes',
        'total_tecnicos', 'total_bachillerato',
        'fee_tecnicos', 'fee_bachillerato', 'valor_total',
        'estado', 'fecha_generacion', 'fecha_pago',
    ];

    protected $casts = [
        'periodo_anio'      => 'integer',
        'periodo_mes'       => 'integer',
        'total_tecnicos'    => 'integer',
        'total_bachillerato'=> 'integer',
        'fee_tecnicos'      => 'decimal:2',
        'fee_bachillerato'  => 'decimal:2',
        'valor_total'       => 'decimal:2',
        'fecha_generacion'  => 'datetime',
        'fecha_pago'        => 'datetime',
    ];

    public function ietdh(): BelongsTo
    {
        return $this->belongsTo(Ietdh::class);
    }

    public function certificados(): HasMany
    {
        return $this->hasMany(Certificado::class);
    }

    public function getPeriodoAttribute(): string
    {
        return str_pad($this->periodo_mes, 2, '0', STR_PAD_LEFT)
             . '/' . $this->periodo_anio;
    }
}
