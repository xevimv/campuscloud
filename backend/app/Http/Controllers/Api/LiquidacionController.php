<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ietdh;
use App\Models\Liquidacion;
use App\Services\LiquidacionService;
use App\Services\PdfLiquidacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * LiquidacionController
 *
 * Endpoints para gestionar liquidaciones mensuales de IETDHs.
 * Todos requieren rol NOMADDI_ADMIN.
 */
class LiquidacionController extends Controller
{
    public function __construct(
        private LiquidacionService    $service,
        private PdfLiquidacionService $pdfService,
    ) {}

    // ── Listado ───────────────────────────────────────────────────

    /** GET /api/liquidaciones */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'ietdh_id' => 'nullable|uuid|exists:ietdhs,id',
            'estado'   => 'nullable|in:GENERADA,ENVIADA,FACTURADA,PAGADA',
            'anio'     => 'nullable|integer|min:2024',
            'mes'      => 'nullable|integer|between:1,12',
        ]);

        $query = Liquidacion::with('ietdh')
            ->orderByDesc('periodo_anio')
            ->orderByDesc('periodo_mes');

        if ($request->ietdh_id) $query->where('ietdh_id', $request->ietdh_id);
        if ($request->estado)   $query->where('estado', $request->estado);
        if ($request->anio)     $query->where('periodo_anio', $request->anio);
        if ($request->mes)      $query->where('periodo_mes', $request->mes);

        return response()->json($query->paginate(30));
    }

    /** GET /api/liquidaciones/pendientes */
    public function pendientes(): JsonResponse
    {
        return response()->json($this->service->pendientesDePago());
    }

    /** GET /api/liquidaciones/{liquidacion} */
    public function show(Liquidacion $liquidacion): JsonResponse
    {
        return response()->json($this->service->detalle($liquidacion));
    }

    // ── Generación manual ─────────────────────────────────────────

    /**
     * POST /api/liquidaciones/generar
     * Genera manualmente la liquidación de una IETDH para un período.
     * Útil para casos extraordinarios o correcciones.
     */
    public function generar(Request $request): JsonResponse
    {
        $request->validate([
            'ietdh_id' => 'required|uuid|exists:ietdhs,id',
            'anio'     => 'required|integer|min:2024',
            'mes'      => 'required|integer|between:1,12',
        ]);

        $ietdh       = Ietdh::findOrFail($request->ietdh_id);
        $liquidacion = $this->service->liquidarIetdh($ietdh, $request->anio, $request->mes);

        if (!$liquidacion) {
            return response()->json([
                'message' => 'No hay certificados para liquidar en ese período.',
            ], 200);
        }

        return response()->json($liquidacion->load('ietdh'), 201);
    }

    // ── Cambios de estado ─────────────────────────────────────────

    /** POST /api/liquidaciones/{liquidacion}/facturada */
    public function marcarFacturada(Liquidacion $liquidacion): JsonResponse
    {
        $this->service->marcarFacturada($liquidacion);
        return response()->json(['message' => 'Liquidación marcada como FACTURADA.']);
    }

    /** POST /api/liquidaciones/{liquidacion}/pagada */
    public function marcarPagada(Liquidacion $liquidacion): JsonResponse
    {
        $this->service->marcarPagada($liquidacion);
        return response()->json([
            'message'    => 'Liquidación marcada como PAGADA.',
            'fecha_pago' => $liquidacion->fresh()->fecha_pago->format('d/m/Y H:i'),
        ]);
    }

    // ── PDF ───────────────────────────────────────────────────────

    /** GET /api/liquidaciones/{liquidacion}/pdf */
    public function descargarPdf(Liquidacion $liquidacion): \Symfony\Component\HttpFoundation\Response
    {
        $contenido = $this->pdfService->contenido($liquidacion);

        if (!$contenido) {
            $detalle   = $this->service->detalle($liquidacion);
            $ruta      = $this->pdfService->generar($liquidacion, $detalle);
            $contenido = \Illuminate\Support\Facades\Storage::disk('public')->get($ruta);
        }

        $mes      = str_pad($liquidacion->periodo_mes, 2, '0', STR_PAD_LEFT);
        $filename = "Liquidacion_{$mes}_{$liquidacion->periodo_anio}_{$liquidacion->ietdh->nit}.pdf";

        return response($contenido, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
