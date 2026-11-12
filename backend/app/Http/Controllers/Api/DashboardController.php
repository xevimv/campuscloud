<?php
// app/Http/Controllers/Api/DashboardController.php
// NUEVO archivo — agregar al proyecto Laravel

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Certificado;
use App\Models\Distribuidor;
use App\Models\Liquidacion;
use App\Models\LogWebhook;
use App\Models\Matricula;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * DashboardController
 *
 * Provee todas las métricas para el dashboard de Nomaddi Admin.
 * Los datos se cachean 5 minutos para no saturar la BD.
 */
class DashboardController extends Controller
{
    /** GET /api/dashboard */
    public function index(): JsonResponse
    {
        $datos = Cache::remember('dashboard_nomaddi', 300, function () {
            return [
                'resumen_hoy'         => $this->resumenHoy(),
                'resumen_mes'         => $this->resumenMes(),
                'matriculas_por_dia'  => $this->matriculasPorDia(30),
                'top_distribuidores'  => $this->topDistribuidores(),
                'liquidaciones_pendientes' => $this->liquidacionesPendientes(),
                'webhooks_recientes'  => $this->webhooksRecientes(),
                'alertas'             => $this->alertas(),
            ];
        });

        return response()->json($datos);
    }

    private function resumenHoy(): array
    {
        $hoy = now()->startOfDay();
        return [
            'matriculas'   => Matricula::where('fecha_matricula', '>=', $hoy)->count(),
            'certificados' => Certificado::where('fecha_emision', '>=', $hoy)->count(),
            'webhooks_ok'  => LogWebhook::where('created_at', '>=', $hoy)
                ->where('estado', 'MATRICULADO')->count(),
            'webhooks_err' => LogWebhook::where('created_at', '>=', $hoy)
                ->whereIn('estado', ['RECHAZADO', 'ERROR'])->count(),
        ];
    }

    private function resumenMes(): array
    {
        $inicio = now()->startOfMonth();
        return [
            'matriculas'            => Matricula::where('fecha_matricula', '>=', $inicio)->count(),
            'certificados'          => Certificado::where('fecha_emision', '>=', $inicio)->count(),
            'distribuidores_activos'=> Distribuidor::where('activo', true)->count(),
            'liquidaciones_pendientes_valor' => Liquidacion::whereIn('estado', ['GENERADA','ENVIADA','FACTURADA'])
                ->sum('valor_total'),
        ];
    }

    private function matriculasPorDia(int $dias): array
    {
        return Matricula::select(
                DB::raw('DATE(fecha_matricula) as fecha'),
                DB::raw('COUNT(*) as total')
            )
            ->where('fecha_matricula', '>=', now()->subDays($dias))
            ->groupBy('fecha')
            ->orderBy('fecha')
            ->get()
            ->map(fn($r) => [
                'fecha' => $r->fecha,
                'total' => (int) $r->total,
            ])
            ->toArray();
    }

    private function topDistribuidores(): array
    {
        return Matricula::select('distribuidor_id', DB::raw('COUNT(*) as total'))
            ->where('fecha_matricula', '>=', now()->startOfMonth())
            ->groupBy('distribuidor_id')
            ->orderByDesc('total')
            ->limit(8)
            ->with('distribuidor:id,nombre')
            ->get()
            ->map(fn($r) => [
                'distribuidor' => $r->distribuidor->nombre,
                'total'        => (int) $r->total,
            ])
            ->toArray();
    }

    private function liquidacionesPendientes(): array
    {
        return Liquidacion::whereIn('estado', ['GENERADA', 'ENVIADA', 'FACTURADA'])
            ->with('ietdh:id,nombre')
            ->orderByDesc('valor_total')
            ->limit(10)
            ->get()
            ->map(fn($l) => [
                'id'          => $l->id,
                'ietdh'       => $l->ietdh->nombre,
                'periodo'     => $l->periodo,
                'valor_total' => (float) $l->valor_total,
                'estado'      => $l->estado,
            ])
            ->toArray();
    }

    private function webhooksRecientes(): array
    {
        return LogWebhook::latest('created_at')
            ->limit(10)
            ->get()
            ->map(fn($w) => [
                'ref'     => $w->epayco_ref,
                'estado'  => $w->estado,
                'monto'   => (float) $w->monto,
                'motivo'  => $w->motivo_rechazo,
                'hora'    => $w->created_at->format('H:i:s'),
            ])
            ->toArray();
    }

    private function alertas(): array
    {
        $alertas = [];

        // Webhooks fallidos en las últimas 2 horas
        $fallos = LogWebhook::where('created_at', '>=', now()->subHours(2))
            ->where('estado', 'ERROR')
            ->count();
        if ($fallos > 0) {
            $alertas[] = [
                'tipo'    => 'error',
                'mensaje' => "{$fallos} webhook(s) con error en las últimas 2 horas.",
                'url'     => '/webhooks',
            ];
        }

        // Liquidaciones sin pagar con más de 20 días
        $liqViejas = Liquidacion::whereIn('estado', ['GENERADA','ENVIADA','FACTURADA'])
            ->where('fecha_generacion', '<=', now()->subDays(20))
            ->count();
        if ($liqViejas > 0) {
            $alertas[] = [
                'tipo'    => 'warning',
                'mensaje' => "{$liqViejas} liquidación(es) pendientes de pago con más de 20 días.",
                'url'     => '/liquidaciones',
            ];
        }

        // Distribuidores sin IETDH asignada
        $sinIetdh = Distribuidor::where('activo', true)
            ->doesntHave('distribuidorIetdhs')
            ->count();
        if ($sinIetdh > 0) {
            $alertas[] = [
                'tipo'    => 'warning',
                'mensaje' => "{$sinIetdh} distribuidor(es) activo(s) sin IETDH asignada.",
                'url'     => '/distribuidores',
            ];
        }

        return $alertas;
    }
}
