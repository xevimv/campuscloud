<?php
// app/Mail/ReporteSemanal.php

namespace App\Mail;

use App\Models\Distribuidor;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReporteSemanal extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Distribuidor $distribuidor,
        public readonly array        $datos,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "📊 Tu reporte semanal — Semana {$this->datos['semana_numero']} / {$this->datos['anio']}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.reportes.semanal',
            with: ['datos' => $this->datos],
        );
    }
}
