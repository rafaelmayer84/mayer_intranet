<?php

namespace App\Services\Crm;

use App\Models\Crm\CrmAdminProcess;
use App\Models\Crm\CrmAdminProcessAto;
use App\Models\Crm\CrmAdminProcessStep;
use App\Models\Crm\CrmAdminProcessTimeline;
use App\Models\NotificationIntranet;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Centraliza todos os avisos proativos do módulo de processos administrativos.
 *
 * Tipos de notificação:
 *  - Tramitação recebida
 *  - Mudança de status
 *  - Novo ato registrado (para o advogado com o processo)
 *  - Prazo se aproximando (7d, 3d, 1d) — via comando diário
 *  - Prazo vencido
 *  - Inatividade (sem movimentação em X dias)
 *  - Etapa atrasada (deadline_at ultrapassado)
 */
class CrmAdminProcessAlertService
{
    // ── Notificações em tempo real (chamadas pelo controller) ──────────────────

    /**
     * Notifica o destinatário quando um processo é tramitado para ele.
     */
    public function notificarTramitacao(
        CrmAdminProcess $processo,
        User $de,
        User $para,
        ?string $despacho = null
    ): void {
        $link = route('crm.admin-processes.show', $processo->id);
        $msg  = "O processo {$processo->protocolo} ({$processo->titulo}) foi encaminhado para você por {$de->name}.";
        if ($despacho) $msg .= "\n\nDespacho: {$despacho}";

        NotificationIntranet::enviar(
            userId:  $para->id,
            titulo:  "Processo recebido: {$processo->protocolo}",
            mensagem: $msg,
            link:    $link,
            tipo:    'info',
            icone:   'inbox',
        );
    }

    /**
     * Notifica owner e com_user quando o status muda (exceto quem fez a ação).
     */
    public function notificarMudancaStatus(
        CrmAdminProcess $processo,
        string $statusAnterior,
        int $feitorPorId
    ): void {
        $label = (new CrmAdminProcess)->fill(['status' => $processo->status])->statusLabel();
        $link  = route('crm.admin-processes.show', $processo->id);
        $msg   = "O status do processo {$processo->protocolo} foi alterado para \"{$label}\".";

        $destinatarios = collect([
            $processo->owner_user_id,
            $processo->com_user_id,
        ])->filter()->unique()->reject(fn($id) => $id === $feitorPorId);

        foreach ($destinatarios as $userId) {
            NotificationIntranet::enviar(
                userId:   $userId,
                titulo:   "Status atualizado: {$processo->protocolo}",
                mensagem: $msg,
                link:     $link,
                tipo:     in_array($processo->status, ['suspenso','cancelado']) ? 'warning' : 'info',
                icone:    'refresh',
            );
        }
    }

    /**
     * Notifica o advogado com o processo quando um novo ato é registrado por outro.
     */
    public function notificarNovoAto(
        CrmAdminProcess $processo,
        string $tipoAto,
        int $feitorPorId
    ): void {
        if (!$processo->com_user_id || $processo->com_user_id === $feitorPorId) {
            return;
        }

        $autor = User::find($feitorPorId);
        $link  = route('crm.admin-processes.show', $processo->id);

        NotificationIntranet::enviar(
            userId:   $processo->com_user_id,
            titulo:   "Novo ato em {$processo->protocolo}",
            mensagem: "Um novo ato foi registrado ({$tipoAto}) no processo {$processo->titulo} por {$autor?->name}.",
            link:     $link,
            tipo:     'info',
            icone:    'document',
        );
    }

    /**
     * Notifica o advogado responsável quando uma etapa é concluída.
     */
    public function notificarEtapaConcluida(
        CrmAdminProcess $processo,
        CrmAdminProcessStep $step,
        int $feitorPorId
    ): void {
        $destinatarios = collect([
            $processo->owner_user_id,
            $processo->com_user_id,
        ])->filter()->unique()->reject(fn($id) => $id === $feitorPorId);

        $link = route('crm.admin-processes.show', $processo->id);

        foreach ($destinatarios as $userId) {
            NotificationIntranet::enviar(
                userId:   $userId,
                titulo:   "Etapa concluída: {$processo->protocolo}",
                mensagem: "A etapa \"{$step->titulo}\" foi concluída no processo {$processo->titulo}.",
                link:     $link,
                tipo:     'success',
                icone:    'check',
            );
        }
    }

    // ── Verificações periódicas (chamadas pelo comando diário) ─────────────────

    /**
     * Verifica prazos finais e emite alertas em 7, 3 e 1 dia(s).
     */
    public function verificarPrazos(): int
    {
        $count = 0;
        $hoje  = Carbon::today();

        $processos = CrmAdminProcess::whereNotIn('status', ['concluido', 'cancelado'])
            ->whereNotNull('prazo_final')
            ->with(['owner'])
            ->get();

        foreach ($processos as $processo) {
            $diasRestantes = $hoje->diffInDays($processo->prazo_final, false);

            if ($diasRestantes < 0) {
                // Prazo vencido — 1 aviso por dia enquanto ativo
                $count += $this->emitirAlertaPrazo($processo, $diasRestantes);
            } elseif (in_array($diasRestantes, [1, 3, 7])) {
                $count += $this->emitirAlertaPrazo($processo, $diasRestantes);
            }
        }

        return $count;
    }

