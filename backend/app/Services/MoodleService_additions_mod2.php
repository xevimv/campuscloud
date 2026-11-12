<?php
// MÉTODOS ADICIONALES para agregar a app/Services/MoodleService.php
// (copiar dentro de la clase MoodleService existente)

// ──────────────────────────────────────────────
// COHORTES — gestión
// ──────────────────────────────────────────────

/**
 * Crea una cohorte en Moodle a nivel de sitio.
 * Devuelve el ID de la cohorte creada.
 *
 * @param string $nombre  Nombre legible (ej: "Distribuidor HSEQ")
 * @param string $idnumber  Identificador único interno (ej: "dist_abc123")
 */
public function crearCohorte(string $nombre, string $idnumber): int
{
    $resp = $this->llamar('core_cohort_create_cohorts', [
        'cohorts' => [[
            'categorytype' => ['type' => 'system', 'value' => ''],
            'name'         => $nombre,
            'idnumber'     => $idnumber,
            'description'  => "Cohorte del distribuidor: {$nombre}",
        ]],
    ]);

    if (empty($resp[0]['id'])) {
        throw new \RuntimeException("Moodle no devolvió el ID de la cohorte creada para: {$nombre}");
    }

    Log::info('Moodle: cohorte creada', ['cohort_id' => $resp[0]['id'], 'nombre' => $nombre]);

    return (int) $resp[0]['id'];
}

/**
 * Elimina una cohorte de Moodle.
 */
public function eliminarCohorte(int $cohortId): void
{
    $this->llamar('core_cohort_delete_cohorts', [
        'cohortids' => [$cohortId],
    ]);
    Log::info('Moodle: cohorte eliminada', ['cohort_id' => $cohortId]);
}

// ──────────────────────────────────────────────
// GRUPOS — gestión dentro de un curso
// ──────────────────────────────────────────────

/**
 * Crea un grupo en un curso específico de Moodle.
 * Devuelve el ID del grupo creado.
 *
 * @param int    $moodleCourseId
 * @param string $nombre   Nombre del grupo (ej: "HSEQ - escuelahseq.com")
 * @param string $idnumber Identificador único (ej: "dist_abc123_course_47")
 */
public function crearGrupo(int $moodleCourseId, string $nombre, string $idnumber): int
{
    $resp = $this->llamar('core_group_create_groups', [
        'groups' => [[
            'courseid'    => $moodleCourseId,
            'name'        => $nombre,
            'idnumber'    => $idnumber,
            'description' => "Grupo del distribuidor en el curso",
        ]],
    ]);

    if (empty($resp[0]['id'])) {
        throw new \RuntimeException(
            "Moodle no devolvió el ID del grupo creado: {$nombre} en curso {$moodleCourseId}"
        );
    }

    Log::info('Moodle: grupo creado', [
        'group_id'  => $resp[0]['id'],
        'course_id' => $moodleCourseId,
        'nombre'    => $nombre,
    ]);

    return (int) $resp[0]['id'];
}

/**
 * Elimina un grupo de Moodle.
 */
public function eliminarGrupo(int $groupId): void
{
    $this->llamar('core_group_delete_groups', [
        'groupids' => [$groupId],
    ]);
    Log::info('Moodle: grupo eliminado', ['group_id' => $groupId]);
}

// ──────────────────────────────────────────────
// NUEVA FUNCIÓN para agregar al MoodleService
// También agregar esta función al webservice token en Moodle:
//   - core_cohort_create_cohorts
//   - core_cohort_delete_cohorts
//   - core_group_create_groups
//   - core_group_delete_groups
// ──────────────────────────────────────────────
