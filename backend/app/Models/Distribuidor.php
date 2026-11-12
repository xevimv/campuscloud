<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Distribuidor extends Model
{
    use HasUuids;

    protected $fillable = [
        'nombre',
        'dominio',
        'email_contacto',
        'epayco_receiver_id',
        'moodle_cohort_id',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'moodle_cohort_id' => 'integer',
    ];

    public function matriculas(): HasMany
    {
        return $this->hasMany(Matricula::class);
    }

    public function estudiantes(): HasMany
    {
        return $this->hasMany(Estudiante::class);
    }

    public function logsWebhook(): HasMany
    {
        return $this->hasMany(LogWebhook::class);
    }
}
