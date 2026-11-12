<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Curso;
use App\Services\InventarioCursoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class InventarioController extends Controller
{
    public function __construct(
        private InventarioCursoService $inventario,
    ) {}

    /**
     * GET /api/inventario
     * Resumen de todos los cursos activos — tabla comparativa.
     * Se cachea 30 minutos (los contenidos de Moodle no cambian frecuentemente).
     */
    public function resumen(): JsonResponse
    {
        $datos = Cache::remember('inventario_cursos', 1800, function () {
            return $this->inventario->resumenTodos();
        });

        return response()->json(['cursos' => $datos]);
    }

    /**
     * GET /api/inventario/{curso}
     * Inventario completo de un curso específico con desglose sección por sección.
     * Se cachea 15 minutos.
     */
    public function detalle(Curso $curso): JsonResponse
    {
        $datos = Cache::remember("inventario_curso_{$curso->id}", 900, function () use ($curso) {
            return $this->inventario->inventariar($curso);
        });

        return response()->json($datos);
    }

    /**
     * POST /api/inventario/{curso}/refrescar
     * Fuerza la regeneración del inventario de un curso limpiando el caché.
     */
    public function refrescar(Curso $curso): JsonResponse
    {
        Cache::forget("inventario_curso_{$curso->id}");
        Cache::forget('inventario_cursos');

        $datos = $this->inventario->inventariar($curso);
        return response()->json($datos);
    }
}
