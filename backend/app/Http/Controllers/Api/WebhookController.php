<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LogWebhook;
use App\Services\EpaycoService;
use App\Services\MatriculaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * WebhookController
 *
 * Único endpoint que recibe notificaciones de pago de ePayco.
 * URL: POST /api/webhook/epayco
 *
 * Flujo:
 *  1. Recibe y registra el payload crudo (log RECIBIDO)
 *  2. Valida la firma de ePayco
 *  3. Verifica que la transacción esté aprobada
 *  4. Extrae los datos normalizados
 *  5. Delega al MatriculaService
 *  6. Actualiza el log con el resultado
 *
 * Siempre devuelve HTTP 200 a ePayco (para evitar reintentos
 * por errores de negocio que son definitivos como precio bajo).
 * Los errores transitorios (falla de red a Moodle) devuelven 500
 * para que ePayco reintente.
 */
class WebhookController extends Controller
{
    public function __construct(
        private EpaycoService   $epayco,
        private MatriculaService $matricula,
    ) {}

    public function recibir(Request $request): JsonResponse
    {
        $payload = $request->all();
        $epaycoRef = $payload['x_ref_payco'] ?? ('sin-ref-' . now()->timestamp);

        // ── 1. Log inicial: registrar que llegó algo ──────────────────
        $log = LogWebhook::create([
            'epayco_ref'   => $epaycoRef,
            'monto'        => $payload['x_amount'] ?? null,
            'email_estudiante' => $payload['x_customer_email'] ?? null,
            'moodle_course_id' => $payload['p_attr_4'] ?? null,
            'distribuidor_id'  => $payload['p_attr_5'] ?? null,
            'estado'       => 'RECIBIDO',
            'payload_raw'  => $payload,
            'created_at'   => now(),
        ]);

        try {
            // ── 2. Validar firma ──────────────────────────────────────
            if (!$this->epayco->validarFirma($payload)) {
                $log->update(['estado' => 'RECHAZADO', 'motivo_rechazo' => 'Firma inválida']);
                return response()->json(['status' => 'firma_invalida'], 200);
            }

            $log->update(['estado' => 'VALIDADO']);

            // ── 3. Verificar que el pago esté aprobado ────────────────
            if (!$this->epayco->estaAprobada($payload)) {
                $codResp = $payload['x_cod_response'] ?? 'desconocido';
                $log->update([
                    'estado'         => 'RECHAZADO',
                    'motivo_rechazo' => "Pago no aprobado (cod_response: {$codResp})",
                ]);
                return response()->json(['status' => 'pago_no_aprobado'], 200);
            }

            // ── 4. Extraer datos normalizados ─────────────────────────
            $datos = $this->epayco->extraerDatos($payload);

            // ── 5. Ejecutar matrícula ─────────────────────────────────
            $matricula = $this->matricula->procesar($datos);

            // ── 6. Log de éxito ───────────────────────────────────────
            $log->update(['estado' => 'MATRICULADO']);

            return response()->json([
                'status'       => 'ok',
                'matricula_id' => $matricula->id,
            ], 200);

        } catch (\Exception $e) {
            $mensaje = $e->getMessage();

            Log::error("Webhook error [{$epaycoRef}]: {$mensaje}");

            // Errores de negocio definitivos (precio bajo, ya matriculado):
            // devolver 200 para que ePayco NO reintente.
            $esDefinitivo = str_starts_with($mensaje, 'PRECIO_BAJO')
                || str_contains($mensaje, 'ya está matriculado')
                || str_contains($mensaje, 'Distribuidor no encontrado')
                || str_contains($mensaje, 'Curso no encontrado');

            $log->update([
                'estado'         => $esDefinitivo ? 'RECHAZADO' : 'ERROR',
                'motivo_rechazo' => $mensaje,
            ]);

            // Error transitorio (Moodle caído, etc.): 500 para forzar reintento
            if (!$esDefinitivo) {
                return response()->json(['status' => 'error', 'message' => $mensaje], 500);
            }

            return response()->json(['status' => 'rechazado', 'motivo' => $mensaje], 200);
        }
    }
}
