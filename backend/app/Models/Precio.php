<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Precio extends Model
{
    use HasUuids;

    protected $fillable = [
        'curso_id',
        'fee_nomaddi',
        'precio_minimo_publico',
        'vigente_desde',
    ];

    protected $casts = [
        'fee_nomaddi'           => 'decimal:2',
        'precio_minimo_publico' => 'decimal:2',
        'vigente_desde'         => 'date',
    ];

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }
}
