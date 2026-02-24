<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class GdpAcordoPendente extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $advogado,
        public string $cicloNome,
        public string $linkAceite,
        public bool $isLembrete = false
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->isLembrete
            ? "[PENDENTE] Acordo de Desempenho {$this->cicloNome} aguardando sua assinatura"
            : "Acordo de Desempenho {$this->cicloNome} disponível para assinatura";

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.gdp-acordo-pendente');
    }
}
