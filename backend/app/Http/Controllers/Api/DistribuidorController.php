<?php
// app/Http/Controllers/Api/DistribuidorController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CrearDistribuidorRequest;
use App\Models\Distribuidor;
use App\Services\DistribuidorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DistribuidorController extends Controller
{
    public function __construct(
        private DistribuidorService $service,
    ) {}

    /** GET /api/distribuidores */
    public function index(): JsonResponse
    {
        $distribuidores = Distribuidor::with(['distribuidorIetdhs.ietdh'])
            ->orderBy('nombre')
            ->get();

        return response()->json($distribuidores);
    }

    /** GET /api/distribuidores/{id} */
    public function show(Distribuidor $distribuidor): JsonResponse
    {
        return response()->json(
            $distribuidor->load(['distribuidorIetdhs.ietdh', 'grupos.curso'])
        );
    }

    /** POST /api/distribuidores */
    public function store(CrearDistribuidorRequest $request): JsonResponse
    {
        $distribuidor = $this->service->crear($request->validated());
        return response()->json($distribuidor, 201);
    }

    /** PUT /api/distribuidores/{id} */
    public function update(Request $request, Distribuidor $distribuidor): JsonResponse
    {
        $request->validate([
            'nombre'            => 'sometimes|string|max:150',
            'email_contacto'    => 'sometimes|email',
            'epayco_receiver_id'=> 'sometimes|nullable|string|max:100',
        ]);

        $actualizado = $this->service->actualizar($distribuidor, $request->all());
        return response()->json($actualizado);
    }

    /** POST /api/distribuidores/{id}/desactivar */
    public function desactivar(Distribuidor $distribuidor): JsonResponse
    {
        $this->service->desactivar($distribuidor);
        return response()->json(['message' => 'Distribuidor desactivado correctamente.']);
    }

    /** POST /api/distribuidores/{id}/reactivar */
    public function reactivar(Distribuidor $distribuidor): JsonResponse
    {
        $this->service->reactivar($distribuidor);
        return response()->json(['message' => 'Distribuidor reactivado correctamente.']);
    }

    /** POST /api/distribuidores/{id}/ietdh */
    public function asignarIetdh(Request $request, Distribuidor $distribuidor): JsonResponse
    {
        $request->validate([
            'ietdh_id'      => 'required|uuid|exists:ietdhs,id',
            'tipo_programa' => 'required|in:TECNICOS,BACHILLERATO',
        ]);

        $asignacion = $this->service->asignarIetdh(
            distribuidor: $distribuidor,
            ietdhId:      $request->ietdh_id,
            tipoPrograma: $request->tipo_programa,
        );

        return response()->json($asignacion, 201);
    }
}
