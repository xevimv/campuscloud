<?php
// MÉTODOS ADICIONALES para agregar a app/Services/MoodleService.php

// ──────────────────────────────────────────────────────────────────
// CONTENIDO DEL CURSO (secciones + módulos)
// ──────────────────────────────────────────────────────────────────

/**
 * Devuelve el contenido completo de un curso:
 * secciones, módulos por sección, archivos, URLs, quizzes, etc.
 * Función Moodle: core_course_get_contents
 */
public function obtenerContenidoCurso(int $moodleCourseId): array
{
    return $this->llamar('core_course_get_contents', [
        'courseid' => $moodleCourseId,
        'options'  => [
            ['name' => 'excludemodules',   'value' => '0'],
            ['name' => 'excludecontents',  'value' => '0'],
            ['name' => 'includestealthmodules', 'value' => '1'],
        ],
    ]) ?? [];
}

// ──────────────────────────────────────────────────────────────────
// BLOQUES DEL CURSO
// ──────────────────────────────────────────────────────────────────

/**
 * Devuelve los bloques activos en un curso.
 * Incluye el bloque 'Completion Progress' si está configurado.
 * Función Moodle: core_block_get_course_blocks
 */
public function obtenerBloquesCurso(int $moodleCourseId): array
{
    $resp = $this->llamar('core_block_get_course_blocks', [
        'courseid'          => $moodleCourseId,
        'returncontents'    => false,
    ]);

    return $resp['blocks'] ?? [];
}

// ──────────────────────────────────────────────────────────────────
// ÍTEMS DE CALIFICACIÓN
// ──────────────────────────────────────────────────────────────────

/**
 * Devuelve los ítems de calificación configurados en el curso.
 * Incluye: nombre, tipo, nota máxima, nota mínima, nota de aprobación.
 * Función Moodle: core_grades_get_gradeitems
 */
public function obtenerItemsCalificacion(int $moodleCourseId): array
{
    try {
        $resp = $this->llamar('core_grades_get_gradeitems', [
            'courseid' => $moodleCourseId,
        ]);
        return $resp['gradeItems'] ?? $resp['gradeitems'] ?? [];
    } catch (\Exception $e) {
        // Si la función no está habilitada en el webservice, retornar vacío
        Log::warning("core_grades_get_gradeitems no disponible: " . $e->getMessage());
        return [];
    }
}

// ──────────────────────────────────────────────────────────────────
// FUNCIONES PARA AGREGAR AL WEBSERVICE TOKEN EN MOODLE:
//   - core_course_get_contents
//   - core_block_get_course_blocks
//   - core_grades_get_gradeitems
// ──────────────────────────────────────────────────────────────────
