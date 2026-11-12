<?php

namespace App\Services;

use App\Models\AuditoriaCurso;
use App\Models\Curso;
use App\Models\Usuario;
use Illuminate\Support\Facades\Log;

/**
 * AuditoriaService
 *
 * Revisa automáticamente 7 criterios de calidad en cada curso de Moodle:
 *
 *  1. ✅ Título correcto       → el nombre en Moodle coincide con el esperado
 *  2. ✅ Bloque de progreso    → tiene el plugin 'Completion Progress' activo
 *  3. ✅ Módulos               → tiene el número esperado de secciones con contenido
 *  4. ✅ Exámenes              → tiene el número esperado de cuestionarios (quiz)
 *  5. ✅ Certificado           → tiene configurada la actividad de certificado
 *  6. ✅ Sin enlaces rotos     → todos los recursos tipo URL responden correctamente
 *  7. ✅ Calificación          → tiene configurado al menos un ítem de calificación
 *
 * Resultado global:
 *  APROBADO    → todos los checks en verde
 *  ADVERTENCIA → algún check en amarillo (certificado no configurado, etc.)
 *  REPROBADO   → algún check en rojo (título incorrecto, sin exámenes, etc.)
 */
class AuditoriaService
{
    public function __construct(
        private MoodleService $moodle,
    ) {}

    // ──────────────────────────────────────────────────────────────
    // AUDITAR UN CURSO
    // ──────────────────────────────────────────────────────────────

