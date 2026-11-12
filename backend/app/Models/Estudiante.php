<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Estudiante extends Model
{
    use HasUuids;

    protected $fillable = [
        'tipo_documento',
        'numero_documento',
        'nombres',
        'apellidos',
        'email',
        'moodle_user_id',
        'distribuidor_id',
    ];

    protected $casts = [
        'moodle_user_id' => 'integer',
    ];

    public function distribuidor(): BelongsTo
    {
        return $this->belongsTo(Distribuidor::class);
    }

    public function matriculas(): HasMany
    {
        return $this->hasMany(Matricula::class);
    }

    public function siet(): HasOne
    {
        return $this->hasOne(EstudianteSiet::class);
    }

    public function getNombreCompletoAttribute(): string
    {
        return "{$this->nombres} {$this->apellidos}";
    }

    public function tieneSietCompleto(): bool
    {
        return $this->siet?->completado_en !== null;
    }
}
