<?php

namespace App\Jobs;

use App\Models\Matricula;
use App\Services\CertificadoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * DetectarUltimoCursoCompletado
 *
 * Corre cada hora (en paralelo con SincronizarCompletitudMoodle).
 * Busca matrículas de cursos marcados como "es_ultimo = true"
 * que estén COMPLETADAS pero sin certificado emitido todavía.
 *
 * Para cada una, llama CertificadoService::emitir() que:
 *  → Genera acta + folio + UUID
 *  → Crea el certificado en BD
 *  → Actualiza campos en Moodle (Custom Certificate)
 *  → Registra el devengo del fee de IETDH
 */
class DetectarUltimoCursoCompletado implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 120;

    public function handle(CertificadoService $certificadoService): void
    {
        Log::info('DetectarUltimoCursoCompletado: iniciando');

        // Matrículas completadas del último curso sin certificado
        $matriculas = Matricula::where('estado', 'COMPLETADA')
            ->whereHas('curso', fn($q) => $q->where('es_ultimo', true))
            ->whereDoesntHave('certificado')
            ->with(['estudiante', 'curso', 'distribuidor.distribuidorIetdhs.ietdh'])
            ->limit(50) // procesar en lotes
            ->get();

        $emitidos = 0;
        $errores  = 0;

        foreach ($matriculas as $matricula) {
            try {
                $certificado = $certificadoService->emitir($matricula);
                $emitidos++;

                Log::info('Certificado emitido automáticamente', [
                    'certificado_id' => $certificado->id,
                    'estudiante'     => $matricula->estudiante->email,
                    'acta'           => $certificado->acta->codigo_acta,
                    'folio'          => $certificado->folio,
                ]);

            } catch (\Exception $e) {
                $errores++;
                Log::error('Error emitiendo certificado', [
                    'matricula_id' => $matricula->id,
                    'estudiante'   => $matricula->estudiante->email,
                    'error'        => $e->getMessage(),
                ]);
            }
        }

        Log::info("DetectarUltimoCursoCompletado: {$emitidos} emitidos, {$errores} errores");
    }
}
