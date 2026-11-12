<?php

namespace App\Services;

use App\Models\Acta;
use App\Models\Certificado;
use App\Models\Matricula;
use App\Models\LogValidacionCertificado;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * CertificadoService
 *
 * Emite certificados al completar el último curso de la carrera.
 *
 * Lógica crítica — generación del folio:
 *  El folio es un consecutivo por acta. Si dos estudiantes terminan
 *  el último curso simultáneamente, podrían recibir el mismo folio.
 *  Para evitarlo usamos SELECT ... FOR UPDATE (bloqueo pesimista)
 *  dentro de una transacción de BD. Solo un proceso a la vez puede
 *  calcular y asignar el siguiente folio de un acta.
 *
 * Lógica IETDH como distribuidor:
 *  Si la IETDH que certifica tiene distribuidor_id = el distribuidor
 *  de la matrícula, no se genera devengo de fee de IETDH porque es
 *  la misma entidad operando como distribuidor y certificadora.
 */
class CertificadoService
{
    public function __construct(
        private MoodleService $moodle,
        private IetdhService  $ietdhService,
    ) {}

    // ──────────────────────────────────────────────────────────────
    // EMITIR CERTIFICADO
    // ──────────────────────────────────────────────────────────────

    /**
     * Punto de entrada principal.
     * Se llama desde el Job DetectarUltimoCursoCompletado
     * cuando detecta que un estudiante terminó el último curso.
     *
     * @throws \Exception si falta configuración o hay error en Moodle
     */
    public function emitir(Matricula $matricula): Certificado
    {
        $estudiante   = $matricula->estudiante;
        $curso        = $matricula->curso;
        $distribuidor = $matricula->distribuidor;

        // ── 1. Verificar que es el último curso ───────────────────
        if (!$curso->es_ultimo) {
            throw new \Exception(
                "El curso '{$curso->nombre}' no está marcado como último de la carrera."
            );
        }

        // ── 2. Verificar que no tiene ya un certificado ───────────
        if (Certificado::where('matricula_id', $matricula->id)->exists()) {
            throw new \Exception(
                "Ya existe un certificado para la matrícula {$matricula->id}."
            );
        }

        // ── 3. Obtener la IETDH del distribuidor para este programa
        $asignacion = $distribuidor->distribuidorIetdhs()
            ->where('tipo_programa', $curso->tipo_programa)
            ->with('ietdh')
            ->first();

        if (!$asignacion) {
            throw new \Exception(
                "El distribuidor '{$distribuidor->nombre}' no tiene IETDH asignada " .
                "para el programa {$curso->tipo_programa}."
            );
        }

        $ietdh = $asignacion->ietdh;

        // ── 4. Generar acta + folio + UUID dentro de transacción ──
        $certificado = DB::transaction(function () use (
            $matricula, $curso, $distribuidor, $ietdh
        ) {
            // Obtener o crear el acta del mes actual para esta IETDH
            $acta = $this->obtenerOCrearActa($ietdh->id, $curso->tipo_programa);

            // Bloqueo pesimista: solo un proceso a la vez por acta
            $actaBloqueada = Acta::where('id', $acta->id)
                ->lockForUpdate()
                ->first();

            $folio             = $actaBloqueada->siguienteFolio();
            $uuidVerificacion  = Str::uuid()->toString();

            $certificado = Certificado::create([
                'matricula_id'      => $matricula->id,
                'acta_id'           => $actaBloqueada->id,
                'ietdh_id'          => $ietdh->id,
                'folio'             => $folio,
                'uuid_verificacion' => $uuidVerificacion,
                'tipo_programa'     => $curso->tipo_programa,
                'fecha_emision'     => now(),
            ]);

            Log::info('Certificado emitido', [
                'certificado_id'   => $certificado->id,
                'acta'             => $actaBloqueada->codigo_acta,
                'folio'            => $folio,
                'estudiante'       => $matricula->estudiante->email,
                'ietdh'            => $ietdh->nombre,
            ]);

            return $certificado;
        });

        // ── 5. Enviar UUID a Moodle (Custom Certificate) ──────────
        $this->enviarDatosACertificadoMoodle($certificado, $matricula, $ietdh);

        // ── 6. Registrar devengo del fee de IETDH ─────────────────
        //       (solo si la IETDH NO es el mismo distribuidor)
        $this->registrarDevengoIetdh($certificado, $ietdh, $distribuidor);

        return $certificado->fresh(['acta', 'ietdh']);
    }

    // ──────────────────────────────────────────────────────────────
    // ANULAR CERTIFICADO
    // ──────────────────────────────────────────────────────────────

