<?php

namespace App\Services\NexoQa;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NexoQaResolverService
{
    /**
     * Resolve o user_id responsável pelo alvo.
     *
     * Precedência:
     * (1) source_type=DATAJURI → proprietarioId mapeado para users.id
     * (2) Conversa NEXO pelo phone_hash → assigned_user_id
     * (3) null → alvo SKIPPED "sem responsável"
     */
    public function resolveResponsibleUser(string $sourceType, int $sourceId, string $phoneHash): ?int
    {
        // (1) DataJuri: buscar proprietarioId do processo/cliente
        if ($sourceType === 'DATAJURI') {
            $userId = $this->resolveFromDataJuri($sourceId);
            if ($userId !== null) {
                return $userId;
            }
        }

        // (2) NEXO: buscar conversa WhatsApp pelo phone_hash
        $userId = $this->resolveFromNexoConversation($phoneHash);
        if ($userId !== null) {
            return $userId;
        }

        // (3) Sem responsável
        return null;
    }

    /**
     * Resolve via DataJuri: busca proprietarioId nos processos do cliente.
     * proprietarioId já está mapeado para users.id no sistema.
     */
    private function resolveFromDataJuri(int $clienteDatajuriId): ?int
    {
        // Buscar proprietarioId do processo mais recente desse cliente
        $proprietarioId = DB::table('processos')
            ->where('cliente_datajuri_id', $clienteDatajuriId)
            ->whereNotNull('proprietarioId')
            ->orderByDesc('data_abertura')
            ->value('proprietarioId');

        if ($proprietarioId === null) {
            return null;
        }

        // proprietarioId já mapeia para users.id
        $exists = DB::table('users')->where('id', $proprietarioId)->exists();

        return $exists ? (int) $proprietarioId : null;
    }

    /**
     * Resolve via NEXO: buscar assigned_user_id da conversa WhatsApp.
     */
    private function resolveFromNexoConversation(string $phoneHash): ?int
    {
        // Buscar conversa pelo telefone (phone_hash ou phone direto)
        // wa_conversations armazena telefone em formato E.164
        $userId = DB::table('wa_conversations')
            ->whereNotNull('assigned_user_id')
            ->where(function ($q) use ($phoneHash) {
                // Tentar por phone diretamente — wa_conversations não tem phone_hash
                // Precisamos reverter: buscamos o phone_e164 do target e comparamos
                // MAS aqui recebemos phoneHash, não o phone_e164 original
                // Solução: usar subquery via sampled_targets
                $q->whereIn('phone', function ($sub) use ($phoneHash) {
                    $sub->select('phone_e164')
                        ->from('nexo_qa_sampled_targets')
                        ->where('phone_hash', $phoneHash)
                        ->limit(1);
                });
            })
            ->orderByDesc('updated_at')
            ->value('assigned_user_id');

        return $userId !== null ? (int) $userId : null;
    }

    /**
     * Resolve a data da última interação relevante.
     *
     * Prioridade:
     * (1) Última wa_messages outbound para esse telefone
     * (2) Último evento de ticket NEXO
     * (3) null
     */
    public function resolveLastInteractionAt(string $phoneE164): ?\Carbon\Carbon
    {
        // (1) Última mensagem outbound do escritório
        $lastWa = DB::table('wa_messages')
            ->where('direction', 'outbound')
            ->whereIn('conversation_id', function ($q) use ($phoneE164) {
                $q->select('id')
                    ->from('wa_conversations')
                    ->where('phone', $phoneE164);
            })
            ->orderByDesc('created_at')
            ->value('created_at');

        if ($lastWa !== null) {
            return \Carbon\Carbon::parse($lastWa);
        }

        // (2) Último ticket NEXO (via phone do cliente)
        $lastTicket = DB::table('nexo_tickets')
            ->where('telefone_cliente', $phoneE164)
            ->orderByDesc('updated_at')
            ->value('updated_at');

        if ($lastTicket !== null) {
            return \Carbon\Carbon::parse($lastTicket);
        }

        return null;
    }

    /**
     * Normaliza telefone para E.164 (55DDNNNNNNNNN).
     * Remove +, espaços, traços. Adiciona 55 se não tiver.
     */
    public static function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Se não começa com 55 e tem 10-11 dígitos (DDD+número), adiciona 55
        if (!str_starts_with($phone, '55') && strlen($phone) >= 10 && strlen($phone) <= 11) {
            $phone = '55' . $phone;
        }

        return $phone;
    }
}
