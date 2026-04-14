<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NexoTicketAtribuido extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param User   $responsavel
     * @param array  $dados  ['protocolo', 'assunto', 'nome_cliente', 'telefone', 'tipo', 'prioridade', 'mensagem', 'link']
     */
    public function __construct(
        public User $responsavel,
        public array $dados
    ) {}

    public function envelope(): Envelope
    {
        $protocolo = $this->dados['protocolo'] ?? 'novo ticket';
        $urgente   = ($this->dados['prioridade'] ?? 'normal') === 'urgente';

        return new Envelope(
            subject: ($urgente ? '[URGENTE] ' : '') . "Novo ticket atribuído a você — {$protocolo}"
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.nexo-ticket-atribuido');
    }
}
