<?php

namespace App\Services;

use App\Models\Distribuidor;
use App\Models\Vendedor;
use Illuminate\Support\Facades\Log;

/**
 * VendedorService
 *
 * Gestiona vendedores y resuelve el código de referido
 * que llega en el webhook de ePayco.
 *
 * Flujo del código referido:
 *  1. El vendedor comparte su enlace: dominio.com/?ref=CARL-X7K2M9
 *  2. WooCommerce guarda el ?ref en una cookie/sesión
 *  3. Al pagar, el plugin de ePayco incluye el código en p_attr_6
 *  4. El webhook llega al panel con ese código
 *  5. EpaycoService lo extrae y lo pasa al MatriculaService
 *  6. MatriculaService llama resolverVendedor() para obtener el vendedor_id
 */
class VendedorService
{
    // ──────────────────────────────────────────────────────────────
    // RESOLUCIÓN DE CÓDIGO REFERIDO
    // ──────────────────────────────────────────────────────────────

    /**
     * Busca el vendedor por código de referido.
     * Verifica que pertenezca al mismo distribuidor de la matrícula.
     * Devuelve null si el código no existe, está inactivo o no coincide.
     */
    public function resolverPorCodigo(
        ?string $codigoReferido,
        string $distribuidorId
    ): ?Vendedor {
        if (!$codigoReferido) {
            return null;
        }

        $vendedor = Vendedor::where('codigo_referido', $codigoReferido)
            ->where('activo', true)
            ->first();

        if (!$vendedor) {
            Log::warning('Código de referido no encontrado', [
                'codigo' => $codigoReferido,
            ]);
            return null;
        }

        // Verificar que el vendedor pertenece al distribuidor de esta venta
        if ($vendedor->distribuidor_id !== $distribuidorId) {
            Log::warning('Código de referido de otro distribuidor', [
                'codigo'           => $codigoReferido,
                'vendedor_dist'    => $vendedor->distribuidor_id,
                'matricula_dist'   => $distribuidorId,
            ]);
            return null;
        }

        return $vendedor;
    }

    // ──────────────────────────────────────────────────────────────
    // CRUD DE VENDEDORES
    // ──────────────────────────────────────────────────────────────

    public function desactivar(Vendedor $vendedor): void
    {
        $vendedor->update(['activo' => false]);
        // Desactivar también su usuario del sistema
        $vendedor->usuario?->update(['activo' => false]);
        Log::info('Vendedor desactivado', ['vendedor_id' => $vendedor->id]);
    }

    public function reactivar(Vendedor $vendedor): void
    {
        $vendedor->update(['activo' => true]);
        $vendedor->usuario?->update(['activo' => true]);
        Log::info('Vendedor reactivado', ['vendedor_id' => $vendedor->id]);
    }

    public function actualizarComision(
        Vendedor $vendedor,
        string $tipo,
        float $valor
    ): Vendedor {
        $vendedor->update([
            'comision_tipo'  => $tipo,
            'comision_valor' => $valor,
        ]);
        return $vendedor->fresh();
    }

    // ──────────────────────────────────────────────────────────────
    // REPORTES
    // ──────────────────────────────────────────────────────────────

    /**
     * Resumen de todos los vendedores de un distribuidor en un período.
     * Usado en el panel del distribuidor y en el reporte semanal/mensual.
     */
    public function resumenPorDistribuidor(
        Distribuidor $distribuidor,
        string $desde,
        string $hasta
    ): array {
        return $distribuidor->vendedores()
            ->where('activo', true)
            ->with(['matriculas' => fn($q) => $q
                ->whereBetween('fecha_matricula', [$desde, $hasta])
                ->where('estado', '!=', 'CANCELADA')
            ])
            ->get()
            ->map(fn($v) => $v->resumenComisiones($desde, $hasta))
            ->toArray();
    }
}
