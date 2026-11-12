<?php
// app/Models/Ietdh.php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ietdh extends Model
{
    use HasUuids;

    protected $table = 'ietdhs';

    protected $fillable = [
        'nombre',
        'nit',
        'departamento',
        'municipio',
        'email_contacto',
        'telefono',
        'activa',
    ];

    protected $casts = [
        'activa' => 'boolean',
    ];

    public function fees(): HasMany
    {
        return $this->hasMany(IetdhFee::class);
    }

    public function distribuidores(): HasMany
    {
        return $this->hasMany(DistribuidorIetdh::class);
    }

    /**
     * Fee vigente hoy para un tipo de programa específico.
     */
    public function feeVigente(string $tipoPrograma): ?IetdhFee
    {
        return $this->fees()
            ->where('tipo_programa', $tipoPrograma)
            ->where('vigente_desde', '<=', now()->toDateString())
            ->orderByDesc('vigente_desde')
            ->first();
    }

    public function getFeeAttribute(): ?float
    {
        return $this->feeVigente('TECNICOS')?->fee_por_certificado;
    }
}
