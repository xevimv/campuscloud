<?php
// app/Mail/ReporteMensual.php

namespace App\Mail;

use App\Models\Distribuidor;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReporteMensual extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Distribuidor $distribuidor,
        public readonly array        $datos,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "📈 Tu reporte mensual — {$this->datos['periodo']}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.reportes.mensual',
            with: ['datos' => $this->datos],
        );
    }
}
