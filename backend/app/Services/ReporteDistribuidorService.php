<?php

namespace App\Services;

use App\Models\Certificado;
use App\Models\Distribuidor;
use App\Models\Matricula;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * ReporteDistribuidorService
 *
 * Construye los datos para los reportes automáticos a distribuidores.
 *
 * Dos tipos de reporte:
 *
 * SEMANAL (cada lunes):
 *  - Matrículas de la semana anterior (lunes a domingo)
 *  - Desglose por curso
 *  - Desglose por vendedor
 *  - Comparativo con la semana anterior
 *  - Acumulado del mes en curso
 *
 * MENSUAL (día 1 de cada mes):
 *  - Resumen completo del mes cerrado
 *  - Matrículas, cursos más vendidos, vendedores
 *  - Progreso de estudiantes (desde Moodle)
 *  - Certificados emitidos ese mes
 *  - Comparativo con el mes anterior
 */
class ReporteDistribuidorService
{
    public function __construct(
        private MoodleService $moodle,
    ) {}

    // ──────────────────────────────────────────────────────────────
    // REPORTE SEMANAL
    // ──────────────────────────────────────────────────────────────

    /**
     * Construye los datos del reporte semanal de un distribuidor.
     * Período: lunes a domingo de la semana anterior.
     */
    public function semanal(Distribuidor $distribuidor): array
    {
        $fin    = now()->startOfWeek()->subDay();       // último domingo
        $inicio = $fin->copy()->startOfWeek();           // lunes anterior

        $semanaAnteriorFin    = $inicio->copy()->subDay();
        $semanaAnteriorInicio = $semanaAnteriorFin->copy()->startOfWeek();

        // Matrículas de esta semana
        $matriculas = $this->matriculasEnPeriodo(
            $distribuidor->id, $inicio, $fin
        );

        // Matrículas semana anterior (para comparativo)
        $matriculasSemanaAnterior = $this->matriculasEnPeriodo(
            $distribuidor->id, $semanaAnteriorInicio, $semanaAnteriorFin
        );

        // Acumulado del mes en curso
        $inicioMes = now()->startOfMonth();
        $matriculasMes = $this->matriculasEnPeriodo(
            $distribuidor->id, $inicioMes, now()
        );

        return [
            'tipo'              => 'SEMANAL',
            'distribuidor'      => $distribuidor,
            'periodo_inicio'    => $inicio->format('d/m/Y'),
            'periodo_fin'       => $fin->format('d/m/Y'),
            'semana_numero'     => $fin->weekOfYear,
            'anio'              => $fin->year,

            // Esta semana
            'total_matriculas'        => $matriculas->count(),
            'por_curso'               => $this->agruparPorCurso($matriculas),
            'por_vendedor'            => $this->agruparPorVendedor($matriculas),

            // Comparativo
            'semana_anterior_total'   => $matriculasSemanaAnterior->count(),
            'variacion_pct'           => $this->variacionPct(
                $matriculasSemanaAnterior->count(),
                $matriculas->count()
            ),

            // Acumulado mes
            'mes_actual'              => now()->isoFormat('MMMM YYYY'),
            'mes_total_matriculas'    => $matriculasMes->count(),
        ];
    }

    // ──────────────────────────────────────────────────────────────
    // REPORTE MENSUAL
    // ──────────────────────────────────────────────────────────────

    /**
     * Construye los datos del reporte mensual de un distribuidor.
     * Período: mes anterior completo.
     */
    public function mensual(Distribuidor $distribuidor): array
    {
        $fin    = now()->startOfMonth()->subDay()->endOfDay();   // último día del mes anterior
        $inicio = $fin->copy()->startOfMonth();                  // primer día del mes anterior

        $mesAnteriorFin    = $inicio->copy()->subDay()->endOfDay();
        $mesAnteriorInicio = $mesAnteriorFin->copy()->startOfMonth();

        // Matrículas del mes
        $matriculas = $this->matriculasEnPeriodo(
            $distribuidor->id, $inicio, $fin
        );

        // Mes anterior (comparativo)
        $matriculasMesAnterior = $this->matriculasEnPeriodo(
            $distribuidor->id, $mesAnteriorInicio, $mesAnteriorFin
        );

        // Certificados emitidos ese mes (vía IETDH del distribuidor)
        $certificados = $this->certificadosDelMes($distribuidor, $inicio, $fin);

        // Progreso de estudiantes activos desde Moodle
        $progreso = $this->progresoEstudiantes($distribuidor);

        return [
            'tipo'              => 'MENSUAL',
            'distribuidor'      => $distribuidor,
            'periodo'           => $inicio->isoFormat('MMMM YYYY'),
            'periodo_inicio'    => $inicio->format('d/m/Y'),
            'periodo_fin'       => $fin->format('d/m/Y'),

            // Matrículas
            'total_matriculas'        => $matriculas->count(),
            'por_curso'               => $this->agruparPorCurso($matriculas),
            'por_vendedor'            => $this->agruparPorVendedor($matriculas),
            'top_cursos'              => $this->topCursos($matriculas, 5),

            // Comparativo mes anterior
            'mes_anterior'            => $mesAnteriorInicio->isoFormat('MMMM YYYY'),
            'mes_anterior_total'      => $matriculasMesAnterior->count(),
            'variacion_pct'           => $this->variacionPct(
                $matriculasMesAnterior->count(),
                $matriculas->count()
            ),

            // Certificados
            'certificados_emitidos'   => $certificados->count(),
            'detalle_certificados'    => $certificados->map(fn($c) => [
                'estudiante'  => $c->matricula->estudiante->nombre_completo,
                'programa'    => $c->matricula->curso->nombre,
                'acta_folio'  => "{$c->acta->codigo_acta}-{$c->folio}",
                'fecha'       => $c->fecha_emision->format('d/m/Y'),
            ])->toArray(),

            // Progreso desde Moodle
            'progreso_estudiantes'    => $progreso,

            // Estudiantes activos totales
            'total_estudiantes_activos' => Matricula::where('distribuidor_id', $distribuidor->id)
                ->where('estado', 'ACTIVA')
                ->distinct('estudiante_id')
                ->count('estudiante_id'),
        ];
    }

