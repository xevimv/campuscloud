<?php

namespace App\Services;

use App\Mail\CertificadoEmitido;
use App\Models\Certificado;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * NotificacionCertificadoService
 *
 * Orquesta todo lo que ocurre DESPUÉS de que CertificadoService
 * crea el registro del certificado en la BD:
 *
 *  1. Genera el PDF (PdfCertificadoService)
 *  2. Envía el correo al estudiante con el PDF adjunto (encolado)
 *  3. Actualiza los campos del certificado en Moodle (opcional)
 *
 * Se llama desde CertificadoService::emitir() justo después de
 * que la transacción de BD se confirma exitosamente.
 */
class NotificacionCertificadoService
{
    public function __construct(
        private PdfCertificadoService $pdf,
        private MoodleService         $moodle,
    ) {}

    /**
     * Ejecuta el flujo completo post-emisión.
     * Los errores en PDF o correo se loguean pero no abortan
     * (el certificado ya está legalmente registrado en la BD).
     */
    public function notificar(Certificado $certificado): void
    {
        $certificado->loadMissing([
            'acta',
            'ietdh',
            'matricula.estudiante',
            'matricula.curso',
        ]);

        // ── 1. Generar PDF ────────────────────────────────────────
        $rutaPdf = null;
        try {
            $rutaPdf = $this->pdf->generar($certificado);
            Log::info('PDF generado', [
                'certificado_id' => $certificado->id,
                'ruta'           => $rutaPdf,
            ]);
        } catch (\Exception $e) {
            Log::error('Error generando PDF del certificado', [
                'certificado_id' => $certificado->id,
                'error'          => $e->getMessage(),
            ]);
        }

        // ── 2. Enviar correo al estudiante ────────────────────────
        if ($rutaPdf) {
            try {
                $emailEstudiante = $certificado->matricula->estudiante->email;

                Mail::to($emailEstudiante)
                    ->queue(new CertificadoEmitido($certificado, $rutaPdf));

                Log::info('Correo certificado encolado', [
                    'certificado_id' => $certificado->id,
                    'email'          => $emailEstudiante,
                ]);
            } catch (\Exception $e) {
                Log::error('Error encolando correo del certificado', [
                    'certificado_id' => $certificado->id,
                    'error'          => $e->getMessage(),
                ]);
            }
        }

        // ── 3. Actualizar campos en Moodle ────────────────────────
        // (Opcional: por si quieres que Moodle también muestre los datos)
        $moodleUserId = $certificado->matricula->estudiante->moodle_user_id;
        if ($moodleUserId) {
            try {
                $this->moodle->actualizarCamposCertificado(
                    moodleUserId: $moodleUserId,
                    campos: [
                        'cert_acta'  => $certificado->acta->codigo_acta,
                        'cert_folio' => (string) $certificado->folio,
                        'cert_uuid'  => $certificado->uuid_verificacion,
                        'cert_ietdh' => $certificado->ietdh->nombre,
                        'cert_fecha' => $certificado->fecha_emision->format('d/m/Y'),
                    ]
                );
            } catch (\Exception $e) {
                Log::error('Error actualizando campos Moodle del certificado', [
                    'certificado_id' => $certificado->id,
                    'error'          => $e->getMessage(),
                ]);
            }
        }
    }
}
