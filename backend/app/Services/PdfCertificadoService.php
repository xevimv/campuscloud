<?php

namespace App\Services;

use App\Models\Certificado;
use Barryvdh\DomPDF\Facade\Pdf;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * PdfCertificadoService
 *
 * Genera el PDF del certificado usando DomPDF y la plantilla Blade
 * resources/views/certificados/plantilla.blade.php
 *
 * Flujo:
 *  1. Genera el QR del UUID de verificación (base64 PNG)
 *  2. Carga el logo de la IETDH desde storage (base64)
 *  3. Renderiza la vista Blade con todos los datos
 *  4. Genera el PDF con DomPDF en orientación horizontal (landscape)
 *  5. Guarda el PDF en storage/app/public/certificados/{uuid}.pdf
 *  6. Devuelve la ruta del archivo guardado
 *
 * Dependencias:
 *   composer require barryvdh/laravel-dompdf
 *   composer require endroid/qr-code
 */
class PdfCertificadoService
{
    // Tipos de documento para mostrar en el PDF
    private array $tiposDocumento = [
        'RC'  => 'Registro Civil',
        'TI'  => 'Tarjeta de Identidad',
        'CC'  => 'Cédula de Ciudadanía',
        'CE'  => 'Cédula de Extranjería',
        'PA'  => 'Pasaporte',
        'PPT' => 'Permiso por Protección Temporal',
    ];

    // ──────────────────────────────────────────────────────────────
    // GENERAR Y GUARDAR PDF
    // ──────────────────────────────────────────────────────────────

    /**
     * Genera el PDF del certificado y lo guarda en storage.
     * Devuelve la ruta del archivo guardado.
     *
     * @param Certificado $certificado  Con relaciones cargadas:
     *                                  acta, ietdh, matricula.estudiante, matricula.curso
     * @return string  Ruta en storage (ej: certificados/uuid.pdf)
     */
    public function generar(Certificado $certificado): string
    {
        $certificado->loadMissing([
            'acta',
            'ietdh',
            'matricula.estudiante',
            'matricula.curso',
        ]);

        $matricula  = $certificado->matricula;
        $estudiante = $matricula->estudiante;
        $curso      = $matricula->curso;
        $ietdh      = $certificado->ietdh;

        // ── 1. Generar QR del UUID ────────────────────────────────
        $urlVerificacion = config('app.url') . "/verificar/{$certificado->uuid_verificacion}";
        $qrBase64        = $this->generarQrBase64($urlVerificacion);

        // ── 2. Logo de la IETDH en base64 ────────────────────────
        $logoIetdh = $this->cargarLogoBase64($ietdh->id);

        // ── 3. Datos para la vista ────────────────────────────────
        $datos = [
            'certificado'     => $certificado,
            'matricula'       => $matricula,
            'estudiante'      => $estudiante,
            'curso'           => $curso,
            'ietdh'           => $ietdh,
            'codigoActa'      => $certificado->acta->codigo_acta,
            'folio'           => $certificado->folio,
            'uuidVerificacion'=> $certificado->uuid_verificacion,
            'urlVerificacion' => $urlVerificacion,
            'tipoPrograma'    => $certificado->tipo_programa,
            'fechaEmision'    => $certificado->fecha_emision->format('d \d\e F \d\e Y'),
            'qrBase64'        => $qrBase64,
            'logoIetdh'       => $logoIetdh,
            'tiposDocumento'  => $this->tiposDocumento,
        ];

        // ── 4. Renderizar PDF ─────────────────────────────────────
        $pdf = Pdf::loadView('certificados.plantilla', $datos)
            ->setPaper('letter', 'landscape')
            ->setOptions([
                'defaultFont'    => 'DejaVu Serif',
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled'      => false, // no cargar URLs externas
                'dpi'            => 150,
            ]);

        // ── 5. Guardar en storage ─────────────────────────────────
        $ruta = "certificados/{$certificado->uuid_verificacion}.pdf";
        Storage::disk('public')->put($ruta, $pdf->output());

        Log::info('PDF certificado generado', [
            'certificado_id' => $certificado->id,
            'ruta'           => $ruta,
        ]);

        return $ruta;
    }

    /**
     * Devuelve la URL pública del PDF de un certificado.
     * Devuelve null si el archivo no existe aún.
     */
    public function urlPdf(Certificado $certificado): ?string
    {
        $ruta = "certificados/{$certificado->uuid_verificacion}.pdf";

        if (!Storage::disk('public')->exists($ruta)) {
            return null;
        }

        return Storage::disk('public')->url($ruta);
    }

    /**
     * Devuelve el contenido binario del PDF para descarga directa.
     */
    public function contenido(Certificado $certificado): ?string
    {
        $ruta = "certificados/{$certificado->uuid_verificacion}.pdf";

        if (!Storage::disk('public')->exists($ruta)) {
            return null;
        }

        return Storage::disk('public')->get($ruta);
    }

    // ──────────────────────────────────────────────────────────────
    // HELPERS PRIVADOS
    // ──────────────────────────────────────────────────────────────

    /**
     * Genera un QR de la URL de verificación y lo devuelve en base64 PNG.
     */
    private function generarQrBase64(string $url): string
    {
        $result = Builder::create()
            ->writer(new PngWriter())
            ->data($url)
            ->encoding(new Encoding('UTF-8'))
            ->size(200)
            ->margin(10)
            ->build();

        return 'data:image/png;base64,' . base64_encode($result->getString());
    }

    /**
     * Carga el logo de la IETDH desde storage y lo convierte a base64.
     * Busca: storage/app/public/logos-ietdh/{ietdh_id}.png
     *        storage/app/public/logos-ietdh/{ietdh_id}.jpg
     * Si no existe, devuelve null (la plantilla maneja el caso sin logo).
     */
    private function cargarLogoBase64(string $ietdhId): ?string
    {
        foreach (['png', 'jpg', 'jpeg', 'webp'] as $ext) {
            $ruta = "logos-ietdh/{$ietdhId}.{$ext}";
            if (Storage::disk('public')->exists($ruta)) {
                $contenido = Storage::disk('public')->get($ruta);
                $mime      = "image/{$ext}";
                return "data:{$mime};base64," . base64_encode($contenido);
            }
        }
        return null;
    }
}
