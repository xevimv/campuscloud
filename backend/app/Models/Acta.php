<?php
// app/Models/Acta.php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Acta extends Model
{
    use HasUuids;

    protected $fillable = [
        'ietdh_id', 'tipo_programa', 'anio', 'mes', 'codigo_acta',
    ];

    protected $casts = [
        'anio' => 'integer',
        'mes'  => 'integer',
    ];

    public function ietdh(): BelongsTo
    {
        return $this->belongsTo(Ietdh::class);
    }

    public function certificados(): HasMany
    {
        return $this->hasMany(Certificado::class);
    }

    /**
     * Genera el código de acta automáticamente.
     * Formato: T/B + últimos 2 dígitos del año + mes con 2 dígitos
     * Ejemplos: T2604, B2601, T2712
     */
    public static function generarCodigo(
        string $tipoPrograma,
        int $anio,
        int $mes
    ): string {
        $prefijo    = $tipoPrograma === 'TECNICOS' ? 'T' : 'B';
        $anio2dig   = substr((string) $anio, -2);
        $mes2dig    = str_pad((string) $mes, 2, '0', STR_PAD_LEFT);
        return "{$prefijo}{$anio2dig}{$mes2dig}";
    }

    /**
     * Siguiente folio disponible dentro de esta acta.
     * El máximo folio existente + 1, o 1 si no hay certificados.
     * Se llama DENTRO de una transacción con bloqueo.
     */
    public function siguienteFolio(): int
    {
        $max = $this->certificados()->max('folio');
        return ($max ?? 0) + 1;
    }
}
