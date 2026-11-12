<?php

namespace App\Services;

use App\Models\Curso;
use App\Models\Estudiante;
use App\Models\EstudianteSiet;
use App\Models\Matricula;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * SietService
 *
 * Gestiona el formulario SIET post-curso 1:
 *  1. Valida que el estudiante completó el curso 1
 *  2. Guarda los 19 campos en estudiantes_siet
 *  3. Desbloquea el curso 2 en Moodle vía API
 *  4. Registra el desbloqueo en el log
 *
 * La restricción de acceso al curso 2 vive en Moodle
 * como una "Restricción de disponibilidad" de tipo campo de perfil.
 * El campo de perfil personalizado "siet_completado" se activa
 * desde aquí vía core_user_update_users.
 */
class SietService
{
    public function __construct(
        private MoodleService $moodle,
    ) {}

    // ──────────────────────────────────────────────────────────────
    // PUNTO DE ENTRADA PRINCIPAL
    // ──────────────────────────────────────────────────────────────

    /**
     * Recibe los 19 campos SIET del estudiante, los valida, los guarda
     * y desbloquea el acceso al siguiente curso en Moodle.
     *
     * @param Estudiante $estudiante
     * @param array      $datos  Los 19 campos del formulario SIET
     * @return array             Resultado con estado y curso desbloqueado
     * @throws \Exception        Si el estudiante no completó el curso 1
     */
    public function completar(Estudiante $estudiante, array $datos): array
    {
        // ── 1. Verificar que no lo haya completado ya ─────────────
        if ($estudiante->tieneSietCompleto()) {
            return [
                'status'  => 'ya_completado',
                'message' => 'El formulario SIET ya fue completado anteriormente.',
            ];
        }

        // ── 2. Verificar que completó el curso 1 ──────────────────
        $matriculaCurso1 = $this->verificarCurso1Completado($estudiante);

        // ── 3. Guardar los campos SIET ────────────────────────────
        $siet = DB::transaction(function () use ($estudiante, $datos) {
            return EstudianteSiet::updateOrCreate(
                ['estudiante_id' => $estudiante->id],
                array_merge($datos, ['completado_en' => now()])
            );
        });

        // ── 4. Desbloquear el curso 2 en Moodle ───────────────────
        $cursosDesbloqueados = $this->desbloquearSiguienteCurso($estudiante, $matriculaCurso1);

        Log::info('SIET completado y curso desbloqueado', [
            'estudiante_id'       => $estudiante->id,
            'cursos_desbloqueados'=> $cursosDesbloqueados,
        ]);

        return [
            'status'              => 'ok',
            'message'             => 'Formulario completado. Ya puedes continuar tu carrera.',
            'cursos_desbloqueados'=> $cursosDesbloqueados,
        ];
    }

    // ──────────────────────────────────────────────────────────────
    // VERIFICACIONES
    // ──────────────────────────────────────────────────────────────

    /**
     * Verifica que el estudiante tiene el curso 1 completado.
     * Devuelve la matrícula del curso 1.
     *
     * @throws \Exception si no tiene el curso 1 completado
     */
    private function verificarCurso1Completado(Estudiante $estudiante): Matricula
    {
        // Buscar el curso 1 de la carrera (orden_en_carrera = 1)
        // en las matrículas del estudiante
        $matriculaCurso1 = Matricula::where('estudiante_id', $estudiante->id)
            ->whereHas('curso', fn($q) => $q->where('orden_en_carrera', 1))
            ->where('estado', 'COMPLETADA')
            ->first();

        if (!$matriculaCurso1) {
            throw new \Exception(
                'El estudiante no ha completado el curso 1 de la carrera. ' .
                'Completa el primer curso antes de llenar el formulario SIET.'
            );
        }

        return $matriculaCurso1;
    }

    // ──────────────────────────────────────────────────────────────
    // DESBLOQUEO EN MOODLE
    // ──────────────────────────────────────────────────────────────

