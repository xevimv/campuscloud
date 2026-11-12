<?php

namespace App\Services;

use App\Models\Certificado;
use App\Models\Ietdh;
use App\Models\Liquidacion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * LiquidacionService
 *
 * Gestiona el ciclo completo de liquidación mensual de fees a IETDHs.
 *
 * Flujo mensual (dispara el Job CerrarMesLiquidaciones el día 1 de cada mes):
 *  1. Para cada IETDH activa:
 *     a. Reúne todos los certificados del mes anterior sin liquidacion_id
 *     b. Los agrupa por tipo de programa (TECNICOS / BACHILLERATO)
 *     c. Consulta el fee vigente de esa IETDH para cada tipo
 *     d. Calcula el total: (qty_tec × fee_tec) + (qty_bach × fee_bach)
 *     e. Crea el registro de Liquidacion en BD
 *     f. Vincula los certificados a esa liquidación (liquidacion_id)
 *  2. Envía el correo con el documento de liquidación a la IETDH
 *
 * Estados de la liquidación:
 *  GENERADA  → recién creada por el sistema
 *  ENVIADA   → correo enviado a la IETDH
 *  FACTURADA → la IETDH emitió su factura (lo marca Nomaddi manualmente)
 *  PAGADA    → Nomaddi registró el pago
 */
class LiquidacionService
{
    public function __construct(
        private IetdhService $ietdhService,
    ) {}

    // ──────────────────────────────────────────────────────────────
    // CIERRE MENSUAL
    // ──────────────────────────────────────────────────────────────

    /**
     * Genera las liquidaciones de TODAS las IETDHs activas
     * para un período dado (año + mes).
     *
     * Se llama desde el Job CerrarMesLiquidaciones el día 1 de cada mes
     * para cerrar el mes anterior.
     *
     * @param int $anio  Año del período a liquidar
     * @param int $mes   Mes del período a liquidar (1-12)
     * @return array     Resumen: cuántas IETDHs liquidadas, errores
     */
    public function cerrarMes(int $anio, int $mes): array
    {
        $ietdhs     = Ietdh::where('activa', true)->get();
        $liquidadas = 0;
        $errores    = 0;
        $sinMovimiento = 0;

        foreach ($ietdhs as $ietdh) {
            try {
                $resultado = $this->liquidarIetdh($ietdh, $anio, $mes);

                if ($resultado === null) {
                    $sinMovimiento++;
                } else {
                    $liquidadas++;
                }
            } catch (\Exception $e) {
                $errores++;
                Log::error("Error liquidando IETDH {$ietdh->id}", [
                    'ietdh'  => $ietdh->nombre,
                    'error'  => $e->getMessage(),
                    'periodo'=> "{$mes}/{$anio}",
                ]);
            }
        }

        Log::info("Cierre mensual {$mes}/{$anio} completado", [
            'liquidadas'     => $liquidadas,
            'sin_movimiento' => $sinMovimiento,
            'errores'        => $errores,
        ]);

        return compact('liquidadas', 'sinMovimiento', 'errores');
    }

