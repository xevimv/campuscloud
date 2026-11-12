<?php
// MÉTODOS ADICIONALES para agregar a app/Services/MoodleService.php

// ──────────────────────────────────────────────────────────────────
// CURSOS — lectura desde Moodle
// ──────────────────────────────────────────────────────────────────

/**
 * Lista todos los cursos de Moodle.
 * Usa core_course_get_courses sin filtros para traer todo.
 * Incluye: id, fullname, shortname, categoryname, visible.
 */
public function listarCursos(): array
{
    $resp = $this->llamar('core_course_get_courses', [
        'options' => ['ids' => []],
    ]);

    return $resp ?? [];
}

/**
 * Obtiene los datos de un curso específico por su ID de Moodle.
 */
public function obtenerCurso(int $moodleCourseId): array
{
    $resp = $this->llamar('core_course_get_courses', [
        'options' => ['ids' => [$moodleCourseId]],
    ]);

    if (empty($resp[0])) {
        throw new \RuntimeException("Curso {$moodleCourseId} no encontrado en Moodle.");
    }

    return $resp[0];
}

// También agregar al webservice token en Moodle:
//   - core_course_get_courses
