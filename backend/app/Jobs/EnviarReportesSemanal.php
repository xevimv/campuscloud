<?php
// app/Jobs/EnviarReportesSemanal.php

namespace App\Jobs;

use App\Mail\ReporteSemanal;
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
 * EnviarReportesSemanal
 *
 * Corre cada lunes a las 8:00 AM.
 * Envía el reporte de la semana anterior a cada distribuidor activo.
 *
 * Solo envía si el distribuidor tiene al menos una matrícula histórica
 * (evita correos vacíos a distribuidores nuevos sin actividad).
 */
class EnviarReportesSemanal implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 2;
    public int $timeout = 180;

    public function handle(ReporteDistribuidorService $reporteService): void
    {
        Log::info('EnviarReportesSemanal: iniciando');

        $distribuidores = Distribuidor::where('activo', true)
            ->whereNotNull('email_contacto')
            ->get();

        $enviados = 0;
        $errores  = 0;

        foreach ($distribuidores as $distribuidor) {
            try {
                $datos = $reporteService->semanal($distribuidor);

                // Enviar solo si tiene actividad total (no solo esta semana)
                // para no bombardear distribuidores inactivos
                $tieneHistorial = \App\Models\Matricula::where('distribuidor_id', $distribuidor->id)->exists();
                if (!$tieneHistorial) continue;

                Mail::to($distribuidor->email_contacto)
                    ->queue(new ReporteSemanal($distribuidor, $datos));

                $enviados++;
                Log::info('Reporte semanal encolado', [
                    'distribuidor'   => $distribuidor->nombre,
                    'matriculas'     => $datos['total_matriculas'],
                ]);

            } catch (\Exception $e) {
                $errores++;
                Log::error('Error generando reporte semanal', [
                    'distribuidor' => $distribuidor->nombre,
                    'error'        => $e->getMessage(),
                ]);
            }
        }

        Log::info("EnviarReportesSemanal: {$enviados} enviados, {$errores} errores");
    }
}
