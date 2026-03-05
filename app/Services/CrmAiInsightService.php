<?php

namespace App\Services;

use App\Models\CrmAiInsight;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CrmAiInsightService
{
    protected string $apiKey;
    protected string $model = 'gpt-5-mini';

    public function __construct()
    {
        $this->apiKey = env('OPENAI_API_KEY');
    }

    // ══════════════════════════════════════════════════
    // WEEKLY DIGEST — Visão geral semanal do CRM
    // ══════════════════════════════════════════════════

    public function generateWeeklyDigest(): ?CrmAiInsight
    {
        $snapshot = $this->buildWeeklySnapshot();

        $systemPrompt = <<<PROMPT
Você é um consultor de gestão de escritórios de advocacia especializado em CRM e desenvolvimento comercial.
Analise o snapshot semanal do CRM abaixo e gere um digest executivo para o sócio-gestor.

FORMATO OBRIGATÓRIO da resposta (JSON):
{
  "titulo": "Título curto do digest (max 80 chars)",
  "insight_text": "Análise executiva em 3-5 parágrafos. Inclua: situação da carteira, pipeline, atividade comercial, pontos de atenção. Seja direto e acionável. Não use bullet points.",
  "actions": ["Ação 1 específica e prática", "Ação 2", "Ação 3"],
  "priority": "alta|media|baixa"
}

REGRAS:
- Foque em tendências e anomalias, não em números absolutos que o gestor já vê no painel.
- Se há contas sem responsável, sugira redistribuição com critério.
- Se há oportunidades paradas, sugira ações específicas.
- Se a atividade da semana caiu, alerte.
- Não invente dados. Use apenas o que está no snapshot.
- Responda APENAS o JSON, sem markdown, sem backticks.
PROMPT;

        $userMessage = "Snapshot semanal do CRM (gerado em " . now('America/Sao_Paulo')->format('d/m/Y H:i') . "):\n\n" . json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $response = $this->callOpenAI($systemPrompt, $userMessage);

        if (!$response) {
            return null;
        }

        // Desativar digests anteriores
        CrmAiInsight::where('tipo', 'weekly_digest')
            ->where('status', 'active')
            ->update(['status' => 'dismissed']);

        return CrmAiInsight::create([
            'account_id'        => null,
            'tipo'              => 'weekly_digest',
            'titulo'            => $response['titulo'] ?? 'Digest Semanal CRM',
            'insight_text'      => $response['insight_text'] ?? '',
            'action_suggested'  => isset($response['actions']) ? implode("\n", $response['actions']) : null,
            'priority'          => $response['priority'] ?? 'media',
            'status'            => 'active',
            'context_snapshot'  => $snapshot,
        ]);
    }

    // ══════════════════════════════════════════════════
    // ACCOUNT ACTION — Sugestão por conta sob demanda
    // ══════════════════════════════════════════════════

    public function generateAccountAction(int $accountId, ?int $userId = null): ?CrmAiInsight
    {
        $snapshot = $this->buildAccountSnapshot($accountId);

        if (!$snapshot) {
            Log::warning('CRM AI: account não encontrado', ['account_id' => $accountId]);
            return null;
        }

        $systemPrompt = <<<PROMPT
Você é um consultor de gestão de relacionamento com clientes de um escritório de advocacia.
Analise o perfil completo da conta abaixo e sugira a melhor próxima ação comercial ou de relacionamento.

FORMATO OBRIGATÓRIO da resposta (JSON):
{
  "titulo": "Título curto da sugestão (max 80 chars)",
  "insight_text": "Análise da situação da conta em 2-3 parágrafos. Contextualize o health score, atividade recente, processos e financeiro. Seja específico.",
  "action_suggested": "Uma ação prática, concreta e imediata que o advogado responsável deve tomar. Ex: 'Ligar para o cliente para acompanhar o processo X que teve movimentação em DD/MM'. Não seja genérico.",
  "priority": "alta|media|baixa"
}

REGRAS:
- Health score < 40 = prioridade alta, requer ação imediata.
- Conta sem contato há 30+ dias = sugerir contato proativo.
- Inadimplência ativa = sugerir abordagem de cobrança amigável.
- Processo com movimentação recente = oportunidade de contato.
- Responda APENAS o JSON, sem markdown, sem backticks.
PROMPT;

        $userMessage = "Perfil da conta CRM (gerado em " . now('America/Sao_Paulo')->format('d/m/Y H:i') . "):\n\n" . json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $response = $this->callOpenAI($systemPrompt, $userMessage);

        if (!$response) {
            return null;
        }

        // Desativar sugestões anteriores da mesma conta
        CrmAiInsight::where('tipo', 'account_action')
            ->where('account_id', $accountId)
            ->where('status', 'active')
            ->update(['status' => 'dismissed']);

        return CrmAiInsight::create([
            'account_id'          => $accountId,
            'tipo'                => 'account_action',
            'titulo'              => $response['titulo'] ?? 'Sugestão para conta',
            'insight_text'        => $response['insight_text'] ?? '',
            'action_suggested'    => $response['action_suggested'] ?? null,
            'priority'            => $response['priority'] ?? 'media',
            'status'              => 'active',
            'generated_by_user_id' => $userId,
            'context_snapshot'    => $snapshot,
        ]);
    }

    // ══════════════════════════════════════════════════
    // SNAPSHOTS — Coleta de dados para prompt
    // ══════════════════════════════════════════════════

    protected function buildWeeklySnapshot(): array
    {
        $inicioSemana = Carbon::now('America/Sao_Paulo')->startOfWeek(Carbon::MONDAY);
        $semanaPassada = $inicioSemana->copy()->subWeek();

        // Carteira
        $porLifecycle = DB::table('crm_accounts')
            ->select('lifecycle', DB::raw('COUNT(*) as total'))
            ->groupBy('lifecycle')
            ->pluck('total', 'lifecycle')
            ->toArray();

        $semOwner = DB::table('crm_accounts')->whereNull('owner_user_id')->count();

        // Health score distribuição
        $healthDist = DB::table('crm_accounts')
            ->whereNotNull('health_score')
            ->selectRaw('
                AVG(health_score) as media,
                SUM(CASE WHEN health_score < 20 THEN 1 ELSE 0 END) as perdido,
                SUM(CASE WHEN health_score >= 20 AND health_score < 40 THEN 1 ELSE 0 END) as critico
            ')
            ->first();

        // Pipeline
        $pipeline = DB::table('crm_opportunities')
            ->join('crm_stages', 'crm_opportunities.stage_id', '=', 'crm_stages.id')
            ->where('crm_opportunities.status', 'open')
            ->where('crm_stages.is_won', false)
            ->where('crm_stages.is_lost', false)
            ->selectRaw('COUNT(*) as abertas, SUM(COALESCE(value_estimated, 0)) as valor_total')
            ->first();

        $opParadas = DB::table('crm_opportunities')
            ->where('status', 'open')
            ->where('updated_at', '<', Carbon::now()->subDays(15))
            ->count();

        // Atividade semana atual vs anterior
        $atividadeSemana = DB::table('crm_activities')
            ->whereBetween('created_at', [$inicioSemana, now()])
            ->count();

        $atividadeSemanaAnterior = DB::table('crm_activities')
            ->whereBetween('created_at', [$semanaPassada, $inicioSemana])
            ->count();

        // Atividade por usuário esta semana
        $atividadePorUsuario = DB::table('crm_activities')
            ->join('users', 'crm_activities.created_by_user_id', '=', 'users.id')
            ->whereBetween('crm_activities.created_at', [$inicioSemana, now()])
            ->select('users.name', DB::raw('COUNT(*) as total'))
            ->groupBy('users.name')
            ->pluck('total', 'name')
            ->toArray();

        // Inadimplência top 5
        $inadimplencia = DB::table('contas_receber')
            ->join('clientes', 'contas_receber.cliente_datajuri_id', '=', 'clientes.datajuri_id')
            ->where('contas_receber.status', '!=', 'Concluído')
            ->where('contas_receber.status', '!=', 'Excluido')
            ->where(function ($q) {
                $q->where('contas_receber.is_stale', false)->orWhereNull('contas_receber.is_stale');
            })
            ->whereNotNull('contas_receber.data_vencimento')
            ->whereDate('contas_receber.data_vencimento', '<', Carbon::today())
            ->selectRaw('clientes.nome, SUM(contas_receber.valor) as total_vencido')
            ->groupBy('clientes.nome')
            ->orderByDesc(DB::raw('SUM(contas_receber.valor)'))
            ->limit(5)
            ->pluck('total_vencido', 'nome')
            ->toArray();

        // Leads novos sem ação
        $leadsNovos = DB::table('leads')
            ->where('status', 'novo')
            ->count();

        return [
            'periodo'                   => $inicioSemana->format('d/m/Y') . ' a ' . now('America/Sao_Paulo')->format('d/m/Y'),
            'carteira_por_lifecycle'    => $porLifecycle,
            'contas_sem_responsavel'    => $semOwner,
            'health_score_medio'        => round($healthDist->media ?? 0),
            'contas_criticas'           => ($healthDist->critico ?? 0) + ($healthDist->perdido ?? 0),
            'pipeline_abertas'          => $pipeline->abertas ?? 0,
            'pipeline_valor'            => $pipeline->valor_total ?? 0,
            'oportunidades_paradas_15d' => $opParadas,
            'atividades_semana_atual'   => $atividadeSemana,
            'atividades_semana_anterior' => $atividadeSemanaAnterior,
            'atividades_por_advogado'   => $atividadePorUsuario,
            'top5_inadimplentes'        => $inadimplencia,
            'leads_novos_sem_acao'      => $leadsNovos,
        ];
    }

    protected function buildAccountSnapshot(int $accountId): ?array
    {
        $account = DB::table('crm_accounts')
            ->leftJoin('users', 'crm_accounts.owner_user_id', '=', 'users.id')
            ->where('crm_accounts.id', $accountId)
            ->select(
                'crm_accounts.*',
                'users.name as owner_name'
            )
            ->first();

        if (!$account) return null;

        // Oportunidades
        $oportunidades = DB::table('crm_opportunities')
            ->join('crm_stages', 'crm_opportunities.stage_id', '=', 'crm_stages.id')
            ->where('crm_opportunities.account_id', $accountId)
            ->select('crm_stages.name as stage', 'crm_opportunities.status', 'crm_opportunities.value_estimated', 'crm_opportunities.title')
            ->get()
            ->toArray();

        // Últimas 10 atividades
        $atividades = DB::table('crm_activities')
            ->leftJoin('users', 'crm_activities.created_by_user_id', '=', 'users.id')
            ->where('crm_activities.account_id', $accountId)
            ->select('crm_activities.type', 'crm_activities.purpose', 'crm_activities.title', 'crm_activities.created_at', 'users.name as user_name')
            ->orderByDesc('crm_activities.created_at')
            ->limit(10)
            ->get()
            ->toArray();

        // Processos ativos (via datajuri_pessoa_id)
        $processos = [];
        if ($account->datajuri_pessoa_id) {
            $processos = DB::table('processos')
                ->where('cliente_datajuri_id', $account->datajuri_pessoa_id)
                ->select('numero', 'status', 'area', 'adverso_nome')
                ->limit(10)
                ->get()
                ->toArray();
        }

        // Inadimplência
        $inadimplencia = [];
        if ($account->datajuri_pessoa_id) {
            $inadimplencia = DB::table('contas_receber')
                ->where('cliente_datajuri_id', $account->datajuri_pessoa_id)
                ->where('status', '!=', 'Concluído')
                ->where('status', '!=', 'Excluido')
                ->where(function ($q) {
                    $q->where('is_stale', false)->orWhereNull('is_stale');
                })
                ->whereNotNull('data_vencimento')
                ->whereDate('data_vencimento', '<', Carbon::today())
                ->selectRaw('SUM(valor) as total, COUNT(*) as titulos, MIN(data_vencimento) as mais_antigo')
                ->first();

            $inadimplencia = $inadimplencia ? (array) $inadimplencia : [];
        }

        return [
            'nome'              => $account->name,
            'lifecycle'         => $account->lifecycle,
            'health_score'      => $account->health_score,
            'responsavel'       => $account->owner_name ?? 'Sem responsável',
            'ultimo_contato'    => $account->last_touch_at,
            'oportunidades'     => $oportunidades,
            'ultimas_atividades' => $atividades,
            'processos_ativos'  => $processos,
            'inadimplencia'     => $inadimplencia,
        ];
    }

    // ══════════════════════════════════════════════════
    // CHAMADA OpenAI
    // ══════════════════════════════════════════════════

    protected function callOpenAI(string $systemPrompt, string $userMessage): ?array
    {
        try {
            $response = Http::timeout(60)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type'  => 'application/json',
                ])
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model'                  => $this->model,
                    'max_completion_tokens'  => 4000,
                    'messages'               => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $userMessage],
                    ],
                ]);

            if (!$response->successful()) {
                Log::error('CRM AI: OpenAI HTTP ' . $response->status(), [
                    'body' => $response->body(),
                ]);
                return null;
            }

            $text = $response->json('choices.0.message.content', '');
            Log::info('CRM AI: raw response', [
                'status' => $response->status(),
                'content_length' => strlen($text),
                'finish_reason' => $response->json('choices.0.finish_reason', '?'),
                'prompt_tokens' => $response->json('usage.prompt_tokens', 0),
                'completion_tokens' => $response->json('usage.completion_tokens', 0),
            ]);
            $text = trim(str_replace(['```json', '```'], '', $text));

            $parsed = json_decode($text, true);

            if (!$parsed) {
                Log::warning('CRM AI: resposta não é JSON válido', ['raw' => substr($text, 0, 500)]);
                return null;
            }

            Log::info('CRM AI: insight gerado', [
                'tipo'   => $parsed['titulo'] ?? '?',
                'tokens' => $response->json('usage.total_tokens', 0),
            ]);

            return $parsed;

        } catch (\Exception $e) {
            Log::error('CRM AI: exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    // ══════════════════════════════════════════════════
    // LEITURA — Para o Painel
    // ══════════════════════════════════════════════════

    public function getActiveDigest(): ?CrmAiInsight
    {
        return CrmAiInsight::where('tipo', 'weekly_digest')
            ->where('status', 'active')
            ->orderByDesc('created_at')
            ->first();
    }

    public function getRecentInsights(int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        return CrmAiInsight::where('status', 'active')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }
}