    /**
     * Anula un certificado.
     * El folio NO se reutiliza — queda registrado como anulado.
     * La validación pública devolverá "ANULADO" en lugar de "VALIDO".
     */
    public function anular(Certificado $certificado, string $motivo): void
    {
        if ($certificado->anulado) {
            throw new \Exception('Este certificado ya está anulado.');
        }

        $certificado->update([
            'anulado'          => true,
            'motivo_anulacion' => $motivo,
            'fecha_anulacion'  => now(),
        ]);

        Log::info('Certificado anulado', [
            'certificado_id' => $certificado->id,
            'acta'           => $certificado->acta->codigo_acta,
            'folio'          => $certificado->folio,
            'motivo'         => $motivo,
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // VALIDACIÓN PÚBLICA
    // ──────────────────────────────────────────────────────────────

    /**
     * Valida un certificado por UUID.
     * Registra la consulta en el log de validaciones.
     * Devuelve los datos públicos del certificado o null si no existe.
     */
    public function validar(
        string $uuid,
        ?string $ip = null,
        ?string $dominio = null
    ): array {
        $certificado = Certificado::where('uuid_verificacion', $uuid)
            ->with(['acta', 'ietdh', 'matricula.estudiante', 'matricula.curso'])
            ->first();

        if (!$certificado) {
            $this->registrarValidacion($uuid, $ip, $dominio, 'INVALIDO');
            return ['valido' => false, 'mensaje' => 'Certificado no encontrado.'];
        }

        if ($certificado->anulado) {
            $this->registrarValidacion($uuid, $ip, $dominio, 'ANULADO');
            return [
                'valido'  => false,
                'anulado' => true,
                'mensaje' => 'Este certificado ha sido anulado.',
                'motivo'  => $certificado->motivo_anulacion,
            ];
        }

        $this->registrarValidacion($uuid, $ip, $dominio, 'VALIDO');

        return [
            'valido'          => true,
            'estudiante'      => $certificado->matricula->estudiante->nombre_completo,
            'programa'        => $certificado->matricula->curso->nombre,
            'tipo_programa'   => $certificado->tipo_programa,
            'institucion'     => $certificado->ietdh->nombre,
            'fecha_emision'   => $certificado->fecha_emision->format('d/m/Y'),
            'acta'            => $certificado->acta->codigo_acta,
            'folio'           => $certificado->folio,
            'referencia'      => $certificado->referencias,
        ];
    }

    // ──────────────────────────────────────────────────────────────
    // HELPERS PRIVADOS
    // ──────────────────────────────────────────────────────────────

    /**
     * Obtiene el acta del mes actual para una IETDH y tipo de programa.
     * Si no existe, la crea con el código generado automáticamente.
     */
    private function obtenerOCrearActa(string $ietdhId, string $tipoPrograma): Acta
    {
        $anio = (int) now()->format('Y');
        $mes  = (int) now()->format('n');

        return Acta::firstOrCreate(
            [
                'ietdh_id'      => $ietdhId,
                'tipo_programa' => $tipoPrograma,
                'anio'          => $anio,
                'mes'           => $mes,
            ],
            [
                'codigo_acta' => Acta::generarCodigo($tipoPrograma, $anio, $mes),
            ]
        );
    }

    /**
     * Envía los datos del certificado a Moodle vía API
     * para que el Custom Certificate los incluya en el PDF.
     *
     * Los datos viajan como campos de perfil del usuario:
     *  - cert_acta         → código del acta (ej: T2604)
     *  - cert_folio        → número de folio (ej: 15)
     *  - cert_uuid         → UUID de verificación
     *  - cert_ietdh        → nombre de la IETDH emisora
     *  - cert_fecha        → fecha de emisión formateada
     */
    private function enviarDatosACertificadoMoodle(
        Certificado $certificado,
        Matricula $matricula,
        $ietdh
    ): void {
        try {
            $this->moodle->actualizarCamposCertificado(
                moodleUserId: $matricula->estudiante->moodle_user_id,
                campos: [
                    'cert_acta'   => $certificado->acta->codigo_acta,
                    'cert_folio'  => (string) $certificado->folio,
                    'cert_uuid'   => $certificado->uuid_verificacion,
                    'cert_ietdh'  => $ietdh->nombre,
                    'cert_fecha'  => $certificado->fecha_emision->format('d/m/Y'),
                ]
            );
        } catch (\Exception $e) {
            // Log pero no abortar — el certificado ya está en la BD
            Log::error('Error enviando datos del certificado a Moodle', [
                'certificado_id' => $certificado->id,
                'error'          => $e->getMessage(),
            ]);
        }
    }

    /**
     * Registra el devengo del fee de IETDH.
     * Se omite si la IETDH y el distribuidor son la misma entidad.
     */
    private function registrarDevengoIetdh(
        Certificado $certificado,
        $ietdh,
        $distribuidor
    ): void {
        // IETDH como distribuidor: no se cobra fee de IETDH
        if ($ietdh->distribuidor_id === $distribuidor->id) {
            Log::info('Fee IETDH omitido: IETDH opera como distribuidor', [
                'ietdh_id'        => $ietdh->id,
                'distribuidor_id' => $distribuidor->id,
            ]);
            return;
        }

        // Registrar para la liquidación mensual
        // (la liquidación la genera el Módulo 6 al cerrar el mes)
        Log::info('Devengo fee IETDH registrado', [
            'certificado_id' => $certificado->id,
            'ietdh_id'       => $ietdh->id,
            'tipo_programa'  => $certificado->tipo_programa,
        ]);
        // El certificado ya tiene ietdh_id y liquidacion_id (null por ahora).
        // El Módulo 6 agrupa todos los certificados sin liquidacion_id
        // y genera la liquidación mensual correspondiente.
    }

    private function registrarValidacion(
        string $uuid,
        ?string $ip,
        ?string $dominio,
        string $resultado
    ): void {
        LogValidacionCertificado::create([
            'uuid_verificacion'  => $uuid,
            'ip_consultante'     => $ip,
            'dominio_consultante'=> $dominio,
            'resultado'          => $resultado,
            'created_at'         => now(),
        ]);
    }
}
