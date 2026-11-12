<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Matricula extends Model
{
    use HasUuids;

    protected $fillable = [
        'estudiante_id',
        'curso_id',
        'distribuidor_id',
        'epayco_ref',
        'monto_pagado',
        'fee_nomaddi_cobrado',
        'moodle_enrol_id',
        'moodle_group_id',
        'estado',
        'fecha_matricula',
        'fecha_completado',
    ];

    protected $casts = [
        'monto_pagado'       => 'decimal:2',
        'fee_nomaddi_cobrado'=> 'decimal:2',
        'moodle_enrol_id'    => 'integer',
        'moodle_group_id'    => 'integer',
        'fecha_matricula'    => 'datetime',
        'fecha_completado'   => 'datetime',
    ];

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class);
    }

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    public function distribuidor(): BelongsTo
    {
        return $this->belongsTo(Distribuidor::class);
    }
}
