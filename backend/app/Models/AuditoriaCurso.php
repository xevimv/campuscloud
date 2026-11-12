<?php
// app/Models/AuditoriaCurso.php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditoriaCurso extends Model
{
    use HasUuids;

    public $timestamps  = false;
    protected $table    = 'auditorias_cursos';

    protected $fillable = [
        'curso_id', 'resultado_global',
        'check_titulo', 'check_bloque_progreso', 'check_modulos',
        'check_examenes', 'check_certificado', 'check_enlaces', 'check_calificacion',
        'detalle', 'auditado_por', 'auditado_en',
    ];

    protected $casts = [
        'check_titulo'          => 'boolean',
        'check_bloque_progreso' => 'boolean',
        'check_modulos'         => 'boolean',
        'check_examenes'        => 'boolean',
        'check_certificado'     => 'boolean',
        'check_enlaces'         => 'boolean',
        'check_calificacion'    => 'boolean',
        'detalle'               => 'array',
        'auditado_en'           => 'datetime',
    ];

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    public function auditadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'auditado_por');
    }

    /**
     * Resumen de checks como array de semáforos.
     * Útil para el frontend.
     */
    public function getSemaforosAttribute(): array
    {
        return [
            ['check' => 'Título correcto',       'ok' => $this->check_titulo,          'critico' => true],
            ['check' => 'Bloque de progreso',    'ok' => $this->check_bloque_progreso, 'critico' => false],
            ['check' => 'Módulos completos',     'ok' => $this->check_modulos,         'critico' => true],
            ['check' => 'Exámenes completos',    'ok' => $this->check_examenes,        'critico' => true],
            ['check' => 'Certificado activo',    'ok' => $this->check_certificado,     'critico' => false],
            ['check' => 'Sin enlaces rotos',     'ok' => $this->check_enlaces,         'critico' => false],
            ['check' => 'Calificación config.',  'ok' => $this->check_calificacion,    'critico' => false],
        ];
    }
}
