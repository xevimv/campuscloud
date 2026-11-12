<?php
// app/Models/LogValidacionCertificado.php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class LogValidacionCertificado extends Model
{
    use HasUuids;

    public $timestamps   = false;
    protected $table     = 'logs_validacion_certificado';

    protected $fillable  = [
        'uuid_verificacion',
        'ip_consultante',
        'dominio_consultante',
        'resultado',
        'created_at',
    ];

    protected $casts = ['created_at' => 'datetime'];
}
