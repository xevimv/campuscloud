<?php
// app/Models/DistribuidorGrupoCurso.php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DistribuidorGrupoCurso extends Model
{
    use HasUuids;

    protected $table = 'distribuidor_grupos_cursos';

    protected $fillable = [
        'distribuidor_id',
        'curso_id',
        'moodle_group_id',
    ];

    protected $casts = [
        'moodle_group_id' => 'integer',
    ];

    public function distribuidor(): BelongsTo
    {
        return $this->belongsTo(Distribuidor::class);
    }

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }
}
