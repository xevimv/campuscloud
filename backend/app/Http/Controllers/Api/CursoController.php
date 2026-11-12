<?php
// app/Http/Controllers/Api/CursoController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CrearCursoRequest;
use App\Models\Curso;
use App\Services\CursoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CursoController extends Controller
{
    public function __construct(
        private CursoService $service,
    ) {}

    /** GET /api/cursos */
    public function index(): JsonResponse
    {
        $cursos = Curso::with('precioVigente')
            ->orderBy('tipo_programa')
            ->orderBy('orden_en_carrera')
            ->get()
            ->map(fn($c) => [
                'id'                    => $c->id,
                'moodle_course_id'      => $c->moodle_course_id,
                'nombre'                => $c->nombre,
                'tipo_programa'         => $c->tipo_programa,
                'orden_en_carrera'      => $c->orden_en_carrera,
                'es_ultimo'             => $c->es_ultimo,
                'activo'                => $c->activo,
                'fee_nomaddi'           => $c->fee_nomaddi,
                'precio_minimo_publico' => $c->precio_minimo,
            ]);

        return response()->json($cursos);
    }

    /** POST /api/cursos */
    public function store(CrearCursoRequest $request): JsonResponse
    {
        $curso = $this->service->crear($request->validated());
        return response()->json($curso, 201);
    }

    /** PUT /api/cursos/{id}/precio */
    public function actualizarPrecio(Request $request, Curso $curso): JsonResponse
    {
        $request->validate([
            'fee_nomaddi'           => 'required|numeric|min:0',
            'precio_minimo_publico' => 'required|numeric|gt:fee_nomaddi',
            'vigente_desde'         => 'nullable|date|after_or_equal:today',
        ]);

        $precio = $this->service->actualizarPrecio(
            curso:               $curso,
            feeNomaddi:          $request->fee_nomaddi,
            precioMinimoPublico: $request->precio_minimo_publico,
            vigenteDesde:        $request->vigente_desde,
        );

        return response()->json($precio, 201);
    }

    /** POST /api/cursos/{id}/ultimo */
    public function marcarComoUltimo(Curso $curso): JsonResponse
    {
        $this->service->marcarComoUltimo($curso);
        return response()->json(['message' => "Curso '{$curso->nombre}' marcado como último de la carrera."]);
    }

    /** POST /api/cursos/{id}/desactivar */
    public function desactivar(Curso $curso): JsonResponse
    {
        $this->service->desactivar($curso);
        return response()->json(['message' => 'Curso desactivado.']);
    }
}
