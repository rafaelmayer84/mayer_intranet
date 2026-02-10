<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BoasVindasUsuario extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $senhaTemporaria
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Bem-vindo(a) à Intranet Mayer Advogados - Seu acesso foi criado!',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.boas-vindas',
        );
    }
}
