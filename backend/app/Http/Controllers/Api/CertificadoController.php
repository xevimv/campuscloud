<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Certificado;
use App\Models\Matricula;
use App\Services\CertificadoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CertificadoController extends Controller
{
    public function __construct(
        private CertificadoService $certificados,
    ) {}

    // ──────────────────────────────────────────────────────────────
    // EMISIÓN MANUAL
    // ──────────────────────────────────────────────────────────────

    /**
     * POST /api/certificados/emitir/{matricula}
     * Emisión manual de un certificado (solo NOMADDI_ADMIN).
     * Normalmente ocurre automáticamente vía Job.
     */
    public function emitir(Matricula $matricula): JsonResponse
    {
        $certificado = $this->certificados->emitir($matricula);

        return response()->json([
            'certificado_id'  => $certificado->id,
            'acta'            => $certificado->acta->codigo_acta,
            'folio'           => $certificado->folio,
            'referencia'      => $certificado->referencias,
            'uuid'            => $certificado->uuid_verificacion,
            'url_verificacion'=> $certificado->url_validacion,
            'fecha_emision'   => $certificado->fecha_emision->format('d/m/Y'),
            'ietdh'           => $certificado->ietdh->nombre,
        ], 201);
    }

    // ──────────────────────────────────────────────────────────────
    // ANULACIÓN
    // ──────────────────────────────────────────────────────────────

    /**
     * POST /api/certificados/{certificado}/anular
     * Solo NOMADDI_ADMIN puede anular certificados.
     */
    public function anular(Request $request, Certificado $certificado): JsonResponse
    {
        $request->validate([
            'motivo' => 'required|string|max:500',
        ]);

        $this->certificados->anular($certificado, $request->motivo);

        return response()->json([
            'message' => 'Certificado anulado correctamente.',
            'acta'    => $certificado->acta->codigo_acta,
            'folio'   => $certificado->folio,
            'motivo'  => $request->motivo,
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // LISTADO
    // ──────────────────────────────────────────────────────────────

    /**
     * GET /api/certificados?ietdh_id=X&desde=Y&hasta=Z
     * Lista certificados con filtros. Solo NOMADDI_ADMIN.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'ietdh_id' => 'nullable|uuid|exists:ietdhs,id',
            'desde'    => 'nullable|date',
            'hasta'    => 'nullable|date|after_or_equal:desde',
            'anulados' => 'nullable|boolean',
        ]);

        $query = Certificado::with(['acta', 'ietdh', 'matricula.estudiante', 'matricula.curso'])
            ->orderByDesc('fecha_emision');

        if ($request->ietdh_id) {
            $query->where('ietdh_id', $request->ietdh_id);
        }
        if ($request->desde) {
            $query->where('fecha_emision', '>=', $request->desde);
        }
        if ($request->hasta) {
            $query->where('fecha_emision', '<=', $request->hasta . ' 23:59:59');
        }
        if ($request->has('anulados')) {
            $query->where('anulado', $request->boolean('anulados'));
        }

        return response()->json($query->paginate(50));
    }

    /**
     * GET /api/certificados/{certificado}
     * Detalle completo de un certificado.
     */
    public function show(Certificado $certificado): JsonResponse
    {
        return response()->json(
            $certificado->load(['acta', 'ietdh', 'matricula.estudiante', 'matricula.curso'])
        );
    }

    // ──────────────────────────────────────────────────────────────
    // VALIDACIÓN PÚBLICA — sin autenticación
    // ──────────────────────────────────────────────────────────────

    /**
     * GET /api/verificar/{uuid}
     * Endpoint público de validación de certificados.
     * Accesible desde cualquier dominio sin autenticación.
     * También se expone en: GET /verificar/{uuid} (ruta web)
     */
    public function verificar(Request $request, string $uuid): JsonResponse
    {
        $resultado = $this->certificados->validar(
            uuid:    $uuid,
            ip:      $request->ip(),
            dominio: $request->header('Origin') ?? $request->header('Referer'),
        );

        return response()->json($resultado)
            ->header('Access-Control-Allow-Origin', '*'); // CORS abierto para validación
    }
}
