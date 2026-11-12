<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Curso;
use App\Services\MoodleSincronizadorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * SincronizadorController
 *
 * Endpoints para sincronizar cursos desde Moodle al panel central.
 * Solo accesible por NOMADDI_ADMIN.
 *
 * Flujo de uso desde el dashboard:
 *  1. GET  /api/sincronizador/cursos          → lista cursos de Moodle
 *  2. POST /api/sincronizador/cursos/importar → importa los seleccionados
 *  3. POST /api/sincronizador/cursos/{curso}/nombre → resincroniza el nombre
 */
class SincronizadorController extends Controller
{
    public function __construct(
        private MoodleSincronizadorService $sincronizador,
    ) {}

    /**
     * GET /api/sincronizador/cursos
     *
     * Lista todos los cursos de Moodle con un flag "ya_importado"
     * para que el admin sepa cuáles faltan por registrar en el panel.
     */
    public function listarCursosMoodle(): JsonResponse
    {
        $cursos = $this->sincronizador->listarCursosMoodle();

        return response()->json([
            'total'         => $cursos->count(),
            'ya_importados' => $cursos->where('ya_importado', true)->count(),
            'pendientes'    => $cursos->where('ya_importado', false)->count(),
            'cursos'        => $cursos,
        ]);
    }

    /**
     * POST /api/sincronizador/cursos/importar
     *
     * Importa al panel los cursos seleccionados por el admin.
     * El admin define tipo_programa, orden_en_carrera y precios
     * para cada curso desde la interfaz del dashboard.
     *
     * Body:
     * {
     *   "cursos": [
     *     {
     *       "moodle_course_id": 5,
     *       "nombre": "Trabajo en alturas",
     *       "tipo_programa": "TECNICOS",
     *       "orden_en_carrera": 1,
     *       "es_ultimo": false,
     *       "fee_nomaddi": 30000,
     *       "precio_minimo_publico": 80000
     *     },
     *     ...
     *   ]
     * }
     */
    public function importar(Request $request): JsonResponse
    {
        $request->validate([
            'cursos'                              => 'required|array|min:1',
            'cursos.*.moodle_course_id'           => 'required|integer',
            'cursos.*.nombre'                     => 'required|string|max:200',
            'cursos.*.tipo_programa'              => 'required|in:TECNICOS,BACHILLERATO',
            'cursos.*.orden_en_carrera'           => 'required|integer|min:1',
            'cursos.*.es_ultimo'                  => 'boolean',
            'cursos.*.fee_nomaddi'                => 'required|numeric|min:0',
            'cursos.*.precio_minimo_publico'      => 'required|numeric|gt:cursos.*.fee_nomaddi',
        ]);

        $resultado = $this->sincronizador->importar($request->cursos);

        return response()->json([
            'mensaje'    => "{$resultado['importados']} curso(s) importado(s) correctamente.",
            'importados' => $resultado['importados'],
            'omitidos'   => $resultado['omitidos'],
            'errores'    => $resultado['errores'],
        ], $resultado['errores'] ? 207 : 200); // 207 Multi-Status si hubo errores parciales
    }

    /**
     * POST /api/sincronizador/cursos/{curso}/nombre
     *
     * Resincroniza el nombre de un curso específico desde Moodle.
     * Útil cuando un curso fue renombrado en Moodle
     * y quieres actualizar el panel sin importarlo de nuevo.
     */
    public function sincronizarNombre(Curso $curso): JsonResponse
    {
        $actualizado = $this->sincronizador->sincronizarNombre($curso);

        return response()->json([
            'actualizado' => $actualizado,
            'nombre'      => $curso->fresh()->nombre,
            'mensaje'     => $actualizado
                ? 'Nombre actualizado desde Moodle.'
                : 'El nombre ya estaba sincronizado.',
        ]);
    }
}
