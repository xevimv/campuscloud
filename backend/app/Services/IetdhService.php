<?php

namespace App\Services;

use App\Models\Ietdh;
use App\Models\IetdhFee;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * IetdhService
 *
 * Gestiona el ciclo de vida de las IETDHs aliadas y sus fees.
 */
class IetdhService
{
    // ──────────────────────────────────────────────────────────────
    // CREAR IETDH
    // ──────────────────────────────────────────────────────────────

    /**
     * @param array $datos {
     *   nombre, nit, departamento, municipio, email_contacto, telefono,
     *   fees: [{ tipo_programa, fee_por_certificado }]
     * }
     */
    public function crear(array $datos): Ietdh
    {
        return DB::transaction(function () use ($datos) {

            $ietdh = Ietdh::create([
                'nombre'         => $datos['nombre'],
                'nit'            => $datos['nit'],
                'departamento'   => $datos['departamento'],
                'municipio'      => $datos['municipio'],
                'email_contacto' => $datos['email_contacto'],
                'telefono'       => $datos['telefono'] ?? null,
                'activa'         => true,
            ]);

            // Registrar fees iniciales por tipo de programa
            if (!empty($datos['fees'])) {
                foreach ($datos['fees'] as $fee) {
                    $this->actualizarFee(
                        ietdh:           $ietdh,
                        tipoPrograma:    $fee['tipo_programa'],
                        feePorCertificado: $fee['fee_por_certificado'],
                    );
                }
            }

            Log::info('IETDH creada', ['ietdh_id' => $ietdh->id, 'nombre' => $ietdh->nombre]);

            return $ietdh->fresh(['fees']);
        });
    }

    // ──────────────────────────────────────────────────────────────
    // ACTUALIZAR FEE
    // ──────────────────────────────────────────────────────────────

    /**
     * Registra un nuevo fee para una IETDH y tipo de programa.
     * El historial anterior queda intacto.
     */
    public function actualizarFee(
        Ietdh $ietdh,
        string $tipoPrograma,
        float $feePorCertificado,
        ?string $vigenteDesde = null
    ): IetdhFee {
        $fee = IetdhFee::create([
            'ietdh_id'           => $ietdh->id,
            'tipo_programa'      => $tipoPrograma,
            'fee_por_certificado'=> $feePorCertificado,
            'vigente_desde'      => $vigenteDesde ?? now()->toDateString(),
        ]);

        Log::info('Fee IETDH actualizado', [
            'ietdh_id'      => $ietdh->id,
            'tipo_programa' => $tipoPrograma,
            'fee'           => $feePorCertificado,
        ]);

        return $fee;
    }

    // ──────────────────────────────────────────────────────────────
    // DESACTIVAR / REACTIVAR
    // ──────────────────────────────────────────────────────────────

    public function desactivar(Ietdh $ietdh): void
    {
        $ietdh->update(['activa' => false]);
        Log::info('IETDH desactivada', ['ietdh_id' => $ietdh->id]);
    }

    public function reactivar(Ietdh $ietdh): void
    {
        $ietdh->update(['activa' => true]);
        Log::info('IETDH reactivada', ['ietdh_id' => $ietdh->id]);
    }

    // ──────────────────────────────────────────────────────────────
    // CONSULTA
    // ──────────────────────────────────────────────────────────────

    /**
     * Devuelve el fee vigente de una IETDH para un tipo de programa.
     * Usado al generar liquidaciones.
     */
    public function getFeeVigente(Ietdh $ietdh, string $tipoPrograma): ?float
    {
        return IetdhFee::where('ietdh_id', $ietdh->id)
            ->where('tipo_programa', $tipoPrograma)
            ->where('vigente_desde', '<=', now()->toDateString())
            ->orderByDesc('vigente_desde')
            ->value('fee_por_certificado');
    }
}
