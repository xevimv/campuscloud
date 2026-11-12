<?php

namespace App\Services;

use App\Models\Distribuidor;
use App\Models\Usuario;
use App\Models\Vendedor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * AuthService
 *
 * Gestiona autenticación y creación de usuarios del panel central.
 *
 * Flujo de login:
 *  1. Verificar email y password
 *  2. Verificar que el usuario esté activo
 *  3. Revocar tokens anteriores (sesión única)
 *  4. Generar token Sanctum con abilities según rol
 *  5. Devolver token + datos del usuario
 */
class AuthService
{
    // ──────────────────────────────────────────────────────────────
    // LOGIN / LOGOUT
    // ──────────────────────────────────────────────────────────────

    /**
     * @throws ValidationException si las credenciales son incorrectas
     */
    public function login(string $email, string $password): array
    {
        $usuario = Usuario::where('email', $email)->first();

        if (!$usuario || !Hash::check($password, $usuario->password)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales no son correctas.'],
            ]);
        }

        if (!$usuario->activo) {
            throw ValidationException::withMessages([
                'email' => ['Esta cuenta está desactivada. Contacta al administrador.'],
            ]);
        }

        // Sesión única: revocar todos los tokens anteriores
        $usuario->tokens()->delete();

        // Crear token con abilities según el rol
        $token = $usuario->createToken(
            name:       "panel-{$usuario->rol}",
            abilities:  $usuario->getAbilities(),
            expiresAt:  now()->addDays(7),
        );

        return [
            'token'    => $token->plainTextToken,
            'usuario'  => $this->formatearUsuario($usuario),
        ];
    }

    public function logout(Usuario $usuario): void
    {
        $usuario->currentAccessToken()->delete();
    }

    // ──────────────────────────────────────────────────────────────
    // CREAR USUARIOS
    // ──────────────────────────────────────────────────────────────

    /**
     * Crea un administrador de Nomaddi.
     * Solo puede ser creado por otro NOMADDI_ADMIN.
     */
    public function crearNomaddiAdmin(array $datos): Usuario
    {
        return Usuario::create([
            'nombre'   => $datos['nombre'],
            'email'    => $datos['email'],
            'password' => $datos['password'],
            'rol'      => 'NOMADDI_ADMIN',
        ]);
    }

    /**
     * Crea el administrador de un distribuidor.
     * Solo puede ser creado por un NOMADDI_ADMIN.
     */
    public function crearDistAdmin(array $datos, Distribuidor $distribuidor): Usuario
    {
        return Usuario::create([
            'nombre'          => $datos['nombre'],
            'email'           => $datos['email'],
            'password'        => $datos['password'],
            'rol'             => 'DIST_ADMIN',
            'distribuidor_id' => $distribuidor->id,
        ]);
    }

    /**
     * Crea un vendedor: registro en usuarios + registro en vendedores.
     * Solo puede ser creado por el DIST_ADMIN de ese distribuidor.
     *
     * @param array $datos {
     *   nombre, email, password, comision_tipo, comision_valor
     * }
     */
    public function crearVendedor(array $datos, Distribuidor $distribuidor): array
    {
        return DB::transaction(function () use ($datos, $distribuidor) {

            // Crear usuario del sistema
            $usuario = Usuario::create([
                'nombre'          => $datos['nombre'],
                'email'           => $datos['email'],
                'password'        => $datos['password'],
                'rol'             => 'VENDEDOR',
                'distribuidor_id' => $distribuidor->id,
            ]);

            // Crear registro de vendedor vinculado al usuario
            $vendedor = Vendedor::create([
                'distribuidor_id'  => $distribuidor->id,
                'usuario_id'       => $usuario->id,
                'nombre'           => $datos['nombre'],
                'email'            => $datos['email'],
                'codigo_referido'  => $this->generarCodigoReferido($datos['nombre']),
                'comision_tipo'    => $datos['comision_tipo'],
                'comision_valor'   => $datos['comision_valor'],
                'activo'           => true,
            ]);

            return [
                'usuario'  => $usuario,
                'vendedor' => $vendedor,
            ];
        });
    }

    /**
     * Cambia la contraseña de un usuario.
     */
    public function cambiarPassword(Usuario $usuario, string $nuevaPassword): void
    {
        $usuario->update(['password' => $nuevaPassword]);
        // Invalidar todos los tokens (forzar re-login)
        $usuario->tokens()->delete();
    }

    // ──────────────────────────────────────────────────────────────
    // HELPERS
    // ──────────────────────────────────────────────────────────────

    private function formatearUsuario(Usuario $usuario): array
    {
        $data = [
            'id'              => $usuario->id,
            'nombre'          => $usuario->nombre,
            'email'           => $usuario->email,
            'rol'             => $usuario->rol,
            'distribuidor_id' => $usuario->distribuidor_id,
        ];

        if ($usuario->distribuidor_id) {
            $data['distribuidor'] = [
                'id'     => $usuario->distribuidor->id,
                'nombre' => $usuario->distribuidor->nombre,
            ];
        }

        if ($usuario->esVendedor()) {
            $vendedor = $usuario->vendedor;
            $data['vendedor'] = [
                'id'              => $vendedor?->id,
                'codigo_referido' => $vendedor?->codigo_referido,
                'comision_tipo'   => $vendedor?->comision_tipo,
                'comision_valor'  => $vendedor?->comision_valor,
            ];
        }

        return $data;
    }

    /**
     * Genera un código de referido único basado en el nombre.
     * Formato: primeras 4 letras del nombre + 6 caracteres aleatorios.
     * Ejemplo: CARL-X7K2M9
     */
    private function generarCodigoReferido(string $nombre): string
    {
        $prefijo = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $nombre), 0, 4));
        do {
            $codigo = $prefijo . '-' . strtoupper(Str::random(6));
        } while (Vendedor::where('codigo_referido', $codigo)->exists());

        return $codigo;
    }
}
