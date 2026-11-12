<?php
// PARCHE para app/Services/CertificadoService.php
// Integrar NotificacionCertificadoService al flujo de emisión

// ── 1. Agregar al constructor ─────────────────────────────────────
// Cambiar de:
/*
public function __construct(
    private MoodleService $moodle,
    private IetdhService  $ietdhService,
) {}
*/

// A:
/*
public function __construct(
    private MoodleService                 $moodle,
    private IetdhService                  $ietdhService,
    private NotificacionCertificadoService $notificacion,
) {}
*/

// ── 2. Al final del método emitir(), reemplazar el bloque ─────────
// Eliminar la llamada directa a enviarDatosACertificadoMoodle()
// y reemplazar con NotificacionCertificadoService::notificar()

// ANTES (en CertificadoService::emitir()):
/*
    // ── 5. Enviar UUID a Moodle (Custom Certificate) ──────────
    $this->enviarDatosACertificadoMoodle($certificado, $matricula, $ietdh);

    // ── 6. Registrar devengo del fee de IETDH ─────────────────
    $this->registrarDevengoIetdh($certificado, $ietdh, $distribuidor);

    return $certificado->fresh(['acta', 'ietdh']);
*/

// DESPUÉS:
/*
    // ── 5. Generar PDF, enviar correo y actualizar Moodle ─────
    $this->notificacion->notificar($certificado);

    // ── 6. Registrar devengo del fee de IETDH ─────────────────
    $this->registrarDevengoIetdh($certificado, $ietdh, $distribuidor);

    return $certificado->fresh(['acta', 'ietdh']);
*/

// ── 3. Eliminar el método enviarDatosACertificadoMoodle() ─────────
// Ya no es necesario — NotificacionCertificadoService lo hace.
