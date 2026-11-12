<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Curso extends Model
{
    use HasUuids;

    protected $fillable = [
        'moodle_course_id',
        'nombre',
        'tipo_programa',
        'orden_en_carrera',
        'es_ultimo',
        'activo',
    ];

    protected $casts = [
        'moodle_course_id' => 'integer',
        'orden_en_carrera' => 'integer',
        'es_ultimo'        => 'boolean',
        'activo'           => 'boolean',
    ];

    public function precios(): HasMany
    {
        return $this->hasMany(Precio::class);
    }

    /**
     * El precio vigente hoy: el registro con vigente_desde más reciente
     * que no supere la fecha actual.
     */
    public function precioVigente(): HasOne
    {
        return $this->hasOne(Precio::class)
            ->where('vigente_desde', '<=', now()->toDateString())
            ->orderByDesc('vigente_desde')
            ->limit(1);
    }

    public function matriculas(): HasMany
    {
        return $this->hasMany(Matricula::class);
    }

    /**
     * Devuelve el precio mínimo público vigente o null si no hay precio.
     */
    public function getPrecioMinimoAttribute(): ?float
    {
        return $this->precioVigente?->precio_minimo_publico;
    }

    /**
     * Devuelve el fee de Nomaddi vigente o null si no hay precio.
     */
    public function getFeeNomaddiAttribute(): ?float
    {
        return $this->precioVigente?->fee_nomaddi;
    }
}
