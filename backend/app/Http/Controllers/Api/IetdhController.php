<?php
// app/Http/Controllers/Api/IetdhController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CrearIetdhRequest;
use App\Models\Ietdh;
use App\Services\IetdhService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IetdhController extends Controller
{
    public function __construct(
        private IetdhService $service,
    ) {}

    /** GET /api/ietdhs */
    public function index(): JsonResponse
    {
        $ietdhs = Ietdh::with('fees')
            ->orderBy('departamento')
            ->orderBy('nombre')
            ->get();

        return response()->json($ietdhs);
    }

    /** GET /api/ietdhs/{id} */
    public function show(Ietdh $ietdh): JsonResponse
    {
        return response()->json($ietdh->load(['fees', 'distribuidores.distribuidor']));
    }

    /** POST /api/ietdhs */
    public function store(CrearIetdhRequest $request): JsonResponse
    {
        $ietdh = $this->service->crear($request->validated());
        return response()->json($ietdh, 201);
    }

    /** POST /api/ietdhs/{id}/fee */
    public function actualizarFee(Request $request, Ietdh $ietdh): JsonResponse
    {
        $request->validate([
            'tipo_programa'       => 'required|in:TECNICOS,BACHILLERATO',
            'fee_por_certificado' => 'required|numeric|min:0',
            'vigente_desde'       => 'nullable|date|after_or_equal:today',
        ]);

        $fee = $this->service->actualizarFee(
            ietdh:             $ietdh,
            tipoPrograma:      $request->tipo_programa,
            feePorCertificado: $request->fee_por_certificado,
            vigenteDesde:      $request->vigente_desde,
        );

        return response()->json($fee, 201);
    }

    /** POST /api/ietdhs/{id}/desactivar */
    public function desactivar(Ietdh $ietdh): JsonResponse
    {
        $this->service->desactivar($ietdh);
        return response()->json(['message' => 'IETDH desactivada.']);
    }

    /** POST /api/ietdhs/{id}/reactivar */
    public function reactivar(Ietdh $ietdh): JsonResponse
    {
        $this->service->reactivar($ietdh);
        return response()->json(['message' => 'IETDH reactivada.']);
    }
}
