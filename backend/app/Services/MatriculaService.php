<?php

namespace App\Services;

use App\Models\Curso;
use App\Models\Distribuidor;
use App\Models\Estudiante;
use App\Models\LogWebhook;
use App\Models\Matricula;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * MatriculaService
 *
 * Orquesta el flujo completo de una matrícula:
 *  1. Valida el precio mínimo (Piso 2)
 *  2. Crea o recupera el estudiante en la BD local
 *  3. Crea o recupera el usuario en Moodle
 *  4. Matricula en el curso
 *  5. Agrega a la cohorte del distribuidor
 *  6. Agrega al grupo del distribuidor en ese curso
 *  7. Registra la matrícula en la BD
 *  8. Registra el log
 */
class MatriculaService
{
    public function __construct(
        private MoodleService $moodle,
    ) {}

    /**
     * Punto de entrada principal. Recibe los datos normalizados
     * del EpaycoService y ejecuta el flujo completo.
     *
     * @return Matricula  La matrícula creada
     * @throws \Exception con mensaje descriptivo para el log
     */
    public function procesar(array $datos): Matricula
    {
        // ── 1. Recuperar entidades de configuración ──────────────────
        $distribuidor = Distribuidor::find($datos['distribuidor_id']);
        if (!$distribuidor || !$distribuidor->activo) {
            throw new \Exception("Distribuidor no encontrado o inactivo: {$datos['distribuidor_id']}");
        }

        $curso = Curso::where('moodle_course_id', $datos['moodle_course_id'])
            ->with('precioVigente')
            ->first();

        if (!$curso || !$curso->activo) {
            throw new \Exception("Curso no encontrado o inactivo: {$datos['moodle_course_id']}");
        }

        // ── 2. Validar precio mínimo (Piso 2) ───────────────────────
        $this->validarPrecioMinimo($datos['monto'], $curso, $datos['epayco_ref']);

        // ── 3. Todo dentro de una transacción de BD ──────────────────
        return DB::transaction(function () use ($datos, $distribuidor, $curso) {

            // ── 3a. Crear o recuperar estudiante ─────────────────────
            $estudiante = $this->resolverEstudiante($datos, $distribuidor);

            // ── 3b. Verificar que no esté ya matriculado ─────────────
            $yaMatriculado = Matricula::where('estudiante_id', $estudiante->id)
                ->where('curso_id', $curso->id)
                ->exists();

            if ($yaMatriculado) {
                throw new \Exception("El estudiante ya está matriculado en este curso: {$estudiante->email}");
            }

            // ── 3c. Crear o recuperar usuario en Moodle ──────────────
            $moodleUserId = $this->resolverUsuarioMoodle($estudiante, $datos);

            // ── 3d. Matricular en Moodle ─────────────────────────────
            $this->moodle->matricularUsuario($moodleUserId, $curso->moodle_course_id);

            // ── 3e. Agregar a cohorte del distribuidor ───────────────
            if ($distribuidor->moodle_cohort_id) {
                $this->moodle->agregarACohorte($distribuidor->moodle_cohort_id, $moodleUserId);
            }

            // ── 3f. Agregar al grupo del distribuidor en el curso ────
            // El group_id se busca en la tabla distribuidor_grupos_cursos
            // (pendiente de crear cuando desarrollemos el módulo de distribuidores)
            $groupId = $this->resolverGroupId($distribuidor, $curso);
            if ($groupId) {
                $this->moodle->agregarAGrupo($groupId, $moodleUserId);
            }

            // ── 3g. Registrar matrícula en BD ─────────────────────────
            $matricula = Matricula::create([
                'estudiante_id'       => $estudiante->id,
                'curso_id'            => $curso->id,
                'distribuidor_id'     => $distribuidor->id,
                'epayco_ref'          => $datos['epayco_ref'],
                'monto_pagado'        => $datos['monto'],
                'fee_nomaddi_cobrado' => $curso->getFeeNomaddiAttribute() ?? 0,
                'moodle_group_id'     => $groupId,
                'estado'              => 'ACTIVA',
                'fecha_matricula'     => now(),
            ]);

            Log::info('Matrícula completada', [
                'matricula_id'  => $matricula->id,
                'estudiante'    => $estudiante->email,
                'curso'         => $curso->nombre,
                'distribuidor'  => $distribuidor->nombre,
            ]);

            return $matricula;
        });
    }

