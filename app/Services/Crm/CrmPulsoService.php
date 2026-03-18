<?php

namespace App\Services\Crm;

use App\Models\Crm\CrmAccount;
use App\Models\Crm\CrmIdentity;
use App\Models\Crm\CrmPulsoAlerta;
use App\Models\Crm\CrmPulsoConfig;
use App\Models\Crm\CrmPulsoDiario;
use App\Models\WaMessage;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CrmPulsoService
{
    protected array $thresholds;

    public function __construct()
    {
        $this->thresholds = CrmPulsoConfig::allThresholds();
    }

    /**
     * Consolida todas as fontes de contato de um dia para todos os accounts ativos.
     */
    public function consolidarDia(string $data): array
    {
        $date = Carbon::parse($data);
        $stats = ['processados' => 0, 'alertas' => 0];

        // Buscar accounts com pelo menos 1 identity phone
        $accountIds = CrmAccount::where('kind', 'client')
            ->pluck('id')
            ->toArray();

        if (empty($accountIds)) {
            return $stats;
        }

        // Pré-calcular: phones -> account_id (mapa reverso)
        $phoneMap = CrmIdentity::where('kind', 'phone')
            ->whereIn('account_id', $accountIds)
            ->pluck('account_id', 'value_norm')
            ->toArray();

        // 1. WA Messages incoming (direction=1) agrupadas por account
        $waIncoming = $this->contarWaIncoming($date, $phoneMap);

        // 2. Tickets NEXO abertos no dia
        $ticketCounts = $this->contarTickets($date, $accountIds);

        // 3. CRM Activities com canal incoming
        $crmInteractions = $this->contarCrmInteractions($date, $accountIds);

        // 4. Phone calls (já carregados via upload em crm_pulso_diario)
        // Não sobreescrevemos phone_calls — pode ter sido preenchido pelo upload

        // 5. Verificar movimentação processual do dia
        $movimentacoes = $this->verificarMovimentacao($date, $accountIds);

        // Consolidar por account
        foreach ($accountIds as $accountId) {
            $waMsgs = $waIncoming[$accountId] ?? 0;
            $tickets = $ticketCounts[$accountId] ?? 0;
            $crm = $crmInteractions[$accountId] ?? 0;
            $hasMov = in_array($accountId, $movimentacoes);

            // Buscar registro existente (pode ter phone_calls do upload)
            $existing = CrmPulsoDiario::where('account_id', $accountId)
                ->where('data', $date->toDateString())
                ->first();

            $phoneCalls = $existing->phone_calls ?? 0;
            $total = $waMsgs + $tickets + $crm + $phoneCalls;

            // Pular se zero contatos e sem registro prévio
            if ($total === 0 && !$existing) {
                continue;
            }

            $exceeded = $total > (int) ($this->thresholds['max_contatos_dia'] ?? 5);

            CrmPulsoDiario::updateOrCreate(
                ['account_id' => $accountId, 'data' => $date->toDateString()],
                [
                    'wa_msgs_incoming'       => $waMsgs,
                    'wa_conversations_opened' => 0, // reservado para evolução futura
                    'tickets_abertos'        => $tickets,
                    'crm_interactions'       => $crm,
                    'phone_calls'            => $phoneCalls,
                    'total_contatos'         => $total,
                    'has_movimentacao'       => $hasMov,
                    'threshold_exceeded'     => $exceeded,
                ]
            );

            $stats['processados']++;
        }

        // Verificar thresholds e gerar alertas
        $stats['alertas'] = $this->verificarThresholds($date);

        Log::info("[Pulso] Consolidação {$data}: {$stats['processados']} accounts, {$stats['alertas']} alertas");
        return $stats;
    }

    /**
     * Conta mensagens WA incoming por account.
     */
    protected function contarWaIncoming(Carbon $date, array $phoneMap): array
    {
        if (empty($phoneMap)) return [];

        $dateStr = $date->toDateString();

        // wa_messages -> wa_conversations.phone -> normalizar -> phoneMap
        // CORRIGIDO 16/03/2026: Conta SESSÕES (conversas distintas), não mensagens individuais
        // Uma conversa com 50 msgs = 1 contato, conforme contrato de atendimento
        $rows = DB::table('wa_messages as m')
            ->join('wa_conversations as c', 'm.conversation_id', '=', 'c.id')
            ->where('m.direction', 1) // incoming
            ->whereDate('m.created_at', $dateStr)
            ->select('c.phone', DB::raw('COUNT(DISTINCT m.conversation_id) as total'))
            ->groupBy('c.phone')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $phoneNorm = preg_replace('/\D/', '', $row->phone ?? '');
            if (isset($phoneMap[$phoneNorm])) {
                $accId = $phoneMap[$phoneNorm];
                $result[$accId] = ($result[$accId] ?? 0) + $row->total;
            } else {
                // Tentar match pelos últimos 11 dígitos
                $suffix = substr($phoneNorm, -11);
                foreach ($phoneMap as $norm => $accId) {
                    if (str_ends_with($norm, $suffix)) {
                        $result[$accId] = ($result[$accId] ?? 0) + $row->total;
                        break;
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Conta tickets NEXO abertos no dia vinculados a accounts.
     */
    protected function contarTickets(Carbon $date, array $accountIds): array
    {
        // nexo_tickets tem phone do solicitante -> resolver para account
        $rows = DB::table('nexo_tickets')
            ->whereDate('created_at', $date->toDateString())
            ->select('telefone', DB::raw('COUNT(*) as total'))
            ->groupBy('telefone')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $phoneNorm = preg_replace('/\D/', '', $row->telefone ?? '');
            $identity = CrmIdentity::where('kind', 'phone')
                ->where(function ($q) use ($phoneNorm) {
                    $q->where('value_norm', $phoneNorm)
                      ->orWhere('value_norm', 'LIKE', '%' . substr($phoneNorm, -11));
                })
                ->whereIn('account_id', $accountIds)
                ->first();

            if ($identity) {
                $accId = $identity->account_id;
                $result[$accId] = ($result[$accId] ?? 0) + $row->total;
            }
        }

        return $result;
    }

    /**
     * Conta atividades CRM incoming do dia.
     */
    protected function contarCrmInteractions(Carbon $date, array $accountIds): array
    {
        return DB::table('crm_activities')
            ->whereIn('account_id', $accountIds)
            ->whereDate('created_at', $date->toDateString())
            ->where('type', 'LIKE', '%incoming%')
            ->select('account_id', DB::raw('COUNT(*) as total'))
            ->groupBy('account_id')
            ->pluck('total', 'account_id')
            ->toArray();
    }

    /**
     * Verifica quais accounts tiveram movimentação processual no dia.
     */
    protected function verificarMovimentacao(Carbon $date, array $accountIds): array
    {
        // andamentos_fase -> processos (via processo_pasta) -> clientes -> crm_accounts
        $dateStr = $date->toDateString();

        $accountsComMov = DB::table('andamentos_fase as af')
            ->join('processos as p', 'af.processo_pasta', '=', 'p.pasta')
            ->join('clientes as cl', 'p.cliente_datajuri_id', '=', 'cl.datajuri_id')
            ->join('crm_accounts as ca', 'ca.datajuri_pessoa_id', '=', 'cl.datajuri_id')
            ->whereDate('af.data_andamento', $dateStr)
            ->whereIn('ca.id', $accountIds)
            ->distinct()
            ->pluck('ca.id')
            ->toArray();

        return $accountsComMov;
    }

    /**
     * Verifica thresholds e gera alertas.
     */
    public function verificarThresholds(Carbon $date): int
    {
        $alertasGerados = 0;
        $maxDia = (int) ($this->thresholds['max_contatos_dia'] ?? 5);
        $maxSemana = (int) ($this->thresholds['max_atualizacao_semana_sem_mov'] ?? 3);

        // 1. Alerta diário excedido
        $excedidos = CrmPulsoDiario::where('data', $date->toDateString())
            ->where('threshold_exceeded', true)
            ->with('account')
            ->get();

        foreach ($excedidos as $reg) {
            $jaAlertado = CrmPulsoAlerta::where('account_id', $reg->account_id)
                ->where('tipo', 'diario_excedido')
                ->whereDate('created_at', $date->toDateString())
                ->exists();

            if (!$jaAlertado) {
                $alerta = CrmPulsoAlerta::create([
                    'account_id' => $reg->account_id,
                    'tipo'       => 'diario_excedido',
                    'descricao'  => "Cliente {$reg->account->name} realizou {$reg->total_contatos} contatos em {$date->format('d/m/Y')} (limite: {$maxDia}).",
                    'dados_json' => [
                        'total'     => $reg->total_contatos,
                        'wa'        => $reg->wa_msgs_incoming,
                        'tickets'   => $reg->tickets_abertos,
                        'crm'       => $reg->crm_interactions,
                        'phone'     => $reg->phone_calls,
                        'threshold' => $maxDia,
                    ],
                ]);

                app(CrmPulsoNotificationService::class)->notificarCoordenacao($alerta);
                $alertasGerados++;
            }
        }

        // 2. Alerta semanal (sem movimentação)
        $inicioSemana = $date->copy()->startOfWeek(Carbon::MONDAY);
        $fimSemana = $date->copy()->endOfWeek(Carbon::FRIDAY);

        $semanais = CrmPulsoDiario::select('account_id')
            ->selectRaw('SUM(total_contatos) as soma_contatos')
            ->selectRaw('MAX(has_movimentacao) as teve_mov')
            ->whereBetween('data', [$inicioSemana->toDateString(), $fimSemana->toDateString()])
            ->groupBy('account_id')
            ->havingRaw('SUM(total_contatos) > ?', [$maxSemana])
            ->havingRaw('MAX(has_movimentacao) = 0')
            ->get();

        foreach ($semanais as $row) {
            // Só alerta na sexta ou no último dia da semana processado
            if ($date->dayOfWeek < Carbon::FRIDAY && $date->dayOfWeek > Carbon::MONDAY) {
                continue; // Espera sexta para alerta semanal
            }

            $jaAlertado = CrmPulsoAlerta::where('account_id', $row->account_id)
                ->where('tipo', 'semanal_excedido')
                ->whereBetween('created_at', [$inicioSemana, $fimSemana->endOfDay()])
                ->exists();

            if (!$jaAlertado) {
                $account = CrmAccount::find($row->account_id);
                $alerta = CrmPulsoAlerta::create([
                    'account_id' => $row->account_id,
                    'tipo'       => 'semanal_excedido',
                    'descricao'  => "Cliente {$account->name} realizou {$row->soma_contatos} contatos na semana sem movimentação processual (limite: {$maxSemana}).",
                    'dados_json' => [
                        'soma_semana' => (int) $row->soma_contatos,
                        'threshold'   => $maxSemana,
                        'periodo'     => $inicioSemana->format('d/m') . ' a ' . $fimSemana->format('d/m'),
                    ],
                ]);

                app(CrmPulsoNotificationService::class)->notificarCoordenacao($alerta);
                $alertasGerados++;
            }
        }

        return $alertasGerados;
    }

    /**
     * Retorna dados do Pulso para um account específico (aba Account 360).
     */
    public function dadosAccount(int $accountId, int $dias = 30): array
    {
        $desde = Carbon::now()->subDays($dias)->toDateString();

        $diarios = CrmPulsoDiario::where('account_id', $accountId)
            ->where('data', '>=', $desde)
            ->orderBy('data')
            ->get();

        $alertas = CrmPulsoAlerta::where('account_id', $accountId)
            ->where('status', '!=', 'resolvido')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        // Classificação: média últimos 7 dias vs threshold
        $media7d = CrmPulsoDiario::where('account_id', $accountId)
            ->where('data', '>=', Carbon::now()->subDays(7)->toDateString())
            ->avg('total_contatos') ?? 0;

        $maxDia = (int) ($this->thresholds['max_contatos_dia'] ?? 5);
        $classificacao = 'normal';
        if ($media7d > $maxDia * 1.5) {
            $classificacao = 'excessivo';
        } elseif ($media7d > $maxDia * 0.7) {
            $classificacao = 'atencao';
        }

        return [
            'diarios'       => $diarios,
            'alertas'       => $alertas,
            'media_7d'      => round($media7d, 1),
            'classificacao' => $classificacao,
            'thresholds'    => $this->thresholds,
        ];
    }

    /**
     * Ranking de clientes para dashboard gerencial.
     */
    public function ranking(int $dias = 7, ?string $classificacao = null): array
    {
        $desde = Carbon::now()->subDays($dias)->toDateString();
        $maxDia = (int) ($this->thresholds['max_contatos_dia'] ?? 5);

        $query = CrmPulsoDiario::select('account_id')
            ->selectRaw('SUM(total_contatos) as soma_total')
            ->selectRaw('SUM(wa_msgs_incoming) as soma_wa')
            ->selectRaw('SUM(tickets_abertos) as soma_tickets')
            ->selectRaw('SUM(phone_calls) as soma_phone')
            ->selectRaw('SUM(crm_interactions) as soma_crm')
            ->selectRaw('AVG(total_contatos) as media_diaria')
            ->selectRaw('MAX(data) as ultimo_dia')
            ->where('data', '>=', $desde)
            ->groupBy('account_id')
            ->having('soma_total', '>', 0)
            ->orderByDesc('soma_total');

        $rows = $query->get()->map(function ($row) use ($maxDia) {
            $row->account = CrmAccount::find($row->account_id);
            $media = (float) $row->media_diaria;
            if ($media > $maxDia * 1.5) {
                $row->classificacao = 'excessivo';
            } elseif ($media > $maxDia * 0.7) {
                $row->classificacao = 'atencao';
            } else {
                $row->classificacao = 'normal';
            }

            $row->ultimo_alerta = CrmPulsoAlerta::where('account_id', $row->account_id)
                ->orderByDesc('created_at')
                ->first();

            return $row;
        });

        if ($classificacao) {
            $rows = $rows->filter(fn($r) => $r->classificacao === $classificacao);
        }

        return $rows->values()->toArray();
    }
}