    /**
     * Verifica etapas com deadline_at vencido.
     */
    public function verificarEtapasAtrasadas(): int
    {
        $count = 0;
        $hoje  = Carbon::today()->toDateString();

        $steps = CrmAdminProcessStep::whereNotIn('status', ['concluido', 'nao_aplicavel'])
            ->whereNotNull('deadline_at')
            ->where('deadline_at', '<', $hoje)
            ->with(['process.owner'])
            ->get();

        foreach ($steps as $step) {
            $processo = $step->process ?? null;
            if (!$processo || in_array($processo->status, ['concluido', 'cancelado', 'suspenso'])) {
                continue;
            }

            $chave = "admin_process_step_atrasada_{$step->id}_" . date('Y-m-d');
            if ($this->jaNotificouHoje($chave)) continue;

            $this->marcarNotificado($chave);

            $link = route('crm.admin-processes.show', $processo->id);
            $msg  = "A etapa \"{$step->titulo}\" do processo {$processo->protocolo} ({$processo->titulo}) está atrasada. Prazo era: " . Carbon::parse($step->deadline_at)->format('d/m/Y') . ".";

            foreach ($this->destinatariosProcesso($processo) as $userId) {
                NotificationIntranet::enviar(
                    userId:   $userId,
                    titulo:   "Etapa atrasada: {$processo->protocolo}",
                    mensagem: $msg,
                    link:     $link,
                    tipo:     'warning',
                    icone:    'clock',
                );
                $count++;
            }
        }

        return $count;
    }

    /**
     * Verifica processos sem movimentação há muitos dias (inatividade).
     *
     * - Em andamento / Aberto: alerta após 14 dias sem timeline
     * - Aguardando cliente/terceiro: alerta após 21 dias
     */
    public function verificarInatividade(): int
    {
        $count = 0;

        $limites = [
            'em_andamento'         => 14,
            'aberto'               => 14,
            'aguardando_cliente'   => 21,
            'aguardando_terceiro'  => 21,
        ];

        foreach ($limites as $status => $dias) {
            $processos = CrmAdminProcess::where('status', $status)
                ->with(['owner'])
                ->get();

            foreach ($processos as $processo) {
                $ultimaAtividade = CrmAdminProcessTimeline::where('admin_process_id', $processo->id)
                    ->max('happened_at');

                $referencia = $ultimaAtividade
                    ? Carbon::parse($ultimaAtividade)
                    : $processo->created_at;

                $diasSemAtividade = (int) $referencia->diffInDays(now());

                if ($diasSemAtividade < $dias) continue;

                $chave = "admin_process_inativo_{$processo->id}_" . date('Y-m-d');
                if ($this->jaNotificouHoje($chave)) continue;

                $this->marcarNotificado($chave);

                $link = route('crm.admin-processes.show', $processo->id);
                $msg  = "O processo {$processo->protocolo} ({$processo->titulo}) está sem movimentação há {$diasSemAtividade} dias. Status atual: {$processo->statusLabel()}.";

                foreach ($this->destinatariosProcesso($processo) as $userId) {
                    NotificationIntranet::enviar(
                        userId:   $userId,
                        titulo:   "Processo inativo: {$processo->protocolo}",
                        mensagem: $msg,
                        link:     $link,
                        tipo:     'warning',
                        icone:    'bell',
                    );
                    $count++;
                }
            }
        }

        return $count;
    }

    // ── Helpers privados ───────────────────────────────────────────────────────

    private function emitirAlertaPrazo(CrmAdminProcess $processo, int $dias): int
    {
        $chave = "admin_process_prazo_{$processo->id}_{$dias}_" . date('Y-m-d');
        if ($this->jaNotificouHoje($chave)) return 0;

        $this->marcarNotificado($chave);

        $link = route('crm.admin-processes.show', $processo->id);

        if ($dias < 0) {
            $titulo = "Prazo vencido: {$processo->protocolo}";
            $msg    = "O processo {$processo->titulo} teve seu prazo vencido há " . abs($dias) . " dia(s) (era {$processo->prazo_final->format('d/m/Y')}).";
            $tipo   = 'error';
        } elseif ($dias === 0) {
            $titulo = "Prazo hoje: {$processo->protocolo}";
            $msg    = "O prazo do processo {$processo->titulo} vence HOJE ({$processo->prazo_final->format('d/m/Y')}).";
            $tipo   = 'error';
        } else {
            $titulo = "Prazo em {$dias} dia(s): {$processo->protocolo}";
            $msg    = "O prazo do processo {$processo->titulo} vence em {$dias} dia(s) ({$processo->prazo_final->format('d/m/Y')}).";
            $tipo   = $dias <= 3 ? 'warning' : 'info';
        }

        $count = 0;
        foreach ($this->destinatariosProcesso($processo) as $userId) {
            NotificationIntranet::enviar(
                userId:   $userId,
                titulo:   $titulo,
                mensagem: $msg,
                link:     $link,
                tipo:     $tipo,
                icone:    'calendar',
            );
            $count++;
        }

        return $count;
    }

    private function destinatariosProcesso(CrmAdminProcess $processo): array
    {
        return collect([
            $processo->owner_user_id,
            $processo->com_user_id,
        ])->filter()->unique()->values()->all();
    }

    private function jaNotificouHoje(string $chave): bool
    {
        return cache()->has("notif:{$chave}");
    }

    private function marcarNotificado(string $chave): void
    {
        // TTL 25h — garante que não dispara duas vezes no mesmo dia mas reseta no próximo
        cache()->put("notif:{$chave}", true, now()->addHours(25));
    }
}
