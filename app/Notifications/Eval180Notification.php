<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class Eval180Notification extends Notification
{
    use Queueable;

    private string $tipo;
    private array $dados;

    public function __construct(string $tipo, array $dados)
    {
        $this->tipo = $tipo;
        $this->dados = $dados;
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->from(config('mail.from.address'), config('mail.from.name', 'Intranet Mayer Advogados'));

        switch ($this->tipo) {
            case 'autoavaliacao_pendente':
                $mail->subject('GDP — Autoavaliação Pendente (' . $this->dados['periodo_label'] . ')')
                     ->greeting('Olá, ' . $notifiable->name . '!')
                     ->line('Uma avaliação de desempenho 180° foi criada para você no módulo GDP.')
                     ->line('**Ciclo:** ' . $this->dados['ciclo_nome'])
                     ->line('**Período:** ' . $this->dados['periodo_label'])
                     ->line('Por favor, preencha sua autoavaliação o mais breve possível.')
                     ->action('Acessar Autoavaliação', $this->dados['url'])
                     ->salutation('Intranet Mayer Advogados — Sistema RESULTADOS!');
                break;

            case 'autoavaliacao_concluida':
                $mail->subject('GDP — Autoavaliação Concluída: ' . $this->dados['avaliado_nome'])
                     ->greeting('Olá, ' . $notifiable->name . '!')
                     ->line($this->dados['avaliado_nome'] . ' concluiu a autoavaliação de desempenho 180°.')
                     ->line('**Ciclo:** ' . $this->dados['ciclo_nome'])
                     ->line('**Período:** ' . $this->dados['periodo_label'])
                     ->line('A avaliação do gestor já pode ser realizada.')
                     ->action('Avaliar Equipe', $this->dados['url'])
                     ->salutation('Intranet Mayer Advogados — Sistema RESULTADOS!');
                break;

            case 'feedback_liberado':
                $mail->subject('GDP — Resultado da Avaliação Disponível (' . $this->dados['periodo_label'] . ')')
                     ->greeting('Olá, ' . $notifiable->name . '!')
                     ->line('O resultado da sua avaliação de desempenho 180° foi liberado pelo gestor.')
                     ->line('**Ciclo:** ' . $this->dados['ciclo_nome'])
                     ->line('**Período:** ' . $this->dados['periodo_label'])
                     ->action('Ver Resultado', $this->dados['url'])
                     ->salutation('Intranet Mayer Advogados — Sistema RESULTADOS!');
                break;
        }

        return $mail;
    }

    public function toArray(object $notifiable): array
    {
        $mensagens = [
            'autoavaliacao_pendente' => 'Avaliação 180° pendente — ' . ($this->dados['periodo_label'] ?? ''),
            'autoavaliacao_concluida' => ($this->dados['avaliado_nome'] ?? '') . ' concluiu autoavaliação 180° — ' . ($this->dados['periodo_label'] ?? ''),
            'feedback_liberado' => 'Resultado da avaliação 180° liberado — ' . ($this->dados['periodo_label'] ?? ''),
        ];

        return [
            'tipo' => $this->tipo,
            'mensagem' => $mensagens[$this->tipo] ?? 'Notificação GDP',
            'url' => $this->dados['url'] ?? '/gdp',
            'periodo' => $this->dados['periodo_label'] ?? '',
            'ciclo' => $this->dados['ciclo_nome'] ?? '',
        ];
    }
}
