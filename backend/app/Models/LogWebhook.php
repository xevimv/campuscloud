<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogWebhook extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'logs_webhook';

    protected $fillable = [
        'epayco_ref',
        'distribuidor_id',
        'moodle_course_id',
        'email_estudiante',
        'monto',
        'estado',
        'motivo_rechazo',
        'payload_raw',
        'created_at',
    ];

    protected $casts = [
        'monto'       => 'decimal:2',
        'payload_raw' => 'array',
        'created_at'  => 'datetime',
    ];

    public function distribuidor(): BelongsTo
    {
        return $this->belongsTo(Distribuidor::class);
    }
}
