<?php
// AGREGAR al CertificadoController (app/Http/Controllers/Api/CertificadoController.php)

// ── Descarga del PDF ──────────────────────────────────────────────

/**
 * GET /api/certificados/{certificado}/pdf
 * Descarga el PDF del certificado.
 *
 * Accesible por:
 *  - NOMADDI_ADMIN: cualquier certificado
 *  - DIST_ADMIN: certificados de sus estudiantes
 *  - El propio estudiante (autenticado con token)
 *
 * Si el PDF no existe aún (raro — el Job puede estar en cola),
 * lo genera en el momento.
 */
public function descargarPdf(
    Certificado $certificado,
    PdfCertificadoService $pdfService
): \Symfony\Component\HttpFoundation\Response {
    $certificado->loadMissing(['acta', 'ietdh', 'matricula.estudiante', 'matricula.curso']);

    // Intentar obtener el PDF existente
    $contenido = $pdfService->contenido($certificado);

    // Si no existe, generarlo ahora
    if (!$contenido) {
        $ruta      = $pdfService->generar($certificado);
        $contenido = \Illuminate\Support\Facades\Storage::disk('public')->get($ruta);
    }

    $apellidos = $certificado->matricula->estudiante->apellidos;
    $filename  = "Certificado_{$apellidos}_{$certificado->acta->codigo_acta}-{$certificado->folio}.pdf";

    return response($contenido, 200, [
        'Content-Type'        => 'application/pdf',
        'Content-Disposition' => "attachment; filename=\"{$filename}\"",
    ]);
}
