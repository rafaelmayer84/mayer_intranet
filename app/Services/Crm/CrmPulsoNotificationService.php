<?php

namespace App\Services\Crm;

use App\Models\Crm\CrmPulsoAlerta;
use App\Models\NotificationIntranet;
use Illuminate\Support\Facades\Log;

class CrmPulsoNotificationService
{
    // Patricia=3, Rafael=1
    const COORDENACAO_USER_ID = 3;
    const ADMIN_USER_ID = 1;

    /**
     * Notifica coordenação sobre alerta Pulso.
     * Se alerta muito grave (>2x threshold), notifica admin também.
     */
    public function notificarCoordenacao(CrmPulsoAlerta $alerta): void
    {
        $link = "/crm/pulso/alertas";
        $titulo = $this->tituloAlerta($alerta);
        $mensagem = $alerta->descricao;

        // Notificar coordenação
        NotificationIntranet::enviar(
            self::COORDENACAO_USER_ID,
            $titulo,
            $mensagem,
            $link,
            'pulso',
            'activity'
        );

        $alerta->update(['notificado_em' => now()]);

        // Se diário excedido com >2x threshold, notificar admin
        if ($alerta->tipo === 'diario_excedido') {
            $dados = $alerta->dados_json;
            $threshold = $dados['threshold'] ?? 5;
            $total = $dados['total'] ?? 0;

            if ($total > $threshold * 2) {
                NotificationIntranet::enviar(
                    self::ADMIN_USER_ID,
                    "[CRÍTICO] {$titulo}",
                    $mensagem,
                    $link,
                    'pulso',
                    'alert-triangle'
                );
            }
        }

        Log::info("[Pulso] Notificação enviada para alerta #{$alerta->id} ({$alerta->tipo})");
    }

    protected function tituloAlerta(CrmPulsoAlerta $alerta): string
    {
        return match ($alerta->tipo) {
            'diario_excedido'  => 'Pulso: Contatos excessivos (dia)',
            'semanal_excedido' => 'Pulso: Contatos excessivos (semana)',
            'reiteracao'       => 'Pulso: Reiteração de demanda',
            'fora_horario'     => 'Pulso: Contatos fora do horário',
            default            => 'Pulso: Alerta de atendimento',
        };
    }
}
