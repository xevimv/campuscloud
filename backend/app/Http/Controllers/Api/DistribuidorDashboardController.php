<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Certificado;
use App\Models\Matricula;
use App\Models\Vendedor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * DistribuidorDashboardController
 *
 * Endpoints exclusivos para el rol DIST_ADMIN.
 * Todos los datos están filtrados automáticamente
 * por el distribuidor_id del usuario autenticado.
 *
 * El middleware CheckRol garantiza que solo DIST_ADMIN
 * puede acceder — nunca ve datos de otros distribuidores.
 */
class DistribuidorDashboardController extends Controller
{
    // ──────────────────────────────────────────────────────────────
    // DASHBOARD PRINCIPAL
    // ──────────────────────────────────────────────────────────────

    /**
     * GET /api/dist/dashboard
     * Métricas del mes actual + semana actual + totales históricos.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $distId    = $request->user()->distribuidor_id;
        $inicioMes = now()->startOfMonth();
        $inicioSemana = now()->startOfWeek();

        $totalMatriculas = Matricula::where('distribuidor_id', $distId)->count();

        $matriculasMes = Matricula::where('distribuidor_id', $distId)
            ->where('fecha_matricula', '>=', $inicioMes)
            ->where('estado', '!=', 'CANCELADA')
            ->count();

        $matriculasSemana = Matricula::where('distribuidor_id', $distId)
            ->where('fecha_matricula', '>=', $inicioSemana)
            ->where('estado', '!=', 'CANCELADA')
            ->count();

        $estudiantesActivos = Matricula::where('distribuidor_id', $distId)
            ->where('estado', 'ACTIVA')
            ->distinct('estudiante_id')
            ->count('estudiante_id');

        $certificadosMes = Certificado::whereHas('matricula', fn($q) =>
            $q->where('distribuidor_id', $distId)
        )->where('fecha_emision', '>=', $inicioMes)
         ->where('anulado', false)
         ->count();

        // Matrículas por día — últimas 4 semanas
        $matriculasPorDia = Matricula::where('distribuidor_id', $distId)
            ->where('fecha_matricula', '>=', now()->subDays(28))
            ->where('estado', '!=', 'CANCELADA')
            ->select(DB::raw('DATE(fecha_matricula) as fecha'), DB::raw('COUNT(*) as total'))
            ->groupBy('fecha')
            ->orderBy('fecha')
            ->get();

        // Top cursos del mes
        $topCursos = Matricula::where('distribuidor_id', $distId)
            ->where('fecha_matricula', '>=', $inicioMes)
            ->where('estado', '!=', 'CANCELADA')
            ->select('curso_id', DB::raw('COUNT(*) as total'))
            ->groupBy('curso_id')
            ->orderByDesc('total')
            ->limit(5)
            ->with('curso:id,nombre')
            ->get()
            ->map(fn($m) => [
                'curso'  => $m->curso->nombre,
                'total'  => (int) $m->total,
            ]);

        // Top vendedores del mes
        $topVendedores = Matricula::where('distribuidor_id', $distId)
            ->where('fecha_matricula', '>=', $inicioMes)
            ->whereNotNull('vendedor_id')
            ->select('vendedor_id', DB::raw('COUNT(*) as total'))
            ->groupBy('vendedor_id')
            ->orderByDesc('total')
            ->limit(5)
            ->with('vendedor:id,nombre')
            ->get()
            ->map(fn($m) => [
                'vendedor' => $m->vendedor->nombre,
                'total'    => (int) $m->total,
            ]);

        // Estudiantes sin completar el formulario SIET
        // (completaron curso 1 pero no llenaron el formulario)
        $pendientesSiet = Matricula::where('distribuidor_id', $distId)
            ->where('estado', 'COMPLETADA')
            ->whereHas('curso', fn($q) => $q->where('orden_en_carrera', 1))
            ->whereHas('estudiante', fn($q) =>
                $q->whereDoesntHave('siet', fn($s) => $s->whereNotNull('completado_en'))
            )
            ->count();

        return response()->json([
            'resumen' => [
                'total_matriculas'    => $totalMatriculas,
                'matriculas_mes'      => $matriculasMes,
                'matriculas_semana'   => $matriculasSemana,
                'estudiantes_activos' => $estudiantesActivos,
                'certificados_mes'    => $certificadosMes,
                'pendientes_siet'     => $pendientesSiet,
            ],
            'matriculas_por_dia' => $matriculasPorDia,
            'top_cursos'         => $topCursos,
            'top_vendedores'     => $topVendedores,
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // ESTUDIANTES
    // ──────────────────────────────────────────────────────────────

    /**
     * GET /api/dist/estudiantes
     * Lista paginada de estudiantes del distribuidor con estado SIET
     * y último acceso a Moodle.
     */
    public function estudiantes(Request $request): JsonResponse
    {
        $distId = $request->user()->distribuidor_id;

        $request->validate([
            'busqueda' => 'nullable|string|max:100',
            'estado'   => 'nullable|in:ACTIVA,COMPLETADA,CANCELADA',
            'siet'     => 'nullable|in:completo,pendiente',
        ]);

        $query = Matricula::where('distribuidor_id', $distId)
            ->with([
                'estudiante.siet',
                'curso:id,nombre,orden_en_carrera,tipo_programa',
                'vendedor:id,nombre',
            ])
            ->orderByDesc('fecha_matricula');

        if ($request->estado) {
            $query->where('estado', $request->estado);
        }

        if ($request->busqueda) {
            $busqueda = "%{$request->busqueda}%";
            $query->whereHas('estudiante', fn($q) =>
                $q->where('nombres', 'ilike', $busqueda)
                  ->orWhere('apellidos', 'ilike', $busqueda)
                  ->orWhere('numero_documento', 'ilike', $busqueda)
                  ->orWhere('email', 'ilike', $busqueda)
            );
        }

        if ($request->siet === 'completo') {
            $query->whereHas('estudiante.siet', fn($q) => $q->whereNotNull('completado_en'));
        } elseif ($request->siet === 'pendiente') {
            $query->whereHas('estudiante', fn($q) =>
                $q->whereDoesntHave('siet', fn($s) => $s->whereNotNull('completado_en'))
            );
        }

        $matriculas = $query->paginate(20);

        return response()->json($matriculas->through(fn($m) => [
            'matricula_id'    => $m->id,
            'fecha_matricula' => $m->fecha_matricula->format('d/m/Y'),
            'estado'          => $m->estado,
            'curso'           => $m->curso->nombre,
            'tipo_programa'   => $m->curso->tipo_programa,
            'vendedor'        => $m->vendedor?->nombre ?? 'Directo',
            'estudiante' => [
                'id'               => $m->estudiante->id,
                'nombres'          => $m->estudiante->nombres,
                'apellidos'        => $m->estudiante->apellidos,
                'numero_documento' => $m->estudiante->numero_documento,
                'email'            => $m->estudiante->email,
                'siet_completo'    => $m->estudiante->tieneSietCompleto(),
            ],
        ]));
    }

