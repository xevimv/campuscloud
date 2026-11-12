<?php

namespace App\Services;

use App\Models\Liquidacion;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

/**
 * PdfLiquidacionService
 *
 * Genera el PDF del documento de liquidación mensual
 * usando DomPDF y la vista resources/views/liquidaciones/documento.blade.php
 *
 * El PDF se guarda en: storage/app/public/liquidaciones/{liquidacion_id}.pdf
 */
class PdfLiquidacionService
{
    public function generar(Liquidacion $liquidacion, array $detalle): string
    {
        $pdf = Pdf::loadView('liquidaciones.documento', [
            'liquidacion'  => $liquidacion,
            'ietdh'        => $detalle['ietdh'],
            'certificados' => $detalle['certificados'],
            'resumen'      => $detalle['resumen'],
        ])->setPaper('letter', 'portrait')
          ->setOptions([
              'defaultFont'         => 'Arial',
              'isHtml5ParserEnabled'=> true,
              'dpi'                 => 120,
          ]);

        $ruta = "liquidaciones/{$liquidacion->id}.pdf";
        Storage::disk('public')->put($ruta, $pdf->output());

        Log::info('PDF liquidación generado', [
            'liquidacion_id' => $liquidacion->id,
            'ruta'           => $ruta,
        ]);

        return $ruta;
    }

    public function contenido(Liquidacion $liquidacion): ?string
    {
        $ruta = "liquidaciones/{$liquidacion->id}.pdf";
        return Storage::disk('public')->exists($ruta)
            ? Storage::disk('public')->get($ruta)
            : null;
    }
}
