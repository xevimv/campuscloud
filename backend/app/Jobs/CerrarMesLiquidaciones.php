<?php

namespace App\Jobs;

use App\Mail\LiquidacionGenerada;
use App\Models\Ietdh;
use App\Services\LiquidacionService;
use App\Services\PdfLiquidacionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * CerrarMesLiquidaciones
 *
 * Se ejecuta el día 1 de cada mes a las 6:00 AM.
 * Cierra el mes anterior: genera liquidaciones + PDFs + envía correos a IETDHs.
 *
 * Scheduler en routes/console.php:
 *   Schedule::job(new CerrarMesLiquidaciones)->monthlyOn(1, '06:00');
 */
class CerrarMesLiquidaciones implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 2;
    public int $timeout = 300;

    public function handle(
        LiquidacionService    $liquidacionService,
        PdfLiquidacionService $pdfService,
    ): void {
        // Período a liquidar: mes anterior
        $periodo = now()->subMonth();
        $anio    = (int) $periodo->format('Y');
        $mes     = (int) $periodo->format('n');

        Log::info("CerrarMesLiquidaciones: iniciando cierre {$mes}/{$anio}");

        // 1. Generar todas las liquidaciones del período
        $resultado = $liquidacionService->cerrarMes($anio, $mes);

        Log::info("Liquidaciones generadas", $resultado);

        // 2. Para cada liquidación generada: PDF + correo
        $liquidaciones = \App\Models\Liquidacion::where('periodo_anio', $anio)
            ->where('periodo_mes', $mes)
            ->where('estado', 'GENERADA')
            ->with('ietdh')
            ->get();

        foreach ($liquidaciones as $liquidacion) {
            try {
                // Cargar detalle completo
                $detalle = $liquidacionService->detalle($liquidacion);

                // Generar PDF
                $rutaPdf = $pdfService->generar($liquidacion, $detalle);

                // Enviar correo con PDF adjunto
                Mail::to($liquidacion->ietdh->email_contacto)
                    ->queue(new LiquidacionGenerada($liquidacion, $detalle, $rutaPdf));

                // Marcar como enviada
                $liquidacionService->marcarEnviada($liquidacion);

                Log::info('Liquidación enviada', [
                    'liquidacion_id' => $liquidacion->id,
                    'ietdh'          => $liquidacion->ietdh->nombre,
                    'valor_total'    => $liquidacion->valor_total,
                ]);

            } catch (\Exception $e) {
                Log::error('Error procesando liquidación', [
                    'liquidacion_id' => $liquidacion->id,
                    'ietdh'          => $liquidacion->ietdh->nombre,
                    'error'          => $e->getMessage(),
                ]);
            }
        }

        Log::info("CerrarMesLiquidaciones: completado {$mes}/{$anio}");
    }
}
