<?php
// MÉTODOS ADICIONALES para agregar a app/Services/MoodleService.php

// ──────────────────────────────────────────────────────────────────
// CAMPOS DE PERFIL PERSONALIZADOS
// ──────────────────────────────────────────────────────────────────

/**
 * Activa el campo de perfil "siet_completado" para un usuario.
 * Este campo es la condición de disponibilidad del curso 2 en Moodle.
 *
 * Prerequisito en Moodle:
 *   Administración → Usuarios → Campos de perfil de usuario
 *   → Crear campo tipo "Menú de opciones" o "Casilla de verificación"
 *   → shortname: "siet_completado"
 *   → La restricción del curso 2 apunta a este campo = "1"
 */
public function activarCampoSiet(int $moodleUserId): void
{
    $this->llamar('core_user_update_users', [
        'users' => [[
            'id'           => $moodleUserId,
            'customfields' => [
                ['type' => 'siet_completado', 'value' => '1'],
            ],
        ]],
    ]);

    Log::info('Moodle: campo siet_completado activado', [
        'moodle_user_id' => $moodleUserId,
    ]);
}

/**
 * Marca una matrícula como completada en nuestra BD
 * cuando Moodle notifica que el estudiante terminó el curso.
 *
 * Nota: Moodle no tiene un webhook nativo para esto.
 * Se implementa de dos formas:
 *
 *  Opción A (recomendada): Webhook local de Moodle con el plugin
 *  "Moodle Webhooks" que dispara un POST a nuestro panel cuando
 *  un usuario completa un curso.
 *
 *  Opción B: Job programado cada hora que consulta
 *  core_completion_get_activities_completion_status para todos
 *  los estudiantes activos y actualiza los estados.
 */
public function consultarCompletitud(int $moodleUserId, int $moodleCourseId): bool
{
    $resp = $this->llamar('core_completion_get_activities_completion_status', [
        'courseid' => $moodleCourseId,
        'userid'   => $moodleUserId,
    ]);

    // El curso está completo si todos sus ítems de completitud están OK
    $statuses = $resp['statuses'] ?? [];

    if (empty($statuses)) {
        return false;
    }

    return collect($statuses)->every(
        fn($item) => in_array($item['state'] ?? 0, [1, 2])
    );
}

// También agregar al webservice token en Moodle:
//   - core_user_update_users
