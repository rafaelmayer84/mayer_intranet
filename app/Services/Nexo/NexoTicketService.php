<?php

namespace App\Services\Nexo;

use App\Mail\NexoTicketAtribuido;
use App\Models\Cliente;
use App\Models\Crm\CrmAccount;
use App\Models\NotificationIntranet;
use App\Models\NexoTicket;
use App\Models\NexoTicketNota;
use App\Services\SendPulseWhatsAppService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NexoTicketService
{
    private SendPulseWhatsAppService $sendPulse;

    public function __construct(SendPulseWhatsAppService $sendPulse)
    {
        $this->sendPulse = $sendPulse;
    }

    public function listar(array $filtros = [], int $perPage = 20)
    {
        $query = NexoTicket::with(['responsavel', 'cliente'])
            ->withCount('notas');

        if (!empty($filtros['status'])) {
            if ($filtros['status'] === 'ativos') {
                $query->whereIn('status', ['aberto', 'em_andamento']);
            } else {
                $query->where('status', $filtros['status']);
            }
        }

        if (!empty($filtros['tipo'])) {
            $query->where('tipo', $filtros['tipo']);
        }

        if (!empty($filtros['prioridade'])) {
            $query->where('prioridade', $filtros['prioridade']);
        }

        if (!empty($filtros['responsavel_id'])) {
            if ($filtros['responsavel_id'] === 'sem') {
                $query->whereNull('responsavel_id');
            } else {
                $query->where('responsavel_id', (int) $filtros['responsavel_id']);
            }
        }

        if (!empty($filtros['busca'])) {
            $termo = $filtros['busca'];
            $query->where(function ($q) use ($termo) {
                $q->where('nome_cliente', 'LIKE', "%{$termo}%")
                  ->orWhere('assunto', 'LIKE', "%{$termo}%")
                  ->orWhere('protocolo', 'LIKE', "%{$termo}%")
                  ->orWhere('telefone', 'LIKE', "%{$termo}%");
            });
        }

        if (!empty($filtros['data_inicio'])) {
            $query->whereDate('created_at', '>=', $filtros['data_inicio']);
        }
        if (!empty($filtros['data_fim'])) {
            $query->whereDate('created_at', '<=', $filtros['data_fim']);
        }

        return $query->orderByRaw("FIELD(status, 'aberto', 'em_andamento', 'concluido', 'cancelado')")
                     ->orderByRaw("FIELD(prioridade, 'urgente', 'normal')")
                     ->orderByDesc('created_at')
                     ->paginate($perPage)
                     ->appends($filtros);
    }

    public function getKpis(): array
    {
        $abertos = NexoTicket::where('status', 'aberto')->count();
        $emAndamento = NexoTicket::where('status', 'em_andamento')->count();
        $concluidos = NexoTicket::where('status', 'concluido')->count();
        $urgentes = NexoTicket::whereIn('status', ['aberto', 'em_andamento'])
            ->where('prioridade', 'urgente')->count();

        $slaViolados = NexoTicket::whereIn('status', ['aberto', 'em_andamento'])
            ->where('created_at', '<', now()->subHours(24))
            ->count();

        $semResponsavel = NexoTicket::whereIn('status', ['aberto', 'em_andamento'])
            ->whereNull('responsavel_id')
            ->count();

        $tempoMedioHoras = NexoTicket::where('status', 'concluido')
            ->whereNotNull('resolvido_at')
            ->where('resolvido_at', '>=', now()->subDays(30))
            ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, created_at, resolvido_at)) as media')
            ->value('media');

        $porTipo = NexoTicket::whereIn('status', ['aberto', 'em_andamento'])
            ->selectRaw('tipo, COUNT(*) as total')
            ->groupBy('tipo')
            ->pluck('total', 'tipo')
            ->toArray();

        return [
            'abertos' => $abertos,
            'em_andamento' => $emAndamento,
            'concluidos_30d' => $concluidos,
            'urgentes' => $urgentes,
            'sla_violados' => $slaViolados,
            'sem_responsavel' => $semResponsavel,
            'tempo_medio_horas' => $tempoMedioHoras ? round((float) $tempoMedioHoras, 1) : null,
            'por_tipo' => $porTipo,
            'total_ativos' => $abertos + $emAndamento,
        ];
    }

    public function detalhe(int $id): ?NexoTicket
    {
        return NexoTicket::with(['responsavel', 'cliente', 'notas.user'])
            ->find($id);
    }

    public function atribuirResponsavel(int $ticketId, ?int $userId, ?int $executadoPor = null): NexoTicket
    {
        $ticket = NexoTicket::findOrFail($ticketId);
        $antigoId = $ticket->responsavel_id;
        $ticket->responsavel_id = $userId;

        if ($userId && $ticket->status === 'aberto') {
            $ticket->status = 'em_andamento';
        }

        $ticket->save();

        // Registrar transferencia como nota
        if ($executadoPor && $antigoId !== $userId) {
            $novoNome = $userId ? (\App\Models\User::find($userId)?->name ?? 'Desconhecido') : 'Ninguem';
            $antigoNome = $antigoId ? (\App\Models\User::find($antigoId)?->name ?? 'Desconhecido') : 'Ninguem';
            NexoTicketNota::create([
                'ticket_id' => $ticketId,
                'user_id' => $executadoPor,
                'texto' => "Responsabilidade transferida de {$antigoNome} para {$novoNome}",
                'tipo' => 'transferencia',
                'notificou_cliente' => false,
            ]);
        }

        Log::info('NEXO-TICKETS: Responsavel atribuido', [
            'ticket_id' => $ticketId,
            'responsavel_id' => $userId,
            'status' => $ticket->status,
        ]);

        return $ticket;
    }

    public function mudarStatus(int $ticketId, string $novoStatus): NexoTicket
    {
        $ticket = NexoTicket::findOrFail($ticketId);
        $statusAntigo = $ticket->status;
        $ticket->status = $novoStatus;

        if ($novoStatus === 'concluido') {
            $ticket->resolvido_at = now();
        } elseif (in_array($novoStatus, ['aberto', 'em_andamento'])) {
            $ticket->resolvido_at = null;
        }

        $ticket->save();

        Log::info('NEXO-TICKETS: Status alterado', [
            'ticket_id' => $ticketId,
            'de' => $statusAntigo,
            'para' => $novoStatus,
        ]);

        return $ticket;
    }

    public function adicionarNota(int $ticketId, int $userId, string $texto, bool $notificarCliente = false): NexoTicketNota
    {
        $ticket = NexoTicket::findOrFail($ticketId);

        $nota = NexoTicketNota::create([
            'ticket_id' => $ticketId,
            'user_id' => $userId,
            'texto' => $texto,
            'notificou_cliente' => $notificarCliente,
        ]);

        if ($notificarCliente && $ticket->telefone) {
            $this->notificarClienteWhatsApp($ticket, $texto);
        }

        Log::info('NEXO-TICKETS: Nota adicionada', [
            'ticket_id' => $ticketId,
            'user_id' => $userId,
            'notificou_cliente' => $notificarCliente,
        ]);

        return $nota;
    }


    public function resolver(int $ticketId, int $userId, string $resolucao): NexoTicket
    {
        $ticket = NexoTicket::findOrFail($ticketId);
        $ticket->resolucao = $resolucao;
        $ticket->status = 'concluido';
        $ticket->resolvido_at = now();
        $ticket->save();

        // Registrar como nota tipo "resolucao"
        NexoTicketNota::create([
            'ticket_id' => $ticketId,
            'user_id' => $userId,
            'texto' => $resolucao,
            'tipo' => 'resolucao',
            'notificou_cliente' => false,
        ]);

        Log::info('NEXO-TICKETS: Resolvido', [
            'ticket_id' => $ticketId,
            'user_id' => $userId,
        ]);

        // Notificar cliente por WhatsApp
        $mensagemResolucao = "Seu atendimento foi concluido!\n\n"
            . "Resolucao: {$resolucao}";
        $this->notificarClienteWhatsApp($ticket, $mensagemResolucao);

        return $ticket;
    }

    private function notificarClienteWhatsApp(NexoTicket $ticket, string $mensagemNota): void
    {
        try {
            $protocolo = $ticket->protocolo ?? 'N/A';
            $mensagem = "Atualizacao do seu atendimento\n\n"
                . "Protocolo: {$protocolo}\n"
                . "Assunto: {$ticket->assunto}\n\n"
                . "{$mensagemNota}\n\n"
                . "Mayer Sociedade de Advogados";

            $telefone = $ticket->telefone;
            $resultado = $this->sendPulse->sendMessageByPhone($telefone, $mensagem);

            if (empty($resultado) || isset($resultado['error'])) {
                $this->sendPulse->sendMessageByPhone('+' . $telefone, $mensagem);
            }

            Log::info('NEXO-TICKETS: WhatsApp enviado', [
                'ticket_id' => $ticket->id,
                'telefone' => $telefone,
            ]);
        } catch (\Exception $e) {
            Log::warning('NEXO-TICKETS: Falha ao enviar WhatsApp', [
                'ticket_id' => $ticket->id,
                'erro' => $e->getMessage(),
            ]);
        }
    }

    public function criarManual(array $dados, int $userId): NexoTicket
    {
        $protocolo = 'TKT-' . date('Ymd') . '-' . str_pad(
            NexoTicket::whereDate('created_at', today())->count() + 1,
            3, '0', STR_PAD_LEFT
        );

        $responsavelId = $dados['responsavel_id'] ?? null;

        // Se não veio responsável explícito, busca pelo CRM usando o telefone
        if (!$responsavelId && !empty($dados['telefone'])) {
            $responsavelId = $this->resolverResponsavelPorTelefone($dados['telefone']);
        }

        $ticket = NexoTicket::create([
            'nome_cliente' => $dados['nome_cliente'] ?? null,
            'telefone' => $dados['telefone'] ?? '',
            'tipo' => $dados['tipo'] ?? 'geral',
            'protocolo' => $protocolo,
            'assunto' => $dados['assunto'],
            'mensagem' => $dados['mensagem'] ?? null,
            'status' => $responsavelId ? 'em_andamento' : 'aberto',
            'prioridade' => $dados['prioridade'] ?? 'normal',
            'responsavel_id' => $responsavelId,
            'origem' => 'manual',
        ]);

        if ($responsavelId) {
            $this->notificarResponsavel($ticket);
        }

        return $ticket;
    }

    /**
     * Resolve o user_id do advogado responsável a partir do telefone.
     * Estratégia:
     *   1. telefone → clientes.telefone → crm_accounts.datajuri_pessoa_id → owner_user_id
     *   2. telefone → crm_accounts.phone_e164 → owner_user_id (fallback direto)
     */
    private function resolverResponsavelPorTelefone(string $telefone): ?int
    {
        $digits = preg_replace('/\D/', '', $telefone);
        if (strlen($digits) < 8) return null;

        // Monta variantes com e sem DDI 55
        $variantes = [$digits];
        if (str_starts_with($digits, '55') && strlen($digits) >= 12) {
            $variantes[] = substr($digits, 2);
        } elseif (strlen($digits) <= 11) {
            $variantes[] = '55' . $digits;
        }

        // 1. Via tabela clientes → crm_accounts
        $cliente = Cliente::whereIn('telefone', $variantes)->first();
        if ($cliente?->datajuri_id) {
            $account = CrmAccount::where('datajuri_pessoa_id', $cliente->datajuri_id)
                ->whereNotNull('owner_user_id')
                ->first();
            if ($account?->owner_user_id) return $account->owner_user_id;
        }

        // 2. Direto em crm_accounts.phone_e164
        $phone_e164 = '+' . (str_starts_with($digits, '55') ? $digits : '55' . $digits);
        $account = CrmAccount::where('phone_e164', $phone_e164)
            ->whereNotNull('owner_user_id')
            ->first();

        return $account?->owner_user_id;
    }

    /**
     * Envia notificação bell + email para o responsável atribuído.
     */
    private function notificarResponsavel(NexoTicket $ticket): void
    {
        $responsavel = $ticket->responsavel;
        if (!$responsavel) return;

        $protocolo  = $ticket->protocolo ?? "#{$ticket->id}";
        $cliente    = $ticket->nome_cliente ?? 'Cliente não identificado';
        $linkTicket = route('nexo.tickets') . '?busca=' . urlencode($protocolo);

        // Notificação no sininho
        NotificationIntranet::enviar(
            userId: $responsavel->id,
            titulo: "🎫 Novo ticket: {$protocolo}",
            mensagem: "{$cliente} — {$ticket->assunto}",
            link: $linkTicket,
            icone: 'ticket'
        );

        // Email para o responsável
        try {
            if ($responsavel->email) {
                Mail::to($responsavel->email)->send(new NexoTicketAtribuido($responsavel, [
                    'protocolo'    => $protocolo,
                    'assunto'      => $ticket->assunto,
                    'nome_cliente' => $ticket->nome_cliente,
                    'telefone'     => $ticket->telefone,
                    'tipo'         => $ticket->tipo_label,
                    'prioridade'   => $ticket->prioridade,
                    'mensagem'     => $ticket->mensagem,
                    'link'         => $linkTicket,
                ]));
            }
        } catch (\Exception $e) {
            Log::warning('NEXO-TICKETS: Falha ao enviar email de atribuição', [
                'ticket_id' => $ticket->id,
                'responsavel_id' => $responsavel->id,
                'erro' => $e->getMessage(),
            ]);
        }
    }
}