    /**
     * Activa el campo de perfil "siet_completado" en Moodle.
     * Esto levanta automáticamente la restricción de disponibilidad
     * del curso 2 que está configurada en Moodle.
     *
     * También busca el curso 2 en la BD y lo matricula si no lo está.
     */
    private function desbloquearSiguienteCurso(
        Estudiante $estudiante,
        Matricula $matriculaCurso1
    ): array {
        $desbloqueados = [];
        $tipoPrograma  = $matriculaCurso1->curso->tipo_programa;

        // ── Activar campo de perfil en Moodle ─────────────────────
        try {
            $this->moodle->activarCampoSiet($estudiante->moodle_user_id);
        } catch (\Exception $e) {
            Log::error('Error activando campo SIET en Moodle', [
                'estudiante_id' => $estudiante->id,
                'error'         => $e->getMessage(),
            ]);
            // No abortar: el formulario ya fue guardado
        }

        // ── Encontrar el curso 2 del mismo programa ───────────────
        $curso2 = Curso::where('tipo_programa', $tipoPrograma)
            ->where('orden_en_carrera', 2)
            ->where('activo', true)
            ->first();

        if (!$curso2) {
            Log::warning('No se encontró curso 2 del programa', [
                'tipo_programa' => $tipoPrograma,
            ]);
            return $desbloqueados;
        }

        // ── Registrar log de desbloqueo ───────────────────────────
        $logData = [
            'estudiante_id'              => $estudiante->id,
            'moodle_user_id'             => $estudiante->moodle_user_id,
            'moodle_curso_desbloqueado_id'=> $curso2->moodle_course_id,
        ];

        try {
            \App\Models\LogDesbloqueoSiet::create(
                array_merge($logData, ['estado' => 'OK'])
            );
            $desbloqueados[] = [
                'curso_id'   => $curso2->id,
                'nombre'     => $curso2->nombre,
                'moodle_id'  => $curso2->moodle_course_id,
            ];
        } catch (\Exception $e) {
            \App\Models\LogDesbloqueoSiet::create(
                array_merge($logData, [
                    'estado'         => 'ERROR',
                    'error_mensaje'  => $e->getMessage(),
                ])
            );
        }

        return $desbloqueados;
    }

    // ──────────────────────────────────────────────────────────────
    // ESTADO DEL FORMULARIO
    // ──────────────────────────────────────────────────────────────

    /**
     * Devuelve el estado del formulario SIET de un estudiante.
     * Usado por el frontend para mostrar el formulario o bloquearlo.
     */
    public function estado(Estudiante $estudiante): array
    {
        $siet = $estudiante->siet;

        return [
            'completado'      => $siet?->estaCompleto() ?? false,
            'completado_en'   => $siet?->completado_en?->toDateTimeString(),
            'datos'           => $siet?->estaCompleto() ? $siet : null,
        ];
    }

    // ──────────────────────────────────────────────────────────────
    // EXPORTACIÓN SIET
    // ──────────────────────────────────────────────────────────────

