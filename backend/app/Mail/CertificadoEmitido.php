<?php

namespace App\Mail;

use App\Models\Certificado;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

/**
 * CertificadoEmitido
 *
 * Correo enviado automáticamente al estudiante cuando
 * el panel emite su certificado.
 *
 * Contiene:
 *  - Mensaje personalizado con nombre del estudiante y programa
 *  - URL de verificación pública del certificado
 *  - PDF del certificado como adjunto
 *
 * Se encola en la cola 'emails' para no bloquear
 * el proceso de emisión del certificado.
 */
class CertificadoEmitido extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Certificado $certificado,
        public readonly string      $rutaPdf,
    ) {}

    public function envelope(): Envelope
    {
        $estudiante = $this->certificado->matricula->estudiante;
        $ietdh      = $this->certificado->ietdh;

        return new Envelope(
            subject: "Tu certificado está listo — {$ietdh->nombre}",
            replyTo: $ietdh->email_contacto,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.certificado-emitido',
        );
    }

    public function attachments(): array
    {
        $uuid     = $this->certificado->uuid_verificacion;
        $nombre   = $this->certificado->matricula->estudiante->apellidos;
        $filename = "Certificado_{$nombre}_{$this->certificado->acta->codigo_acta}-{$this->certificado->folio}.pdf";

        return [
            Attachment::fromStorageDisk('public', $this->rutaPdf)
                ->as($filename)
                ->withMime('application/pdf'),
        ];
    }
}
