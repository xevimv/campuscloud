<?php

namespace App\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * MoodleService
 *
 * Wrapper sobre la API REST de Moodle 4.5.
 * Todas las llamadas usan el token de webservice configurado en .env
 *
 * Funciones usadas:
 *  - core_user_create_users
 *  - core_user_get_users
 *  - enrol_manual_enrol_users
 *  - cohort_add_cohort_members
 *  - groups_add_group_member
 *  - core_completion_get_activities_completion_status
 */
class MoodleService
{
    private string $baseUrl;
    private string $token;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.moodle.url'), '/');
        $this->token   = config('services.moodle.token');
    }

    // ──────────────────────────────────────────────
    // USUARIOS
    // ──────────────────────────────────────────────

    /**
     * Busca un usuario en Moodle por email.
     * Devuelve el usuario o null si no existe.
     */
    public function buscarUsuarioPorEmail(string $email): ?array
    {
        $resp = $this->llamar('core_user_get_users', [
            'criteria' => [
                ['key' => 'email', 'value' => $email],
            ],
        ]);

        return $resp['users'][0] ?? null;
    }

    /**
     * Crea un usuario en Moodle.
     * Devuelve el array del usuario creado con su id.
     *
     * @throws \RuntimeException si Moodle devuelve error
     */
    public function crearUsuario(array $datos): array
    {
        $resp = $this->llamar('core_user_create_users', [
            'users' => [[
                'username'  => $this->generarUsername($datos['email']),
                'password'  => $this->generarPassword(),
                'firstname' => $datos['nombres'],
                'lastname'  => $datos['apellidos'],
                'email'     => $datos['email'],
                'auth'      => 'manual',
                'lang'      => 'es',
                // Campos de perfil personalizados (tipo_documento, numero_documento)
                'customfields' => [
                    ['type' => 'tipo_documento',   'value' => $datos['tipo_documento']],
                    ['type' => 'numero_documento',  'value' => $datos['numero_documento']],
                ],
            ]],
        ]);

        if (empty($resp[0]['id'])) {
            throw new \RuntimeException('Moodle no devolvió el ID del usuario creado');
        }

        Log::info('Moodle: usuario creado', ['moodle_user_id' => $resp[0]['id'], 'email' => $datos['email']]);

        return $resp[0];
    }

    // ──────────────────────────────────────────────
    // MATRÍCULAS
    // ──────────────────────────────────────────────

    /**
     * Matricula un usuario en un curso.
     *
     * @param int $moodleUserId  ID del usuario en Moodle
     * @param int $moodleCourseId  ID del curso en Moodle
     * @param int $roleId  5 = estudiante (por defecto)
     */
    public function matricularUsuario(
        int $moodleUserId,
        int $moodleCourseId,
        int $roleId = 5
    ): void {
        $this->llamar('enrol_manual_enrol_users', [
            'enrolments' => [[
                'roleid'   => $roleId,
                'userid'   => $moodleUserId,
                'courseid' => $moodleCourseId,
            ]],
        ]);

        Log::info('Moodle: usuario matriculado', [
            'user_id'   => $moodleUserId,
            'course_id' => $moodleCourseId,
        ]);
    }

    // ──────────────────────────────────────────────
    // COHORTES
    // ──────────────────────────────────────────────

    /**
     * Agrega un usuario a la cohorte del distribuidor.
     *
     * @param int $cohortId  ID de la cohorte en Moodle (moodle_cohort_id del distribuidor)
     * @param int $moodleUserId
     */
    public function agregarACohorte(int $cohortId, int $moodleUserId): void
    {
        $this->llamar('cohort_add_cohort_members', [
            'members' => [[
                'cohorttype' => ['type' => 'id', 'value' => (string) $cohortId],
                'usertype'   => ['type' => 'id', 'value' => (string) $moodleUserId],
            ]],
        ]);

        Log::info('Moodle: usuario agregado a cohorte', [
            'cohort_id' => $cohortId,
            'user_id'   => $moodleUserId,
        ]);
    }

    // ──────────────────────────────────────────────
    // GRUPOS
    // ──────────────────────────────────────────────

    /**
     * Agrega un usuario al grupo del distribuidor dentro del curso.
     *
     * @param int $groupId  ID del grupo en Moodle
     * @param int $moodleUserId
     */
    public function agregarAGrupo(int $groupId, int $moodleUserId): void
    {
        $this->llamar('groups_add_group_member', [
            'members' => [[
                'groupid' => $groupId,
                'userid'  => $moodleUserId,
            ]],
        ]);

        Log::info('Moodle: usuario agregado a grupo', [
            'group_id' => $groupId,
            'user_id'  => $moodleUserId,
        ]);
    }

    // ──────────────────────────────────────────────
    // PROGRESO
    // ──────────────────────────────────────────────

    /**
     * Consulta el estado de completitud de las actividades de un curso
     * para un usuario específico.
     */
    public function obtenerProgreso(int $moodleUserId, int $moodleCourseId): array
    {
        return $this->llamar('core_completion_get_activities_completion_status', [
            'courseid' => $moodleCourseId,
            'userid'   => $moodleUserId,
        ]);
    }

    // ──────────────────────────────────────────────
    // NÚCLEO HTTP
    // ──────────────────────────────────────────────

    /**
     * Ejecuta una función de la API REST de Moodle.
     *
     * @throws \RuntimeException en error de red o de Moodle
     */
    private function llamar(string $wsfunction, array $params = []): mixed
    {
        $url = "{$this->baseUrl}/webservice/rest/server.php";

        try {
            $response = Http::timeout(15)
                ->post($url, array_merge($params, [
                    'wstoken'       => $this->token,
                    'wsfunction'    => $wsfunction,
                    'moodlewsrestformat' => 'json',
                ]));

            $data = $response->json();

            // Moodle devuelve errores como { exception, errorcode, message }
            if (isset($data['exception'])) {
                Log::error("Moodle API error en {$wsfunction}", $data);
                throw new \RuntimeException(
                    "Moodle error [{$data['errorcode']}]: {$data['message']}"
                );
            }

            return $data;

        } catch (RequestException $e) {
            Log::error("Moodle HTTP error en {$wsfunction}", ['error' => $e->getMessage()]);
            throw new \RuntimeException("Error de red al comunicarse con Moodle: " . $e->getMessage());
        }
    }

    // ──────────────────────────────────────────────
    // HELPERS
    // ──────────────────────────────────────────────

    private function generarUsername(string $email): string
    {
        // Username único basado en el email, sin caracteres especiales
        return strtolower(preg_replace('/[^a-zA-Z0-9._]/', '', explode('@', $email)[0]))
            . '_' . substr(md5($email), 0, 6);
    }

    private function generarPassword(): string
    {
        // Contraseña temporal segura — Moodle la puede forzar a cambiar
        return 'Tmp_' . bin2hex(random_bytes(8)) . '!';
    }
}
