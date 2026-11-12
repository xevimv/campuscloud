<?php

namespace App\Services;

use App\Models\Curso;
use App\Models\Precio;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * MoodleSincronizadorService
 *
 * Lee los cursos existentes en Moodle vía API REST y permite
 * importarlos al panel central sin tener que ingresar
 * manualmente cada moodle_course_id.
 *
 * Flujo:
 *  1. listarCursosMoodle()   → trae todos los cursos de Moodle
 *  2. compararConPanel()     → marca cuáles ya están importados
 *  3. importar()             → registra los seleccionados en el panel
 *                              + sincroniza grupos para distribuidores
 */
class MoodleSincronizadorService
{
    public function __construct(
        private MoodleService       $moodle,
        private CursoService        $cursoService,
        private DistribuidorService $distribuidorService,
    ) {}

    // ──────────────────────────────────────────────────────────────
    // LISTAR CURSOS DESDE MOODLE
    // ──────────────────────────────────────────────────────────────

    /**
     * Trae todos los cursos visibles de Moodle.
     * Filtra el curso "Sitio" (id=1) que siempre existe en Moodle.
     * Devuelve los datos normalizados listos para mostrar en el panel.
     */
    public function listarCursosMoodle(): Collection
    {
        $respuesta = $this->moodle->listarCursos();

        // IDs ya registrados en el panel (para marcar cuáles ya están)
        $registrados = Curso::pluck('moodle_course_id')->toArray();

        return collect($respuesta)
            ->filter(fn($c) => $c['id'] > 1) // excluir curso "Sitio"
            ->map(fn($c) => [
                'moodle_course_id' => $c['id'],
                'nombre'           => $c['fullname'],
                'nombre_corto'     => $c['shortname'],
                'categoria'        => $c['categoryname'] ?? null,
                'visible'          => (bool) ($c['visible'] ?? true),
                'ya_importado'     => in_array($c['id'], $registrados),
                'curso_id_panel'   => Curso::where('moodle_course_id', $c['id'])->value('id'),
            ])
            ->sortBy('nombre')
            ->values();
    }

    // ──────────────────────────────────────────────────────────────
    // IMPORTAR CURSOS SELECCIONADOS
    // ──────────────────────────────────────────────────────────────

    /**
     * Importa al panel central los cursos seleccionados por el admin.
     *
     * @param array $cursos  Array de cursos a importar, cada uno con:
     *   {
     *     moodle_course_id: int,
     *     nombre: string,
     *     tipo_programa: 'TECNICOS' | 'BACHILLERATO',
     *     orden_en_carrera: int,
     *     es_ultimo: bool,
     *     fee_nomaddi: float,
     *     precio_minimo_publico: float,
     *   }
     *
     * @return array  Resumen: importados, omitidos (ya existían), errores
     */
    public function importar(array $cursos): array
    {
        $importados = 0;
        $omitidos   = 0;
        $errores    = [];

        foreach ($cursos as $datos) {
            // Verificar que no esté ya registrado
            if (Curso::where('moodle_course_id', $datos['moodle_course_id'])->exists()) {
                $omitidos++;
                continue;
            }

            try {
                $this->cursoService->crear([
                    'moodle_course_id'      => $datos['moodle_course_id'],
                    'nombre'                => $datos['nombre'],
                    'tipo_programa'         => $datos['tipo_programa'],
                    'orden_en_carrera'      => $datos['orden_en_carrera'],
                    'es_ultimo'             => $datos['es_ultimo'] ?? false,
                    'fee_nomaddi'           => $datos['fee_nomaddi'],
                    'precio_minimo_publico' => $datos['precio_minimo_publico'],
                ]);

                $importados++;

                Log::info('Curso importado desde Moodle', [
                    'moodle_course_id' => $datos['moodle_course_id'],
                    'nombre'           => $datos['nombre'],
                ]);

            } catch (\Exception $e) {
                $errores[] = [
                    'curso'  => $datos['nombre'],
                    'error'  => $e->getMessage(),
                ];
                Log::error('Error importando curso desde Moodle', [
                    'moodle_course_id' => $datos['moodle_course_id'],
                    'error'            => $e->getMessage(),
                ]);
            }
        }

        return [
            'importados' => $importados,
            'omitidos'   => $omitidos,
            'errores'    => $errores,
        ];
    }

    // ──────────────────────────────────────────────────────────────
    // SINCRONIZAR UN CURSO YA IMPORTADO
    // ──────────────────────────────────────────────────────────────

    /**
     * Actualiza el nombre de un curso en el panel
     * si fue renombrado en Moodle.
     * No toca precios ni configuración del panel.
     */
    public function sincronizarNombre(Curso $curso): bool
    {
        try {
            $datosMoodle = $this->moodle->obtenerCurso($curso->moodle_course_id);

            if ($datosMoodle['fullname'] !== $curso->nombre) {
                $curso->update(['nombre' => $datosMoodle['fullname']]);
                Log::info('Nombre de curso actualizado desde Moodle', [
                    'curso_id'   => $curso->id,
                    'nombre_nuevo' => $datosMoodle['fullname'],
                ]);
                return true;
            }
            return false;
        } catch (\Exception $e) {
            Log::error('Error sincronizando nombre de curso', [
                'curso_id' => $curso->id,
                'error'    => $e->getMessage(),
            ]);
            return false;
        }
    }
}