    /**
     * Ejecuta los 7 checks en un curso y guarda el resultado.
     */
    public function auditarCurso(Curso $curso, ?Usuario $auditor = null): AuditoriaCurso
    {
        $checks  = [];
        $detalle = [];

        try {
            // Obtener datos del curso desde Moodle
            $infoCurso  = $this->moodle->obtenerCurso($curso->moodle_course_id);
            $contenidos = $this->moodle->obtenerContenidoCurso($curso->moodle_course_id);
            $bloques    = $this->moodle->obtenerBloquesCurso($curso->moodle_course_id);
            $items      = $this->moodle->obtenerItemsCalificacion($curso->moodle_course_id);

        } catch (\Exception $e) {
            Log::error("Error auditando curso {$curso->id}", ['error' => $e->getMessage()]);

            return AuditoriaCurso::create([
                'curso_id'       => $curso->id,
                'resultado_global' => 'REPROBADO',
                'detalle'        => ['error_conexion' => $e->getMessage()],
                'auditado_por'   => $auditor?->id,
            ]);
        }

        // ── 1. Título correcto ────────────────────────────────────
        $tituloEsperado = $curso->auditoria_titulo_esperado ?? $curso->nombre;
        $tituloMoodle   = $infoCurso['fullname'] ?? '';
        $checks['check_titulo'] = trim($tituloMoodle) === trim($tituloEsperado);
        $detalle['titulo'] = [
            'esperado' => $tituloEsperado,
            'encontrado' => $tituloMoodle,
            'ok' => $checks['check_titulo'],
        ];

        // ── 2. Bloque Completion Progress ─────────────────────────
        $tieneProgreso = collect($bloques)->contains(
            fn($b) => str_contains(strtolower($b['name'] ?? ''), 'completion') ||
                      str_contains(strtolower($b['type'] ?? ''), 'completionstatus') ||
                      ($b['instanceid'] ?? null) !== null && str_contains(strtolower($b['type'] ?? ''), 'progress')
        );
        $checks['check_bloque_progreso'] = $tieneProgreso;
        $detalle['bloque_progreso'] = [
            'ok'       => $tieneProgreso,
            'bloques_encontrados' => collect($bloques)->pluck('name')->filter()->values()->toArray(),
        ];

        // ── 3. Módulos (secciones con contenido) ─────────────────
        // Una sección con contenido = sección que tiene al menos 1 módulo (recurso, página, video, etc.)
        $seccionesConContenido = collect($contenidos)
            ->filter(fn($s) => !empty($s['modules']) && $s['section'] > 0)
            ->count();

        $modulosEsperados = $curso->auditoria_modulos_esperados;
        $checks['check_modulos'] = $seccionesConContenido >= $modulosEsperados;
        $detalle['modulos'] = [
            'esperados'  => $modulosEsperados,
            'encontrados'=> $seccionesConContenido,
            'ok'         => $checks['check_modulos'],
        ];

        // ── 4. Exámenes / Cuestionarios ───────────────────────────
        $examenes = collect($contenidos)
            ->flatMap(fn($s) => $s['modules'] ?? [])
            ->filter(fn($m) => ($m['modname'] ?? '') === 'quiz')
            ->count();

        $examenesEsperados = $curso->auditoria_examenes_esperados;
        $checks['check_examenes'] = $examenes >= $examenesEsperados;
        $detalle['examenes'] = [
            'esperados'   => $examenesEsperados,
            'encontrados' => $examenes,
            'ok'          => $checks['check_examenes'],
        ];

        // ── 5. Actividad de certificado ───────────────────────────
        $tieneCertificado = collect($contenidos)
            ->flatMap(fn($s) => $s['modules'] ?? [])
            ->contains(fn($m) => in_array($m['modname'] ?? '', ['customcert', 'certificate', 'simplecertificate']));

        $checks['check_certificado'] = $tieneCertificado;
        $detalle['certificado'] = [
            'ok'      => $tieneCertificado,
            'mensaje' => $tieneCertificado
                ? 'Actividad de certificado configurada.'
                : 'No se encontró actividad de certificado (customcert, certificate).',
        ];

        // ── 6. Sin enlaces rotos ──────────────────────────────────
        $resultadoEnlaces = $this->verificarEnlaces($contenidos);
        $checks['check_enlaces'] = $resultadoEnlaces['ok'];
        $detalle['enlaces'] = $resultadoEnlaces;

        // ── 7. Configuración de calificación ──────────────────────
        // Verifica que haya al menos un ítem de calificación que no sea el total del curso
        $itemsCalificacion = collect($items)->filter(
            fn($i) => ($i['itemtype'] ?? '') !== 'course'
        )->count();

        $checks['check_calificacion'] = $itemsCalificacion > 0;
        $detalle['calificacion'] = [
            'ok'           => $checks['check_calificacion'],
            'items_encontrados' => $itemsCalificacion,
            'detalle_items' => collect($items)->map(fn($i) => [
                'nombre' => $i['itemname'] ?? $i['itemtype'] ?? 'Sin nombre',
                'tipo'   => $i['itemtype'] ?? '—',
                'max'    => $i['grademax'] ?? '—',
                'min'    => $i['grademin'] ?? '—',
                'pass'   => $i['gradepass'] ?? '—',
            ])->toArray(),
        ];

        // ── Resultado global ──────────────────────────────────────
        $resultadoGlobal = $this->calcularResultadoGlobal($checks);

        return AuditoriaCurso::create([
            'curso_id'              => $curso->id,
            'resultado_global'      => $resultadoGlobal,
            'check_titulo'          => $checks['check_titulo'],
            'check_bloque_progreso' => $checks['check_bloque_progreso'],
            'check_modulos'         => $checks['check_modulos'],
            'check_examenes'        => $checks['check_examenes'],
            'check_certificado'     => $checks['check_certificado'],
            'check_enlaces'         => $checks['check_enlaces'],
            'check_calificacion'    => $checks['check_calificacion'],
            'detalle'               => $detalle,
            'auditado_por'          => $auditor?->id,
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // AUDITAR TODOS LOS CURSOS
    // ──────────────────────────────────────────────────────────────

    /**
     * Audita todos los cursos activos del panel.
     * Devuelve un resumen con conteos por resultado.
     */
    public function auditarTodos(?Usuario $auditor = null): array
    {
        $cursos     = Curso::where('activo', true)->get();
        $aprobados  = 0;
        $advertencias = 0;
        $reprobados = 0;

        foreach ($cursos as $curso) {
            $resultado = $this->auditarCurso($curso, $auditor);
            match ($resultado->resultado_global) {
                'APROBADO'    => $aprobados++,
                'ADVERTENCIA' => $advertencias++,
                'REPROBADO'   => $reprobados++,
            };
            // Pausa breve entre cursos para no saturar Moodle
            usleep(300_000); // 300ms
        }

        Log::info('Auditoría completa', [
            'aprobados'    => $aprobados,
            'advertencias' => $advertencias,
            'reprobados'   => $reprobados,
        ]);

        return [
            'total'        => $cursos->count(),
            'aprobados'    => $aprobados,
            'advertencias' => $advertencias,
            'reprobados'   => $reprobados,
        ];
    }

    // ──────────────────────────────────────────────────────────────
    // HELPERS PRIVADOS
    // ──────────────────────────────────────────────────────────────

    /**
     * Verifica que los recursos tipo URL del curso respondan correctamente.
     * Solo revisa módulos de tipo 'url' — no rastrea links dentro de páginas HTML.
     */
    private function verificarEnlaces(array $contenidos): array
    {
        $urls = collect($contenidos)
            ->flatMap(fn($s) => $s['modules'] ?? [])
            ->filter(fn($m) => ($m['modname'] ?? '') === 'url')
            ->map(fn($m) => [
                'nombre' => $m['name'] ?? 'Sin nombre',
                'url'    => $m['contents'][0]['fileurl'] ?? null,
            ])
            ->filter(fn($u) => !empty($u['url']))
            ->toArray();

        if (empty($urls)) {
            return ['ok' => true, 'mensaje' => 'No hay recursos URL para verificar.', 'rotos' => []];
        }

        $rotos = [];

        foreach ($urls as $enlace) {
            try {
                $contexto = stream_context_create([
                    'http' => [
                        'method'          => 'HEAD',
                        'timeout'         => 5,
                        'follow_location' => true,
                        'ignore_errors'   => true,
                    ],
                    'ssl' => ['verify_peer' => false],
                ]);

                $headers = @get_headers($enlace['url'], false, $contexto);
                $codigo  = $headers ? (int) substr($headers[0], 9, 3) : 0;

                if ($codigo === 0 || $codigo >= 400) {
                    $rotos[] = [
                        'nombre' => $enlace['nombre'],
                        'url'    => $enlace['url'],
                        'codigo' => $codigo,
                    ];
                }
            } catch (\Exception $e) {
                $rotos[] = [
                    'nombre' => $enlace['nombre'],
                    'url'    => $enlace['url'],
                    'error'  => $e->getMessage(),
                ];
            }
        }

        return [
            'ok'              => empty($rotos),
            'total_revisados' => count($urls),
            'rotos'           => $rotos,
            'mensaje'         => empty($rotos)
                ? "Todos los enlaces revisados están activos ({$this->count($urls)} URL)."
                : count($rotos) . " enlace(s) roto(s) encontrado(s).",
        ];
    }

    private function count(array $arr): int { return count($arr); }

    /**
     * Calcula el resultado global según los checks:
     * - REPROBADO si falla título, módulos o exámenes (críticos)
     * - ADVERTENCIA si falla certificado, enlaces o calificación (importantes)
     * - APROBADO si todo está bien
     */
    private function calcularResultadoGlobal(array $checks): string
    {
        $criticos   = ['check_titulo', 'check_modulos', 'check_examenes'];
        $importantes = ['check_certificado', 'check_enlaces', 'check_calificacion', 'check_bloque_progreso'];

        foreach ($criticos as $check) {
            if ($checks[$check] === false) return 'REPROBADO';
        }

        foreach ($importantes as $check) {
            if ($checks[$check] === false) return 'ADVERTENCIA';
        }

        return 'APROBADO';
    }
}
