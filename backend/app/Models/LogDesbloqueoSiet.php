<?php
// app/Models/LogDesbloqueoSiet.php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogDesbloqueoSiet extends Model
{
    use HasUuids;

    public $timestamps  = false;
    protected $table    = 'logs_desbloqueo_siet';

    protected $fillable = [
        'estudiante_id',
        'moodle_user_id',
        'moodle_curso_desbloqueado_id',
        'estado',
        'error_mensaje',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class);
    }
}
