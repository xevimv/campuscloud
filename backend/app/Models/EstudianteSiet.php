<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EstudianteSiet extends Model
{
    use HasUuids;

    protected $table = 'estudiantes_siet';

    protected $fillable = [
        'estudiante_id',
        'fecha_nacimiento',
        'municipio_dane',
        'sexo_biologico',
        'estado_civil',
        'jornada',
        'estrato',
        'regimen_salud',
        'nivel_formacion',
        'ocupacion',
        'discapacidad',
        'indigena',
        'afrodescendiente',
        'desplazado',
        'poblacion_frontera',
        'cabeza_familia',
        'reinsertado',
        'poblacion_room',
        'completado_en',
    ];

    protected $casts = [
        'fecha_nacimiento'   => 'date',
        'completado_en'      => 'datetime',
        'discapacidad'       => 'boolean',
        'indigena'           => 'boolean',
        'afrodescendiente'   => 'boolean',
        'desplazado'         => 'boolean',
        'poblacion_frontera' => 'boolean',
        'cabeza_familia'     => 'boolean',
        'reinsertado'        => 'boolean',
        'poblacion_room'     => 'boolean',
    ];

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class);
    }

    public function estaCompleto(): bool
    {
        return $this->completado_en !== null;
    }
}
