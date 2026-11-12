<?php

namespace App\Services;

use App\Models\Curso;
use App\Models\Distribuidor;
use App\Models\Precio;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * CursoService
 *
 * Gestiona el ciclo de vida de un curso en el panel central.
 *
 * Regla clave de sincronización:
 * Al crear un curso → crear 1 grupo por cada distribuidor activo en Moodle
 * Al desactivar un curso → no se eliminan grupos (historial intacto)
 */
class CursoService
{
    public function __construct(
        private DistribuidorService $distribuidorService,
    ) {}

    // ──────────────────────────────────────────────────────────────
    // CREAR CURSO
    // ──────────────────────────────────────────────────────────────

    /**
     * Registra un curso en el panel central y crea sus grupos en Moodle
     * para todos los distribuidores activos.
     *
     * @param array $datos {
     *   moodle_course_id, nombre, tipo_programa, orden_en_carrera,
     *   es_ultimo, fee_nomaddi, precio_minimo_publico
     * }
     */
    public function crear(array $datos): Curso
    {
        return DB::transaction(function () use ($datos) {

            // ── 1. Crear el curso en BD ───────────────────────────────
            $curso = Curso::create([
                'moodle_course_id' => $datos['moodle_course_id'],
                'nombre'           => $datos['nombre'],
                'tipo_programa'    => $datos['tipo_programa'],
                'orden_en_carrera' => $datos['orden_en_carrera'],
                'es_ultimo'        => $datos['es_ultimo'] ?? false,
                'activo'           => true,
            ]);

            // ── 2. Registrar precio inicial ───────────────────────────
            Precio::create([
                'curso_id'              => $curso->id,
                'fee_nomaddi'           => $datos['fee_nomaddi'],
                'precio_minimo_publico' => $datos['precio_minimo_publico'],
                'vigente_desde'         => now()->toDateString(),
            ]);

            // ── 3. Crear grupos en Moodle para cada distribuidor activo
            $distribuidores = Distribuidor::where('activo', true)->get();

            foreach ($distribuidores as $distribuidor) {
                try {
                    $this->distribuidorService->crearGrupoEnCurso($distribuidor, $curso);
                } catch (\Exception $e) {
                    // Log pero no abortar: un grupo fallido no bloquea el alta del curso
                    Log::error("Error creando grupo Moodle para distribuidor {$distribuidor->id} en curso {$curso->id}", [
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            Log::info('Curso creado y sincronizado', [
                'curso_id'     => $curso->id,
                'nombre'       => $curso->nombre,
                'distribuidores_sincronizados' => $distribuidores->count(),
            ]);

            return $curso->fresh(['precioVigente']);
        });
    }

    // ──────────────────────────────────────────────────────────────
    // ACTUALIZAR PRECIO
    // ──────────────────────────────────────────────────────────────

    /**
     * Agrega un nuevo precio al historial del curso.
     * El precio anterior queda intacto para auditoría.
     * El nuevo precio aplica desde hoy.
     */
    public function actualizarPrecio(
        Curso $curso,
        float $feeNomaddi,
        float $precioMinimoPublico,
        ?string $vigenteDesde = null
    ): Precio {
        $precio = Precio::create([
            'curso_id'              => $curso->id,
            'fee_nomaddi'           => $feeNomaddi,
            'precio_minimo_publico' => $precioMinimoPublico,
            'vigente_desde'         => $vigenteDesde ?? now()->toDateString(),
        ]);

        Log::info('Precio actualizado', [
            'curso_id'       => $curso->id,
            'nuevo_fee'      => $feeNomaddi,
            'nuevo_minimo'   => $precioMinimoPublico,
            'vigente_desde'  => $precio->vigente_desde,
        ]);

        return $precio;
    }

    // ──────────────────────────────────────────────────────────────
    // ACTIVAR / DESACTIVAR
    // ──────────────────────────────────────────────────────────────

    public function desactivar(Curso $curso): void
    {
        $curso->update(['activo' => false]);
        Log::info('Curso desactivado', ['curso_id' => $curso->id]);
    }

    public function activar(Curso $curso): void
    {
        $curso->update(['activo' => true]);
        Log::info('Curso activado', ['curso_id' => $curso->id]);
    }

    // ──────────────────────────────────────────────────────────────
    // MARCAR COMO ÚLTIMO DE LA CARRERA
    // ──────────────────────────────────────────────────────────────

    /**
     * Solo puede haber un curso "último" por tipo de programa.
     * Al marcar uno nuevo, desactiva el anterior.
     */
    public function marcarComoUltimo(Curso $curso): void
    {
        DB::transaction(function () use ($curso) {
            // Desmarcar el anterior último del mismo programa
            Curso::where('tipo_programa', $curso->tipo_programa)
                ->where('es_ultimo', true)
                ->where('id', '!=', $curso->id)
                ->update(['es_ultimo' => false]);

            $curso->update(['es_ultimo' => true]);
        });

        Log::info('Curso marcado como último de la carrera', [
            'curso_id'      => $curso->id,
            'tipo_programa' => $curso->tipo_programa,
        ]);
    }
}