    // ──────────────────────────────────────────────────────────────
    // HELPERS DE CONSULTA
    // ──────────────────────────────────────────────────────────────

    private function matriculasEnPeriodo(
        string $distribuidorId,
        Carbon $inicio,
        Carbon $fin
    ): Collection {
        return Matricula::where('distribuidor_id', $distribuidorId)
            ->whereBetween('fecha_matricula', [$inicio, $fin])
            ->where('estado', '!=', 'CANCELADA')
            ->with(['curso', 'estudiante', 'vendedor'])
            ->get();
    }

    private function certificadosDelMes(
        Distribuidor $distribuidor,
        Carbon $inicio,
        Carbon $fin
    ): Collection {
        return Certificado::whereHas('matricula', fn($q) =>
            $q->where('distribuidor_id', $distribuidor->id)
        )
        ->whereBetween('fecha_emision', [$inicio, $fin])
        ->where('anulado', false)
        ->with(['acta', 'matricula.estudiante', 'matricula.curso'])
        ->get();
    }

    /**
     * Consulta el progreso de estudiantes activos desde la API de Moodle.
     * Devuelve un resumen: cuántos completaron más del 50%, menos del 50%, sin actividad.
     * Limita a 100 estudiantes para no sobrecargar Moodle.
     */
    private function progresoEstudiantes(Distribuidor $distribuidor): array
    {
        $matriculas = Matricula::where('distribuidor_id', $distribuidor->id)
            ->where('estado', 'ACTIVA')
            ->with(['estudiante', 'curso'])
            ->limit(100)
            ->get();

        $mas50   = 0;
        $menos50 = 0;
        $sinActividad = 0;

        foreach ($matriculas as $matricula) {
            if (!$matricula->estudiante->moodle_user_id) {
                continue;
            }

            try {
                $resp = $this->moodle->obtenerProgreso(
                    $matricula->estudiante->moodle_user_id,
                    $matricula->curso->moodle_course_id
                );

                $statuses = $resp['statuses'] ?? [];
                if (empty($statuses)) { $sinActividad++; continue; }

                $total     = count($statuses);
                $completos = count(array_filter($statuses, fn($s) => in_array($s['state'] ?? 0, [1, 2])));
                $pct       = $total > 0 ? ($completos / $total) * 100 : 0;

                if ($pct >= 50) $mas50++;
                elseif ($pct > 0) $menos50++;
                else $sinActividad++;

            } catch (\Exception $e) {
                Log::warning('Error consultando progreso en Moodle', [
                    'matricula_id' => $matricula->id,
                    'error'        => $e->getMessage(),
                ]);
            }
        }

        return [
            'mas_del_50_pct'    => $mas50,
            'menos_del_50_pct'  => $menos50,
            'sin_actividad'     => $sinActividad,
            'total_consultados' => $mas50 + $menos50 + $sinActividad,
        ];
    }

    // ──────────────────────────────────────────────────────────────
    // HELPERS DE AGRUPACIÓN
    // ──────────────────────────────────────────────────────────────

    private function agruparPorCurso(Collection $matriculas): array
    {
        return $matriculas->groupBy('curso_id')
            ->map(fn($grupo) => [
                'curso'   => $grupo->first()->curso->nombre,
                'total'   => $grupo->count(),
            ])
            ->sortByDesc('total')
            ->values()
            ->toArray();
    }

    private function agruparPorVendedor(Collection $matriculas): array
    {
        return $matriculas->groupBy(fn($m) => $m->vendedor_id ?? 'directo')
            ->map(fn($grupo, $key) => [
                'vendedor' => $key === 'directo'
                    ? 'Venta directa'
                    : ($grupo->first()->vendedor?->nombre ?? 'Desconocido'),
                'total'    => $grupo->count(),
            ])
            ->sortByDesc('total')
            ->values()
            ->toArray();
    }

    private function topCursos(Collection $matriculas, int $limite): array
    {
        return $matriculas->groupBy('curso_id')
            ->map(fn($g) => ['curso' => $g->first()->curso->nombre, 'total' => $g->count()])
            ->sortByDesc('total')
            ->take($limite)
            ->values()
            ->toArray();
    }

    private function variacionPct(int $anterior, int $actual): ?float
    {
        if ($anterior === 0) return null;
        return round((($actual - $anterior) / $anterior) * 100, 1);
    }
}
