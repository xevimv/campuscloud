<?php

namespace App\Services;

use App\Models\Curso;
use App\Models\Distribuidor;
use App\Models\DistribuidorGrupoCurso;
use App\Models\DistribuidorIetdh;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * DistribuidorService
 *
 * Gestiona el ciclo de vida completo de un distribuidor:
 *  - Crear: BD + cohorte Moodle + grupos en todos los cursos
 *  - Actualizar: datos básicos
 *  - Desactivar: sin eliminar historial
 *  - Asignar IETDH por tipo de programa
 *
 * Regla clave de sincronización:
 *  Al crear un distribuidor → crear 1 cohorte + N grupos (uno por cada curso activo)
 *  Al crear un curso nuevo  → CursoService crea 1 grupo por cada distribuidor activo
 */
class DistribuidorService
{
    public function __construct(
        private MoodleService $moodle,
    ) {}

    // ──────────────────────────────────────────────────────────────
    // CREAR DISTRIBUIDOR
    // ──────────────────────────────────────────────────────────────

    /**
     * Crea un distribuidor completo:
     * 1. Registra en BD
     * 2. Crea cohorte en Moodle
     * 3. Crea grupos en todos los cursos activos
     * 4. Asigna IETDHs por tipo de programa
     *
     * @param array $datos {
     *   nombre, dominio, email_contacto, epayco_receiver_id,
     *   ietdhs: [{ ietdh_id, tipo_programa }]
     * }
     */
    public function crear(array $datos): Distribuidor
    {
        return DB::transaction(function () use ($datos) {

            // ── 1. Crear registro en BD ───────────────────────────────
            $distribuidor = Distribuidor::create([
                'nombre'              => $datos['nombre'],
                'dominio'             => $datos['dominio'],
                'email_contacto'      => $datos['email_contacto'],
                'epayco_receiver_id'  => $datos['epayco_receiver_id'] ?? null,
                'activo'              => true,
            ]);

            // ── 2. Crear cohorte en Moodle ────────────────────────────
            $idnumber   = 'dist_' . $distribuidor->id;
            $cohortId   = $this->moodle->crearCohorte(
                nombre:   $datos['nombre'],
                idnumber: $idnumber
            );

            $distribuidor->update(['moodle_cohort_id' => $cohortId]);

            // ── 3. Crear grupos en todos los cursos activos ───────────
            $this->crearGruposEnTodosLosCursos($distribuidor);

            // ── 4. Asignar IETDHs ─────────────────────────────────────
            if (!empty($datos['ietdhs'])) {
                foreach ($datos['ietdhs'] as $asignacion) {
                    $this->asignarIetdh(
                        distribuidor: $distribuidor,
                        ietdhId:      $asignacion['ietdh_id'],
                        tipoPrograma: $asignacion['tipo_programa'],
                    );
                }
            }

            Log::info('Distribuidor creado', [
                'distribuidor_id' => $distribuidor->id,
                'nombre'          => $distribuidor->nombre,
                'cohort_id'       => $cohortId,
            ]);

            return $distribuidor->fresh(['distribuidorIetdhs', 'grupos']);
        });
    }

    // ──────────────────────────────────────────────────────────────
    // ACTUALIZAR DISTRIBUIDOR
    // ──────────────────────────────────────────────────────────────

    /**
     * Actualiza datos básicos del distribuidor.
     * No toca Moodle (cohorte y grupos se mantienen).
     */
    public function actualizar(Distribuidor $distribuidor, array $datos): Distribuidor
    {
        $distribuidor->update(array_filter([
            'nombre'             => $datos['nombre']             ?? null,
            'dominio'            => $datos['dominio']            ?? null,
            'email_contacto'     => $datos['email_contacto']     ?? null,
            'epayco_receiver_id' => $datos['epayco_receiver_id'] ?? null,
        ], fn($v) => $v !== null));

        return $distribuidor->fresh();
    }