    /**
     * Genera los datos en el formato exacto del SIET para una IETDH
     * en un período determinado.
     *
     * Los valores de los campos enum se convierten al código numérico
     * que exige el SIET (ej: sexo M=1, F=2).
     */
    public function exportarParaSiet(string $ietdhId, string $desde, string $hasta): array
    {
        $matriculas = Matricula::with(['estudiante.siet', 'curso'])
            ->whereHas('distribuidor.distribuidorIetdhs', fn($q) =>
                $q->where('ietdh_id', $ietdhId)
            )
            ->whereBetween('fecha_matricula', [$desde, $hasta])
            ->where('estado', '!=', 'CANCELADA')
            ->get();

        $consecutivo = 1;

        return $matriculas->map(function ($matricula) use (&$consecutivo) {
            $e    = $matricula->estudiante;
            $siet = $e->siet;

            return [
                // Columna A — CONSECUTIVO
                'CONSECUTIVO'        => $consecutivo++,
                // Columna B — FECHA DE INGRESO (dd/mm/yyyy)
                'FECHA_INGRESO'      => $matricula->fecha_matricula->format('d/m/Y'),
                // Columna C — JORNADA (1=Diurna 2=Nocturna 3=Fines 4=Virtual)
                'JORNADA'            => $siet?->jornada ?? 4,
                // Columna D — TIPO DE IDENTIFICACIÓN (código SIET)
                'TIPO_IDENTIFICACION'=> $this->codigoTipoDoc($e->tipo_documento),
                // Columna E — NÚMERO DE IDENTIFICACIÓN
                'NUMERO_IDENTIFICACION' => $e->numero_documento,
                // Columna F — NOMBRES
                'NOMBRES'            => $e->nombres,
                // Columna G — APELLIDOS
                'APELLIDOS'          => $e->apellidos,
                // Columna H — SEXO BIOLÓGICO (1=M 2=F)
                'SEXO_BIOLOGICO'     => $this->codigoSexo($siet?->sexo_biologico),
                // Columna I — ESTADO CIVIL
                'ESTADO_CIVIL'       => $this->codigoEstadoCivil($siet?->estado_civil),
                // Columna J — FECHA NACIMIENTO
                'FECHA_NACIMIENTO'   => $siet?->fecha_nacimiento?->format('d/m/Y') ?? '',
                // Columna K — LUGAR DE ORIGEN (código DANE)
                'LUGAR_ORIGEN'       => $siet?->municipio_dane ?? '',
                // Columna L — ESTRATO
                'ESTRATO'            => $siet?->estrato ?? '',
                // Columna M — RÉGIMEN DE SALUD
                'REGIMEN_SALUD'      => $this->codigoRegimen($siet?->regimen_salud),
                // Columna N — NIVEL DE FORMACIÓN
                'NIVEL_FORMACION'    => $siet?->nivel_formacion ?? '',
                // Columna O — OCUPACIÓN
                'OCUPACION'          => $siet?->ocupacion ?? '',
                // Columna P — DISCAPACIDAD (1=Sí 2=No)
                'DISCAPACIDAD'       => $siet?->discapacidad ? 1 : 2,
                // Multiculturalidad (1=Sí 2=No)
                'INDIGENA'           => $siet?->indigena ? 1 : 2,
                'AFRODESCENDIENTE'   => $siet?->afrodescendiente ? 1 : 2,
                'DESPLAZADO'         => $siet?->desplazado ? 1 : 2,
                'POBLACION_FRONTERA' => $siet?->poblacion_frontera ? 1 : 2,
                'CABEZA_FAMILIA'     => $siet?->cabeza_familia ? 1 : 2,
                'REINSERTADO'        => $siet?->reinsertado ? 1 : 2,
                'POBLACION_ROOM'     => $siet?->poblacion_room ? 1 : 2,
            ];
        })->toArray();
    }

    // ──────────────────────────────────────────────────────────────
    // CONVERSORES A CÓDIGOS SIET
    // ──────────────────────────────────────────────────────────────

    private function codigoTipoDoc(string $tipo): int
    {
        return match ($tipo) {
            'RC'  => 1,
            'TI'  => 2,
            'CC'  => 3,
            'CE'  => 4,
            'PA'  => 5,
            'PPT' => 6,
            default => 3,
        };
    }

    private function codigoSexo(?string $sexo): int
    {
        return match ($sexo) {
            'M' => 1,
            'F' => 2,
            default => 1,
        };
    }

    private function codigoEstadoCivil(?string $estado): int
    {
        return match ($estado) {
            'SOLTERO'     => 1,
            'CASADO'      => 2,
            'UNION_LIBRE' => 3,
            'SEPARADO'    => 4,
            'VIUDO'       => 5,
            default       => 1,
        };
    }

    private function codigoRegimen(?string $regimen): int
    {
        return match ($regimen) {
            'CONTRIBUTIVO' => 1,
            'SUBSIDIADO'   => 2,
            'ESPECIAL'     => 3,
            'NINGUNO'      => 4,
            default        => 1,
        };
    }
}