    /**
     * Genera la liquidación de UNA IETDH para un período.
     * Devuelve null si no hay certificados para ese período.
     * Lanza excepción si ya existe una liquidación para ese período.
     */
    public function liquidarIetdh(Ietdh $ietdh, int $anio, int $mes): ?Liquidacion
    {
        // Verificar que no exista ya una liquidación para este período
        $existente = Liquidacion::where('ietdh_id', $ietdh->id)
            ->where('periodo_anio', $anio)
            ->where('periodo_mes', $mes)
            ->first();

        if ($existente) {
            throw new \Exception(
                "Ya existe una liquidación para {$ietdh->nombre} en {$mes}/{$anio}. " .
                "ID: {$existente->id}"
            );
        }

        // Certificados del período sin liquidación asignada
        // (IETDH como distribuidora: sus certificados ya existen
        //  pero con devengo omitido — se incluyen con fee = 0)
        $certificados = $this->certificadosDelPeriodo($ietdh->id, $anio, $mes);

        // Si no hay certificados, no generar liquidación
        if ($certificados->isEmpty()) {
            Log::info("Sin certificados para liquidar", [
                'ietdh'  => $ietdh->nombre,
                'periodo'=> "{$mes}/{$anio}",
            ]);
            return null;
        }

        // Agrupar por tipo de programa
        $tecnicos     = $certificados->where('tipo_programa', 'TECNICOS');
        $bachillerato = $certificados->where('tipo_programa', 'BACHILLERATO');

        // Consultar fees vigentes para este período
        // Usamos la fecha del último día del período para respetar historial
        $fechaPeriodo = now()->setYear($anio)->setMonth($mes)->endOfMonth()->toDateString();

        $feeTecnicos    = $this->feeVigenteEnFecha($ietdh, 'TECNICOS', $fechaPeriodo);
        $feeBachillerato= $this->feeVigenteEnFecha($ietdh, 'BACHILLERATO', $fechaPeriodo);

        // Calcular totales
        $totalTecnicos     = $tecnicos->count();
        $totalBachillerato = $bachillerato->count();
        $valorTecnicos     = $totalTecnicos * ($feeTecnicos ?? 0);
        $valorBachillerato = $totalBachillerato * ($feeBachillerato ?? 0);
        $valorTotal        = $valorTecnicos + $valorBachillerato;

        // Crear la liquidación y vincular los certificados
        return DB::transaction(function () use (
            $ietdh, $anio, $mes,
            $totalTecnicos, $totalBachillerato,
            $feeTecnicos, $feeBachillerato,
            $valorTecnicos, $valorBachillerato,
            $valorTotal, $certificados
        ) {
            $liquidacion = Liquidacion::create([
                'ietdh_id'           => $ietdh->id,
                'periodo_anio'       => $anio,
                'periodo_mes'        => $mes,
                'total_tecnicos'     => $totalTecnicos,
                'total_bachillerato' => $totalBachillerato,
                'fee_tecnicos'       => $feeTecnicos ?? 0,
                'fee_bachillerato'   => $feeBachillerato ?? 0,
                'valor_total'        => $valorTotal,
                'estado'             => 'GENERADA',
                'fecha_generacion'   => now(),
            ]);

            // Vincular cada certificado a esta liquidación
            Certificado::whereIn('id', $certificados->pluck('id'))
                ->update(['liquidacion_id' => $liquidacion->id]);

            Log::info('Liquidación generada', [
                'liquidacion_id'     => $liquidacion->id,
                'ietdh'              => $ietdh->nombre,
                'periodo'            => "{$mes}/{$anio}",
                'total_tecnicos'     => $totalTecnicos,
                'total_bachillerato' => $totalBachillerato,
                'valor_total'        => $valorTotal,
            ]);

            return $liquidacion;
        });
    }

    // ──────────────────────────────────────────────────────────────
    // GESTIÓN DE ESTADOS
    // ──────────────────────────────────────────────────────────────

    /**
     * Marca la liquidación como ENVIADA (correo enviado a la IETDH).
     * Llamado automáticamente por el Job después de enviar el correo.
     */
    public function marcarEnviada(Liquidacion $liquidacion): void
    {
        $liquidacion->update(['estado' => 'ENVIADA']);
    }

    /**
     * Nomaddi registra manualmente que recibió la factura de la IETDH.
     */
    public function marcarFacturada(Liquidacion $liquidacion): void
    {
        if ($liquidacion->estado === 'GENERADA') {
            throw new \Exception('La liquidación debe estar ENVIADA antes de marcarla como FACTURADA.');
        }
        $liquidacion->update(['estado' => 'FACTURADA']);
        Log::info('Liquidación marcada como FACTURADA', ['id' => $liquidacion->id]);
    }

