<?php
// app/Jobs/EnviarReportesMensual.php

namespace App\Jobs;

use App\Mail\ReporteMensual;
use App\Models\Distribuidor;
use App\Services\ReporteDistribuidorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * EnviarReportesMensual
 *
 * Corre el día 1 de cada mes a las 8:30 AM.
 * (30 minutos después del cierre de liquidaciones a las 8:00 AM)
 * Envía el reporte del mes anterior a cada distribuidor activo.
 */
class EnviarReportesMensual implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 2;
    public int $timeout = 300;

    public function handle(ReporteDistribuidorService $reporteService): void
    {
        Log::info('EnviarReportesMensual: iniciando');

        $distribuidores = Distribuidor::where('activo', true)
            ->whereNotNull('email_contacto')
            ->get();

        $enviados = 0;
        $errores  = 0;

        foreach ($distribuidores as $distribuidor) {
            try {
                $datos = $reporteService->mensual($distribuidor);

                // Enviar solo si tuvo matrículas ese mes
                if ($datos['total_matriculas'] === 0 &&
                    $datos['certificados_emitidos'] === 0) {
                    Log::info('Sin actividad mensual, omitiendo reporte', [
                        'distribuidor' => $distribuidor->nombre,
                    ]);
                    continue;
                }

                Mail::to($distribuidor->email_contacto)
                    ->queue(new ReporteMensual($distribuidor, $datos));

                $enviados++;
                Log::info('Reporte mensual encolado', [
                    'distribuidor'   => $distribuidor->nombre,
                    'matriculas'     => $datos['total_matriculas'],
                    'certificados'   => $datos['certificados_emitidos'],
                ]);

            } catch (\Exception $e) {
                $errores++;
                Log::error('Error generando reporte mensual', [
                    'distribuidor' => $distribuidor->nombre,
                    'error'        => $e->getMessage(),
                ]);
            }
        }

        Log::info("EnviarReportesMensual: {$enviados} enviados, {$errores} errores");
    }
}
