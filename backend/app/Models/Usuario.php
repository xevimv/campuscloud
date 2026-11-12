<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Usuario extends Authenticatable
{
    use HasApiTokens, HasUuids, Notifiable;

    protected $table = 'usuarios';

    protected $fillable = [
        'nombre',
        'email',
        'password',
        'rol',
        'distribuidor_id',
        'activo',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'activo'   => 'boolean',
        'password' => 'hashed',
    ];

    // ── Relaciones ───────────────────────────────────────────────

    public function distribuidor(): BelongsTo
    {
        return $this->belongsTo(Distribuidor::class);
    }

    public function vendedor(): HasOne
    {
        return $this->hasOne(Vendedor::class);
    }

    // ── Helpers de rol ───────────────────────────────────────────

    public function esNomaddiAdmin(): bool
    {
        return $this->rol === 'NOMADDI_ADMIN';
    }

    public function esDistAdmin(): bool
    {
        return $this->rol === 'DIST_ADMIN';
    }

    public function esVendedor(): bool
    {
        return $this->rol === 'VENDEDOR';
    }

    /**
     * Verifica que el usuario pertenece a un distribuidor específico.
     * Usado en políticas de autorización.
     */
    public function perteneceADistribuidor(string $distribuidorId): bool
    {
        return $this->distribuidor_id === $distribuidorId;
    }

    /**
     * Devuelve las habilidades (abilities) del token según el rol.
     * Sanctum usa estas abilities para autorizar rutas específicas.
     */
    public function getAbilities(): array
    {
        return match ($this->rol) {
            'NOMADDI_ADMIN' => ['*'],
            'DIST_ADMIN'    => [
                'distribuidores:read',
                'vendedores:manage',
                'matriculas:read',
                'reportes:read',
                'estudiantes:read',
            ],
            'VENDEDOR'      => [
                'matriculas:read-own',
                'reportes:read-own',
            ],
            default         => [],
        };
    }
}