    /**
     * Nomaddi registra el pago efectivo a la IETDH.
     */
    public function marcarPagada(Liquidacion $liquidacion): void
    {
        if (!in_array($liquidacion->estado, ['FACTURADA', 'ENVIADA'])) {
            throw new \Exception('La liquidación debe estar FACTURADA o ENVIADA para poder pagarla.');
        }
        $liquidacion->update([
            'estado'      => 'PAGADA',
            'fecha_pago'  => now(),
        ]);
        Log::info('Liquidación marcada como PAGADA', [
            'id'          => $liquidacion->id,
            'valor_total' => $liquidacion->valor_total,
            'ietdh'       => $liquidacion->ietdh->nombre,
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // CONSULTAS Y REPORTES
    // ──────────────────────────────────────────────────────────────

    /**
     * Resumen de liquidaciones pendientes de pago para el dashboard.
     */
    public function pendientesDePago(): Collection
    {
        return Liquidacion::whereIn('estado', ['GENERADA', 'ENVIADA', 'FACTURADA'])
            ->with('ietdh')
            ->orderBy('periodo_anio')
            ->orderBy('periodo_mes')
            ->get()
            ->map(fn($l) => [
                'id'          => $l->id,
                'ietdh'       => $l->ietdh->nombre,
                'periodo'     => $l->periodo,
                'valor_total' => $l->valor_total,
                'estado'      => $l->estado,
                'dias_espera' => $l->fecha_generacion->diffInDays(now()),
            ]);
    }

    /**
     * Detalle de una liquidación con sus certificados.
     */
    public function detalle(Liquidacion $liquidacion): array
    {
        $liquidacion->loadMissing([
            'ietdh',
            'certificados.matricula.estudiante',
            'certificados.matricula.curso',
            'certificados.acta',
        ]);

        $certificados = $liquidacion->certificados->map(fn($c) => [
            'acta'          => $c->acta->codigo_acta,
            'folio'         => $c->folio,
            'estudiante'    => $c->matricula->estudiante->nombre_completo,
            'documento'     => $c->matricula->estudiante->numero_documento,
            'programa'      => $c->matricula->curso->nombre,
            'tipo_programa' => $c->tipo_programa,
            'fecha_emision' => $c->fecha_emision->format('d/m/Y'),
            'fee_aplicado'  => $c->tipo_programa === 'TECNICOS'
                ? $liquidacion->fee_tecnicos
                : $liquidacion->fee_bachillerato,
        ]);

        return [
            'liquidacion'        => $liquidacion,
            'ietdh'              => $liquidacion->ietdh,
            'certificados'       => $certificados,
            'resumen'            => [
                'total_tecnicos'     => $liquidacion->total_tecnicos,
                'fee_tecnicos'       => $liquidacion->fee_tecnicos,
                'subtotal_tecnicos'  => $liquidacion->total_tecnicos * $liquidacion->fee_tecnicos,
                'total_bachillerato' => $liquidacion->total_bachillerato,
                'fee_bachillerato'   => $liquidacion->fee_bachillerato,
                'subtotal_bachillerato' => $liquidacion->total_bachillerato * $liquidacion->fee_bachillerato,
                'valor_total'        => $liquidacion->valor_total,
            ],
        ];
    }

    // ──────────────────────────────────────────────────────────────
    // HELPERS PRIVADOS
    // ──────────────────────────────────────────────────────────────

    /**
     * Certificados de una IETDH en un período, sin liquidación asignada,
     * no anulados, excluyendo los de IETDHs que son distribuidoras propias.
     */
    private function certificadosDelPeriodo(
        string $ietdhId,
        int $anio,
        int $mes
    ): Collection {
        $inicio = now()->setYear($anio)->setMonth($mes)->startOfMonth();
        $fin    = now()->setYear($anio)->setMonth($mes)->endOfMonth();

        return Certificado::where('ietdh_id', $ietdhId)
            ->whereNull('liquidacion_id')
            ->where('anulado', false)
            ->whereBetween('fecha_emision', [$inicio, $fin])
            // Excluir certificados donde la IETDH actuó como distribuidora propia
            ->whereHas('matricula.distribuidor', function ($q) use ($ietdhId) {
                $q->whereNotExists(function ($sub) use ($ietdhId) {
                    $sub->selectRaw('1')
                        ->from('ietdhs')
                        ->where('id', $ietdhId)
                        ->whereColumn('distribuidor_id', 'distribuidores.id');
                });
            })
            ->get();
    }

    /**
     * Devuelve el fee vigente de una IETDH para un tipo de programa
     * en una fecha específica (respeta el historial de fees).
     */
    private function feeVigenteEnFecha(
        Ietdh $ietdh,
        string $tipoPrograma,
        string $fecha
    ): ?float {
        return \App\Models\IetdhFee::where('ietdh_id', $ietdh->id)
            ->where('tipo_programa', $tipoPrograma)
            ->where('vigente_desde', '<=', $fecha)
            ->orderByDesc('vigente_desde')
            ->value('fee_por_certificado');
    }
}
