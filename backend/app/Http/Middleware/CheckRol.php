<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * CheckRol
 *
 * Middleware que verifica que el usuario autenticado tenga
 * uno de los roles permitidos para la ruta.
 *
 * Uso en rutas:
 *   ->middleware('rol:NOMADDI_ADMIN')
 *   ->middleware('rol:NOMADDI_ADMIN,DIST_ADMIN')
 *
 * También verifica que DIST_ADMIN y VENDEDOR solo accedan
 * a recursos de su propio distribuidor.
 */
class CheckRol
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $usuario = $request->user();

        if (!$usuario) {
            return response()->json(['message' => 'No autenticado.'], 401);
        }

        if (!$usuario->activo) {
            return response()->json(['message' => 'Cuenta desactivada.'], 403);
        }

        if (!in_array($usuario->rol, $roles)) {
            return response()->json([
                'message' => 'No tienes permisos para esta acción.',
                'rol_requerido' => $roles,
                'tu_rol'        => $usuario->rol,
            ], 403);
        }

        // Para DIST_ADMIN y VENDEDOR: verificar que el recurso es de su distribuidor
        if (in_array($usuario->rol, ['DIST_ADMIN', 'VENDEDOR'])) {
            $this->verificarScopeDistribuidor($request, $usuario);
        }

        return $next($request);
    }

    /**
     * Verifica que el distribuidor_id en la ruta o en el body
     * coincide con el distribuidor del usuario autenticado.
     */
    private function verificarScopeDistribuidor(Request $request, $usuario): void
    {
        // Si la ruta tiene un parámetro {distribuidor}, verificar que sea el suyo
        $distribuidorEnRuta = $request->route('distribuidor');

        if ($distribuidorEnRuta) {
            $idEnRuta = is_string($distribuidorEnRuta)
                ? $distribuidorEnRuta
                : $distribuidorEnRuta->id;

            if ($idEnRuta !== $usuario->distribuidor_id) {
                abort(403, 'No tienes acceso a recursos de otro distribuidor.');
            }
        }
    }
}
