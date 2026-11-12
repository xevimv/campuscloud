<?php
// PARCHE para app/Models/Curso.php
// Agregar la relación con la última auditoría

// Agregar este método al modelo Curso:

    /**
     * Última auditoría realizada a este curso.
     */
    public function ultimaAuditoria(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(\App\Models\AuditoriaCurso::class)
            ->latestOfMany('auditado_en');
    }

    /**
     * Historial de auditorías de este curso.
     */
    public function auditorias(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\AuditoriaCurso::class)
            ->orderByDesc('auditado_en');
    }

// También agregar los campos al array $fillable:
//   'auditoria_modulos_esperados',
//   'auditoria_examenes_esperados',
//   'auditoria_titulo_esperado',