    // ──────────────────────────────────────────────────────────────
    // DESACTIVAR / REACTIVAR
    // ──────────────────────────────────────────────────────────────

    /**
     * Desactiva un distribuidor.
     * NO elimina historial ni borra datos de Moodle.
     * Los estudiantes existentes mantienen acceso a sus cursos.
     */
    public function desactivar(Distribuidor $distribuidor): void
    {
        $distribuidor->update(['activo' => false]);
        Log::info('Distribuidor desactivado', ['id' => $distribuidor->id]);
    }

    public function reactivar(Distribuidor $distribuidor): void
    {
        $distribuidor->update(['activo' => true]);
        Log::info('Distribuidor reactivado', ['id' => $distribuidor->id]);
    }

    // ──────────────────────────────────────────────────────────────
    // GESTIÓN DE IETDHs
    // ──────────────────────────────────────────────────────────────

    /**
     * Asigna o reemplaza la IETDH de un distribuidor para un tipo de programa.
     * Si ya tenía una asignación, la reemplaza.
     */
    public function asignarIetdh(
        Distribuidor $distribuidor,
        string $ietdhId,
        string $tipoPrograma
    ): DistribuidorIetdh {
        return DistribuidorIetdh::updateOrCreate(
            [
                'distribuidor_id' => $distribuidor->id,
                'tipo_programa'   => $tipoPrograma,
            ],
            [
                'ietdh_id' => $ietdhId,
            ]
        );
    }

    // ──────────────────────────────────────────────────────────────
    // SINCRONIZACIÓN CON MOODLE
    // ──────────────────────────────────────────────────────────────

    /**
     * Crea un grupo en Moodle para este distribuidor en TODOS los cursos activos.
     * Se llama al crear un distribuidor nuevo.
     *
     * Nombre del grupo: "{nombre_distribuidor}" (legible en Moodle)
     * idnumber: "dist_{distribuidor_id}_course_{curso_id}" (único en Moodle)
     */
    public function crearGruposEnTodosLosCursos(Distribuidor $distribuidor): void
    {
        $cursos = Curso::where('activo', true)->get();

        foreach ($cursos as $curso) {
            $this->crearGrupoEnCurso($distribuidor, $curso);
        }

        Log::info('Grupos creados en todos los cursos', [
            'distribuidor_id' => $distribuidor->id,
            'total_cursos'    => $cursos->count(),
        ]);
    }

    /**
     * Crea un grupo de Moodle para un distribuidor en un curso específico
     * y lo registra en distribuidor_grupos_cursos.
     *
     * Se llama:
     * - Al crear un distribuidor (para todos los cursos)
     * - Al crear un curso nuevo (para todos los distribuidores)
     */
    public function crearGrupoEnCurso(Distribuidor $distribuidor, Curso $curso): DistribuidorGrupoCurso
    {
        // Verificar que no exista ya
        $existente = DistribuidorGrupoCurso::where('distribuidor_id', $distribuidor->id)
            ->where('curso_id', $curso->id)
            ->first();

        if ($existente) {
            return $existente;
        }

        $idnumber = "dist_{$distribuidor->id}_course_{$curso->id}";
        $nombre   = $distribuidor->nombre;

        $moodleGroupId = $this->moodle->crearGrupo(
            moodleCourseId: $curso->moodle_course_id,
            nombre:         $nombre,
            idnumber:       $idnumber,
        );

        return DistribuidorGrupoCurso::create([
            'distribuidor_id' => $distribuidor->id,
            'curso_id'        => $curso->id,
            'moodle_group_id' => $moodleGroupId,
        ]);
    }

    /**
     * Devuelve el group_id de Moodle para un distribuidor en un curso.
     * Resuelve el TODO de MatriculaService::resolverGroupId().
     */
    public function obtenerGroupId(Distribuidor $distribuidor, Curso $curso): ?int
    {
        return DistribuidorGrupoCurso::where('distribuidor_id', $distribuidor->id)
            ->where('curso_id', $curso->id)
            ->value('moodle_group_id');
    }
}
