<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vendedor extends Model
{
    use HasUuids;

    protected $fillable = [
        'distribuidor_id',
        'usuario_id',
        'nombre',
        'email',
        'codigo_referido',
        'comision_tipo',
        'comision_valor',
        'activo',
    ];

    protected $casts = [
        'comision_valor' => 'decimal:2',
        'activo'         => 'boolean',
    ];

    public function distribuidor(): BelongsTo
    {
        return $this->belongsTo(Distribuidor::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }

    public function matriculas(): HasMany
    {
        return $this->hasMany(Matricula::class);
    }

    /**
     * Calcula la comisión de este vendedor para un monto dado.
     */
    public function calcularComision(float $montoPagado): float
    {
        return match ($this->comision_tipo) {
            'PORCENTAJE' => round($montoPagado * ($this->comision_valor / 100), 2),
            'FIJO'       => (float) $this->comision_valor,
            default      => 0.0,
        };
    }

    /**
     * Resumen de comisiones en un período.
     */
    public function resumenComisiones(string $desde, string $hasta): array
    {
        $matriculas = $this->matriculas()
            ->whereBetween('fecha_matricula', [$desde, $hasta])
            ->where('estado', '!=', 'CANCELADA')
            ->get();

        $totalMatriculas  = $matriculas->count();
        $totalComisiones  = $matriculas->sum(
            fn($m) => $this->calcularComision((float) $m->monto_pagado)
        );

        return [
            'vendedor_id'      => $this->id,
            'nombre'           => $this->nombre,
            'total_matriculas' => $totalMatriculas,
            'total_comisiones' => $totalComisiones,
            'comision_tipo'    => $this->comision_tipo,
            'comision_valor'   => $this->comision_valor,
        ];
    }
}