    // ──────────────────────────────────────────────────────────────
    // VENDEDORES
    // ──────────────────────────────────────────────────────────────

    /**
     * GET /api/dist/vendedores
     * Vendedores del distribuidor con métricas del mes.
     */
    public function vendedores(Request $request): JsonResponse
    {
        $distId    = $request->user()->distribuidor_id;
        $inicioMes = now()->startOfMonth();

        $vendedores = Vendedor::where('distribuidor_id', $distId)
            ->with(['matriculas' => fn($q) =>
                $q->where('fecha_matricula', '>=', $inicioMes)
                  ->where('estado', '!=', 'CANCELADA')
            ])
            ->get()
            ->map(function ($v) use ($inicioMes) {
                $matriculasMes = $v->matriculas->count();
                return [
                    'id'              => $v->id,
                    'nombre'          => $v->nombre,
                    'email'           => $v->email,
                    'codigo_referido' => $v->codigo_referido,
                    'comision_tipo'   => $v->comision_tipo,
                    'comision_valor'  => $v->comision_valor,
                    'activo'          => $v->activo,
                    'matriculas_mes'  => $matriculasMes,
                    'comision_mes'    => $v->calcularComision(
                        $v->matriculas->sum('monto_pagado')
                    ),
                    'total_historico' => Matricula::where('vendedor_id', $v->id)->count(),
                    'enlace_ejemplo'  => "https://" . optional($v->distribuidor)->dominio . "/?ref={$v->codigo_referido}",
                ];
            });

        return response()->json($vendedores);
    }

    // ──────────────────────────────────────────────────────────────
    // CERTIFICADOS
    // ──────────────────────────────────────────────────────────────

    /**
     * GET /api/dist/certificados
     * Certificados emitidos a estudiantes del distribuidor.
     */
    public function certificados(Request $request): JsonResponse
    {
        $distId = $request->user()->distribuidor_id;

        $certificados = Certificado::whereHas('matricula', fn($q) =>
            $q->where('distribuidor_id', $distId)
        )
        ->with(['acta', 'ietdh:id,nombre', 'matricula.estudiante:id,nombres,apellidos,numero_documento', 'matricula.curso:id,nombre'])
        ->where('anulado', false)
        ->orderByDesc('fecha_emision')
        ->paginate(20);

        return response()->json($certificados->through(fn($c) => [
            'id'            => $c->id,
            'referencia'    => "{$c->acta->codigo_acta}-{$c->folio}",
            'uuid'          => $c->uuid_verificacion,
            'fecha'         => $c->fecha_emision->format('d/m/Y'),
            'ietdh'         => $c->ietdh->nombre,
            'estudiante'    => "{$c->matricula->estudiante->nombres} {$c->matricula->estudiante->apellidos}",
            'documento'     => $c->matricula->estudiante->numero_documento,
            'curso'         => $c->matricula->curso->nombre,
            'url_verificar' => config('app.url') . "/verificar/{$c->uuid_verificacion}",
        ]));
    }
}
