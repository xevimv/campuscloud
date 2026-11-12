<?php
// MÉTODOS ADICIONALES para agregar a app/Services/MoodleService.php

// ──────────────────────────────────────────────────────────────────
// CAMPOS DE CERTIFICADO
// ──────────────────────────────────────────────────────────────────

/**
 * Actualiza los campos de perfil del usuario en Moodle
 * con los datos del certificado emitido.
 *
 * El Custom Certificate de Moodle tiene elementos de tipo
 * "Campo de usuario" que leen estos valores dinámicamente
 * al generar el PDF.
 *
 * Prerequisitos en Moodle — crear campos de perfil (shortnames):
 *   cert_acta   → texto corto  (ej: T2604)
 *   cert_folio  → texto corto  (ej: 15)
 *   cert_uuid   → texto largo  (UUID de verificación)
 *   cert_ietdh  → texto largo  (nombre de la IETDH)
 *   cert_fecha  → texto corto  (ej: 12/04/2026)
 *
 * @param int   $moodleUserId
 * @param array $campos  ['cert_acta' => 'T2604', 'cert_folio' => '15', ...]
 */
public function actualizarCamposCertificado(int $moodleUserId, array $campos): void
{
    $customfields = array_map(
        fn($tipo, $valor) => ['type' => $tipo, 'value' => $valor],
        array_keys($campos),
        array_values($campos)
    );

    $this->llamar('core_user_update_users', [
        'users' => [[
            'id'           => $moodleUserId,
            'customfields' => $customfields,
        ]],
    ]);

    Log::info('Moodle: campos de certificado actualizados', [
        'moodle_user_id' => $moodleUserId,
        'campos'         => array_keys($campos),
    ]);
}

// También agregar al webservice token en Moodle si no está ya:
//   - core_user_update_users
