<?php
// app/Http/Controllers/Api/AuthController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Distribuidor;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function __construct(
        private AuthService $auth,
    ) {}

    /** POST /api/auth/login */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $resultado = $this->auth->login($request->email, $request->password);

        return response()->json($resultado);
    }

    /** POST /api/auth/logout */
    public function logout(Request $request): JsonResponse
    {
        $this->auth->logout($request->user());
        return response()->json(['message' => 'Sesión cerrada correctamente.']);
    }

    /** GET /api/auth/me */
    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user()->load('distribuidor', 'vendedor'));
    }

    /** POST /api/auth/password */
    public function cambiarPassword(Request $request): JsonResponse
    {
        $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
        ]);

        $this->auth->cambiarPassword($request->user(), $request->password);

        return response()->json(['message' => 'Contraseña actualizada. Inicia sesión nuevamente.']);
    }

    // ── Crear usuarios (solo NOMADDI_ADMIN) ──────────────────────

    /** POST /api/auth/usuarios/admin */
    public function crearNomaddiAdmin(Request $request): JsonResponse
    {
        $request->validate([
            'nombre'   => 'required|string|max:150',
            'email'    => 'required|email|unique:usuarios,email',
            'password' => ['required', Password::min(8)->mixedCase()->numbers()],
        ]);

        $usuario = $this->auth->crearNomaddiAdmin($request->validated());
        return response()->json($usuario, 201);
    }

    /** POST /api/auth/usuarios/dist-admin */
    public function crearDistAdmin(Request $request): JsonResponse
    {
        $request->validate([
            'nombre'          => 'required|string|max:150',
            'email'           => 'required|email|unique:usuarios,email',
            'password'        => ['required', Password::min(8)->mixedCase()->numbers()],
            'distribuidor_id' => 'required|uuid|exists:distribuidores,id',
        ]);

        $distribuidor = Distribuidor::findOrFail($request->distribuidor_id);
        $usuario = $this->auth->crearDistAdmin($request->validated(), $distribuidor);

        return response()->json($usuario, 201);
    }
}