    // ──────────────────────────────────────────────────────────────────
    // HELPERS PRIVADOS
    // ──────────────────────────────────────────────────────────────────

    /**
     * Lanza excepción si el monto pagado es menor al precio mínimo del curso.
     */
    private function validarPrecioMinimo(float $monto, Curso $curso, string $ref): void
    {
        $precioMinimo = $curso->precioVigente?->precio_minimo_publico;

        if ($precioMinimo === null) {
            Log::warning('Curso sin precio configurado', ['curso_id' => $curso->id]);
            // Sin precio configurado no podemos validar — dejamos pasar con warning
            return;
        }

        if ($monto < (float) $precioMinimo) {
            throw new \Exception(
                "PRECIO_BAJO: monto {$monto} < mínimo {$precioMinimo} para curso {$curso->nombre} (ref: {$ref})"
            );
        }
    }

    /**
     * Busca el estudiante por documento o email.
     * Si no existe, lo crea. Si existe, actualiza moodle_user_id si lo tenemos.
     */
    private function resolverEstudiante(array $datos, Distribuidor $distribuidor): Estudiante
    {
        $estudiante = Estudiante::where('tipo_documento', $datos['tipo_documento'])
            ->where('numero_documento', $datos['numero_documento'])
            ->first();

        if (!$estudiante) {
            $estudiante = Estudiante::create([
                'tipo_documento'   => $datos['tipo_documento'],
                'numero_documento' => $datos['numero_documento'],
                'nombres'          => $datos['nombres'],
                'apellidos'        => $datos['apellidos'],
                'email'            => $datos['email'],
                'distribuidor_id'  => $distribuidor->id,
            ]);
        }

        return $estudiante;
    }

    /**
     * Busca el usuario en Moodle por email.
     * Si no existe, lo crea. Actualiza moodle_user_id en la BD local.
     */
    private function resolverUsuarioMoodle(Estudiante $estudiante, array $datos): int
    {
        // Si ya tenemos el ID en nuestra BD, usarlo directamente
        if ($estudiante->moodle_user_id) {
            return $estudiante->moodle_user_id;
        }

        // Buscar en Moodle por email
        $moodleUser = $this->moodle->buscarUsuarioPorEmail($estudiante->email);

        if (!$moodleUser) {
            // Crear nuevo usuario en Moodle
            $moodleUser = $this->moodle->crearUsuario([
                'nombres'          => $estudiante->nombres,
                'apellidos'        => $estudiante->apellidos,
                'email'            => $estudiante->email,
                'tipo_documento'   => $estudiante->tipo_documento,
                'numero_documento' => $estudiante->numero_documento,
            ]);
        }

        $moodleUserId = (int) $moodleUser['id'];

        // Guardar el ID de Moodle en nuestra BD
        $estudiante->update(['moodle_user_id' => $moodleUserId]);

        return $moodleUserId;
    }

    /**
     * Busca el group_id de Moodle para este distribuidor en este curso.
     * Pendiente: implementar tabla distribuidor_grupos_cursos en el módulo de distribuidores.
     */
    private function resolverGroupId(Distribuidor $distribuidor, Curso $curso): ?int
    {
        // TODO: implementar cuando se desarrolle el módulo de distribuidores
        // return DistribuidorGrupoCurso::where('distribuidor_id', $distribuidor->id)
        //     ->where('curso_id', $curso->id)
        //     ->value('moodle_group_id');
        return null;
    }
}
