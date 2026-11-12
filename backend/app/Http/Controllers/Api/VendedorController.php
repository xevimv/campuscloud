<?php
// app/Http/Controllers/Api/VendedorController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Distribuidor;
use App\Models\Vendedor;
use App\Services\AuthService;
use App\Services\VendedorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class VendedorController extends Controller
{
    public function __construct(
        private AuthService     $auth,
        private VendedorService $vendedorService,
    ) {}

    /**
     * GET /api/distribuidores/{distribuidor}/vendedores
     * Lista vendedores del distribuidor con resumen del mes actual.
     */
    public function index(Distribuidor $distribuidor): JsonResponse
    {
        $desde = now()->startOfMonth()->toDateTimeString();
        $hasta = now()->toDateTimeString();

        $resumen = $this->vendedorService->resumenPorDistribuidor(
            $distribuidor, $desde, $hasta
        );

        return response()->json([
            'distribuidor' => $distribuidor->nombre,
            'periodo'      => now()->format('F Y'),
            'vendedores'   => $resumen,
        ]);
    }

    /**
     * GET /api/distribuidores/{distribuidor}/vendedores/{vendedor}
     * Detalle de un vendedor con historial de matrículas.
     */
    public function show(Distribuidor $distribuidor, Vendedor $vendedor): JsonResponse
    {
        abort_if($vendedor->distribuidor_id !== $distribuidor->id, 404);

        return response()->json(
            $vendedor->load(['matriculas.curso', 'matriculas.estudiante'])
        );
    }

    /**
     * POST /api/distribuidores/{distribuidor}/vendedores
     * Crea un vendedor y su usuario del sistema.
     */
    public function store(Request $request, Distribuidor $distribuidor): JsonResponse
    {
        $request->validate([
            'nombre'         => 'required|string|max:150',
            'email'          => 'required|email|unique:usuarios,email|unique:vendedores,email',
            'password'       => ['required', Password::min(8)->mixedCase()->numbers()],
            'comision_tipo'  => 'required|in:PORCENTAJE,FIJO',
            'comision_valor' => 'required|numeric|min:0',
        ]);

        $resultado = $this->auth->crearVendedor($request->validated(), $distribuidor);

        return response()->json([
            'vendedor'        => $resultado['vendedor'],
            'codigo_referido' => $resultado['vendedor']->codigo_referido,
            'enlace_ejemplo'  => "https://{$distribuidor->dominio}/?ref={$resultado['vendedor']->codigo_referido}",
        ], 201);
    }

    /**
     * PUT /api/distribuidores/{distribuidor}/vendedores/{vendedor}/comision
     * Actualiza la comisión de un vendedor.
     */
    public function actualizarComision(
        Request $request,
        Distribuidor $distribuidor,
        Vendedor $vendedor
    ): JsonResponse {
        abort_if($vendedor->distribuidor_id !== $distribuidor->id, 404);

        $request->validate([
            'comision_tipo'  => 'required|in:PORCENTAJE,FIJO',
            'comision_valor' => 'required|numeric|min:0',
        ]);

        $actualizado = $this->vendedorService->actualizarComision(
            $vendedor,
            $request->comision_tipo,
            $request->comision_valor,
        );

        return response()->json($actualizado);
    }

    /**
     * POST /api/distribuidores/{distribuidor}/vendedores/{vendedor}/desactivar
     */
    public function desactivar(Distribuidor $distribuidor, Vendedor $vendedor): JsonResponse
    {
        abort_if($vendedor->distribuidor_id !== $distribuidor->id, 404);
        $this->vendedorService->desactivar($vendedor);
        return response()->json(['message' => 'Vendedor desactivado.']);
    }

    /**
     * POST /api/distribuidores/{distribuidor}/vendedores/{vendedor}/reactivar
     */
    public function reactivar(Distribuidor $distribuidor, Vendedor $vendedor): JsonResponse
    {
        abort_if($vendedor->distribuidor_id !== $distribuidor->id, 404);
        $this->vendedorService->reactivar($vendedor);
        return response()->json(['message' => 'Vendedor reactivado.']);
    }

    /**
     * GET /api/distribuidores/{distribuidor}/vendedores/reporte
     * Reporte de comisiones en un rango de fechas.
     */
    public function reporte(Request $request, Distribuidor $distribuidor): JsonResponse
    {
        $request->validate([
            'desde' => 'required|date',
            'hasta' => 'required|date|after_or_equal:desde',
        ]);

        $resumen = $this->vendedorService->resumenPorDistribuidor(
            $distribuidor,
            $request->desde,
            $request->hasta,
        );

        return response()->json([
            'distribuidor' => $distribuidor->nombre,
            'desde'        => $request->desde,
            'hasta'        => $request->hasta,
            'vendedores'   => $resumen,
        ]);
    }
}
