<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditoriaCurso;
use App\Models\Curso;
use App\Services\AuditoriaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditoriaController extends Controller
{
    public function __construct(
        private AuditoriaService $auditoria,
    ) {}

    /**
     * GET /api/auditoria
     * Resumen general: última auditoría de cada curso activo.
     */
    public function resumen(): JsonResponse
    {
        $cursos = Curso::where('activo', true)
            ->with(['ultimaAuditoria'])
            ->orderBy('tipo_programa')
            ->orderBy('orden_en_carrera')
            ->get()
            ->map(fn($c) => [
                'id'                        => $c->id,
                'moodle_course_id'          => $c->moodle_course_id,
                'nombre'                    => $c->nombre,
                'tipo_programa'             => $c->tipo_programa,
                'orden_en_carrera'          => $c->orden_en_carrera,
                'modulos_esperados'         => $c->auditoria_modulos_esperados,
                'examenes_esperados'        => $c->auditoria_examenes_esperados,
                'ultima_auditoria'          => $c->ultimaAuditoria ? [
                    'resultado_global'      => $c->ultimaAuditoria->resultado_global,
                    'semaforos'             => $c->ultimaAuditoria->semaforos,
                    'auditado_en'           => $c->ultimaAuditoria->auditado_en?->format('d/m/Y H:i'),
                    'detalle'               => $c->ultimaAuditoria->detalle,
                ] : null,
            ]);

        // Conteos globales
        $conAuditoria = $cursos->filter(fn($c) => $c['ultima_auditoria']);
        return response()->json([
            'total_cursos'  => $cursos->count(),
            'sin_auditar'   => $cursos->where('ultima_auditoria', null)->count(),
            'aprobados'     => $conAuditoria->where('ultima_auditoria.resultado_global', 'APROBADO')->count(),
            'advertencias'  => $conAuditoria->where('ultima_auditoria.resultado_global', 'ADVERTENCIA')->count(),
            'reprobados'    => $conAuditoria->where('ultima_auditoria.resultado_global', 'REPROBADO')->count(),
            'cursos'        => $cursos->values(),
        ]);
    }

    /**
     * POST /api/auditoria/curso/{curso}
     * Audita un solo curso y devuelve el resultado inmediatamente.
     */
    public function auditarCurso(Curso $curso, Request $request): JsonResponse
    {
        $resultado = $this->auditoria->auditarCurso($curso, $request->user());
        return response()->json([
            'resultado_global' => $resultado->resultado_global,
            'semaforos'        => $resultado->semaforos,
            'detalle'          => $resultado->detalle,
            'auditado_en'      => $resultado->auditado_en->format('d/m/Y H:i:s'),
        ]);
    }

    /**
     * POST /api/auditoria/todos
     * Audita todos los cursos activos. Puede tardar varios minutos.
     * Se recomienda llamar desde el dashboard con un loader visible.
     */
    public function auditarTodos(Request $request): JsonResponse
    {
        $resumen = $this->auditoria->auditarTodos($request->user());
        return response()->json(array_merge(
            $resumen,
            ['mensaje' => "Auditoría completada. {$resumen['aprobados']} aprobados, {$resumen['advertencias']} con advertencias, {$resumen['reprobados']} reprobados."]
        ));
    }

    /**
     * PUT /api/auditoria/curso/{curso}/criterios
     * Actualiza los criterios de auditoría de un curso
     * (cuántos módulos y exámenes se esperan).
     */
    public function actualizarCriterios(Request $request, Curso $curso): JsonResponse
    {
        $request->validate([
            'modulos_esperados'  => 'sometimes|integer|min:1|max:30',
            'examenes_esperados' => 'sometimes|integer|min:0|max:30',
            'titulo_esperado'    => 'sometimes|nullable|string|max:200',
        ]);

        $curso->update([
            'auditoria_modulos_esperados'  => $request->modulos_esperados  ?? $curso->auditoria_modulos_esperados,
            'auditoria_examenes_esperados' => $request->examenes_esperados ?? $curso->auditoria_examenes_esperados,
            'auditoria_titulo_esperado'    => $request->titulo_esperado    ?? $curso->auditoria_titulo_esperado,
        ]);

        return response()->json([
            'mensaje'            => 'Criterios actualizados.',
            'modulos_esperados'  => $curso->auditoria_modulos_esperados,
            'examenes_esperados' => $curso->auditoria_examenes_esperados,
            'titulo_esperado'    => $curso->auditoria_titulo_esperado,
        ]);
    }
}
