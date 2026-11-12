<?php
// app/Mail/LiquidacionGenerada.php

namespace App\Mail;

use App\Models\Liquidacion;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * LiquidacionGenerada
 *
 * Correo enviado a la IETDH al cerrar el mes con:
 *  - Resumen de certificados emitidos
 *  - Valor total a facturar
 *  - Instrucciones para emitir la factura
 *  - PDF del documento de liquidación adjunto
 */
class LiquidacionGenerada extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Liquidacion $liquidacion,
        public readonly array       $detalle,
        public readonly string      $rutaPdf,
    ) {}

    public function envelope(): Envelope
    {
        $mes   = str_pad($this->liquidacion->periodo_mes, 2, '0', STR_PAD_LEFT);
        $anio  = $this->liquidacion->periodo_anio;
        $ietdh = $this->liquidacion->ietdh->nombre;

        return new Envelope(
            to:      [$this->liquidacion->ietdh->email_contacto],
            subject: "Liquidación de certificados {$mes}/{$anio} — {$ietdh}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.liquidaciones.correo',
            with: [
                'liquidacion' => $this->liquidacion,
                'ietdh'       => $this->liquidacion->ietdh,
                'resumen'     => $this->detalle['resumen'],
                'certificados'=> $this->detalle['certificados'],
            ],
        );
    }

    public function attachments(): array
    {
        $mes      = str_pad($this->liquidacion->periodo_mes, 2, '0', STR_PAD_LEFT);
        $anio     = $this->liquidacion->periodo_anio;
        $filename = "Liquidacion_{$mes}_{$anio}_{$this->liquidacion->ietdh->nit}.pdf";

        return [
            Attachment::fromStorageDisk('public', $this->rutaPdf)
                ->as($filename)
                ->withMime('application/pdf'),
        ];
    }
}
