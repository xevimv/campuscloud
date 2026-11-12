<?php
// app/Models/Certificado.php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Certificado extends Model
{
    use HasUuids;

    protected $fillable = [
        'matricula_id', 'acta_id', 'ietdh_id', 'liquidacion_id',
        'folio', 'uuid_verificacion', 'tipo_programa',
        'fecha_emision', 'anulado', 'motivo_anulacion', 'fecha_anulacion',
    ];

    protected $casts = [
        'fecha_emision'   => 'datetime',
        'fecha_anulacion' => 'datetime',
        'anulado'         => 'boolean',
        'folio'           => 'integer',
    ];

    public function matricula(): BelongsTo
    {
        return $this->belongsTo(Matricula::class);
    }

    public function acta(): BelongsTo
    {
        return $this->belongsTo(Acta::class);
    }

    public function ietdh(): BelongsTo
    {
        return $this->belongsTo(Ietdh::class);
    }

    public function liquidacion(): BelongsTo
    {
        return $this->belongsTo(Liquidacion::class);
    }

    /**
     * Referencia completa legible del certificado.
     * Ejemplo: T2604-15  (acta T2604, folio 15)
     */
    public function getReferenciasAttribute(): string
    {
        return "{$this->acta->codigo_acta}-{$this->folio}";
    }

    /**
     * URL de validación pública.
     */
    public function getUrlValidacionAttribute(): string
    {
        return config('app.url') . "/verificar/{$this->uuid_verificacion}";
    }
}
