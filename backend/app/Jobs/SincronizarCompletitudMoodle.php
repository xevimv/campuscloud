<?php
// app/Jobs/SincronizarCompletitudMoodle.php

namespace App\Jobs;

use App\Models\Curso;
use App\Models\Matricula;
use App\Services\MoodleService;
use App\Services\SietService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * SincronizarCompletitudMoodle
 *
 * Corre cada hora (configurado en routes/console.php).
 * Consulta el estado de completitud de los cursos activos
 * en Moodle y actualiza las matrículas en la BD.
 *
 * Cuando detecta que el curso 1 fue completado:
 *  → Marca la matrícula como COMPLETADA en la BD
 *  → Si el estudiante aún no tiene el SIET completo, el formulario
 *    aparece disponible en su panel (el frontend lo consulta via GET /siet/estado)
 *
 * Nota: Si tienes el plugin "Moodle Webhooks" instalado,
 * puedes reemplazar este Job por un webhook en tiempo real
 * que llame POST /api/moodle/completitud con los datos del evento.
 */
class SincronizarCompletitudMoodle implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function handle(MoodleService $moodle): void
    {
        Log::info('SincronizarCompletitudMoodle: iniciando');

        // Solo cursos activos con matrículas activas
        $matriculasActivas = Matricula::where('estado', 'ACTIVA')
            ->whereNotNull('moodle_group_id') // ya tienen ID de Moodle
            ->with(['estudiante', 'curso'])
            ->limit(200) // procesar en lotes para no sobrecargar Moodle
            ->get();

        $completadas = 0;

        foreach ($matriculasActivas as $matricula) {
            $estudiante = $matricula->estudiante;

            if (!$estudiante->moodle_user_id) {
                continue;
            }

            try {
                $completado = $moodle->consultarCompletitud(
                    $estudiante->moodle_user_id,
                    $matricula->curso->moodle_course_id
                );

                if ($completado) {
                    $matricula->update([
                        'estado'           => 'COMPLETADA',
                        'fecha_completado' => now(),
                    ]);
                    $completadas++;

                    Log::info('Matrícula marcada como completada', [
                        'matricula_id'  => $matricula->id,
                        'estudiante'    => $estudiante->email,
                        'curso'         => $matricula->curso->nombre,
                    ]);
                }

            } catch (\Exception $e) {
                Log::error('Error consultando completitud en Moodle', [
                    'matricula_id' => $matricula->id,
                    'error'        => $e->getMessage(),
                ]);
            }
        }

        Log::info("SincronizarCompletitudMoodle: {$completadas} matrículas completadas");
    }
}
