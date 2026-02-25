<?php

namespace App\Services\Crm;

use App\Models\Crm\CrmAccount;
use App\Models\Crm\CrmOwnerProfile;
use App\Models\Crm\CrmDistributionProposal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CrmDistributionService
{
    /**
     * Gerar proposta via IA em 2 fases com Structured Outputs.
     * Fase 1: IA seleciona filés do Rafael (top 30)
     * Fase 2: IA distribui restante entre Patrícia, Anelise, Franciéli (lotes de 80)
     */
    public function gerarProposta(int $userId, ?callable $onProgress = null): CrmDistributionProposal
    {
        $profiles = CrmOwnerProfile::active()->with('user')->get();
        $accounts = $this->loadAccountData();
        $total = count($accounts);

        if ($onProgress) $onProgress("Carregando {$total} clientes ativos...");

        // === FASE 1: IA seleciona filés do Rafael ===
        if ($onProgress) $onProgress("FASE 1: IA selecionando clientes estratégicos para Rafael...");

        $top30 = array_slice($accounts, 0, 30);
        $rafaelAssignments = $this->fase1_selecionarFilesRafael($top30, $onProgress);
        $rafaelIds = collect($rafaelAssignments)->pluck('account_id')->toArray();

        if ($onProgress) $onProgress("Rafael recebeu " . count($rafaelIds) . " clientes estratégicos.");

        // === FASE 2: IA distribui restante ===
        $remaining = [];
        foreach ($accounts as $a) {
            if (!in_array($a->id, $rafaelIds)) {
                $remaining[] = $a;
            }
        }

        if ($onProgress) $onProgress("FASE 2: Distribuindo " . count($remaining) . " clientes entre Patrícia, Anelise e Franciéli...");

        $outrosAssignments = $this->fase2_distribuirRestante($remaining, $profiles, $onProgress);

        // Juntar tudo
        $allAssignments = array_merge($rafaelAssignments, $outrosAssignments);

        // Cobrir missing
        $coveredIds = collect($allAssignments)->pluck('account_id')->toArray();
        $missing = 0;
        foreach ($accounts as $a) {
            if (!in_array($a->id, $coveredIds)) {
                $allAssignments[] = [
                    'account_id' => $a->id,
                    'suggested_owner_id' => 3,
                    'reason' => 'Fallback - nao coberto',
                    'score' => 20,
                    'overridden' => false,
                ];
                $missing++;
            }
        }
        if ($missing > 0 && $onProgress) $onProgress("Fallback: {$missing} clientes nao cobertos atribuidos a Patricia.");

        $summary = $this->buildSummary($allAssignments, $profiles);

        return CrmDistributionProposal::create([
            'status' => 'pending',
            'assignments' => $allAssignments,
            'summary' => $summary,
            'ai_reasoning' => 'Fase 1 (IA): ' . count($rafaelIds) . ' clientes estrategicos para Rafael. Fase 2 (IA): ' . count($outrosAssignments) . ' clientes distribuidos entre Patricia, Anelise e Francieli.',
            'created_by' => $userId,
        ]);
    }

    /**
     * Fase 1: IA seleciona clientes estratégicos para o Rafael.
     */
    private function fase1_selecionarFilesRafael(array $top30, ?callable $onProgress): array
    {
        $csv = "id,nome,receita,proc_ativos,vencido\n";
        foreach ($top30 as $a) {
            $nome = str_replace([',', '"'], [' ', ''], mb_substr($a->name, 0, 30));
            $csv .= $a->id . "," . $nome . "," . round($a->receita) . "," . $a->proc_ativos . "," . round($a->valor_vencido) . "\n";
        }

        $prompt = "Voce e o consultor de gestao do escritorio Mayer Advogados.\n";
        $prompt .= "Rafael Mayer e o socio fundador. Ele quer ter na carteira pessoal dele APENAS os clientes mais importantes (files).\n";
        $prompt .= "Abaixo estao os 30 clientes com maior receita historica do escritorio.\n";
        $prompt .= "Sua missao: selecione entre 10 e 20 clientes que DEVEM ficar com Rafael.\n";
        $prompt .= "Criterios para ser file do Rafael: receita >= 15000 OU processos ativos >= 3 OU valor vencido alto.\n";
        $prompt .= "Clientes com receita baixa e zero processos NAO sao files.\n\n";
        $prompt .= "CLIENTES (ordenados por receita):\n" . $csv;

        $schema = [
            'type' => 'object',
            'properties' => [
                'selected' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'account_id' => ['type' => 'integer'],
                            'reason' => ['type' => 'string'],
                            'score' => ['type' => 'integer'],
                        ],
                        'required' => ['account_id', 'reason', 'score'],
                        'additionalProperties' => false,
                    ],
                ],
                'reasoning' => ['type' => 'string'],
            ],
            'required' => ['selected', 'reasoning'],
            'additionalProperties' => false,
        ];

        $raw = $this->callOpenAI($prompt, $schema, 3000, 90);

        if (!$raw || !isset($raw['selected'])) {
            if ($onProgress) $onProgress("WARN: Fase 1 IA falhou, fallback top 15 por receita.");
            $assignments = [];
            foreach (array_slice($top30, 0, 15) as $a) {
                $assignments[] = ['account_id' => $a->id, 'suggested_owner_id' => 1, 'reason' => 'Top receita (fallback)', 'score' => 80, 'overridden' => false];
            }
            return $assignments;
        }

        $valid = collect($top30)->pluck('id')->toArray();
        $assignments = [];
        foreach (array_slice($raw['selected'], 0, 20) as $s) {
            if (in_array($s['account_id'], $valid)) {
                $assignments[] = [
                    'account_id' => $s['account_id'],
                    'suggested_owner_id' => 1,
                    'reason' => $s['reason'],
                    'score' => $s['score'],
                    'overridden' => false,
                ];
            }
        }

        return $assignments;
    }

    /**
     * Fase 2: IA distribui clientes restantes entre 3 advogadas.
     */
    private function fase2_distribuirRestante(array $remaining, $profiles, ?callable $onProgress): array
    {
        $batches = array_chunk($remaining, 80);
        $allAssignments = [];
        $accumulated = [3 => 0, 7 => 0, 8 => 0];

        foreach ($batches as $bIdx => $batch) {
            $bNum = $bIdx + 1;
            $bTotal = count($batches);
            if ($onProgress) $onProgress("  Lote {$bNum}/{$bTotal} (" . count($batch) . " clientes)...");

            $csv = "id,nome,tipo,receita,proc,vencido\n";
            foreach ($batch as $a) {
                $nome = str_replace([',', '"'], [' ', ''], mb_substr($a->name, 0, 30));
                $csv .= $a->id . "," . $nome . "," . $a->kind . "," . round($a->receita) . "," . $a->proc_ativos . "," . round($a->valor_vencido) . "\n";
            }

            $prompt = "Distribua estes " . count($batch) . " clientes entre 3 advogadas do escritorio Mayer Advogados.\n\n";
            $prompt .= "ADVOGADAS:\n";
            $prompt .= "- Patricia Silveira Martins (ID:3): coordenadora plena, generalista, experiencia ampla. Ja tem " . $accumulated[3] . " clientes.\n";
            $prompt .= "- Anelise Muller (ID:7): junior, experiencia em empresarial/tributario/civel. Ja tem " . $accumulated[7] . " clientes.\n";
            $prompt .= "- Francieli Vasconcellos Nogueira (ID:8): junior, generalista. Ja tem " . $accumulated[8] . " clientes.\n\n";
            $prompt .= "REGRAS:\n";
            $prompt .= "1. Dividir equilibradamente (diferenca maxima de 5 entre elas).\n";
            $prompt .= "2. Clientes complexos (proc>5 ou receita>20000) priorizar Patricia.\n";
            $prompt .= "3. Empresas (tipo=client com receita>10000) considerar perfil empresarial de Anelise.\n";
            $prompt .= "4. Cada cliente deve ser atribuido a exatamente uma advogada.\n\n";
            $prompt .= "CLIENTES:\n" . $csv;

            $schema = [
                'type' => 'object',
                'properties' => [
                    'assignments' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'account_id' => ['type' => 'integer'],
                                'suggested_owner_id' => ['type' => 'integer'],
                                'reason' => ['type' => 'string'],
                                'score' => ['type' => 'integer'],
                            ],
                            'required' => ['account_id', 'suggested_owner_id', 'reason', 'score'],
                            'additionalProperties' => false,
                        ],
                    ],
                    'reasoning' => ['type' => 'string'],
                ],
                'required' => ['assignments', 'reasoning'],
                'additionalProperties' => false,
            ];

            $raw = $this->callOpenAI($prompt, $schema, 6000, 120);

            $validIds = collect($batch)->pluck('id')->toArray();
            $validOwners = [3, 7, 8];

            if ($raw && isset($raw['assignments'])) {
                foreach ($raw['assignments'] as $a) {
                    $accId = (int) ($a['account_id'] ?? 0);
                    $ownId = (int) ($a['suggested_owner_id'] ?? 0);
                    if (in_array($accId, $validIds) && in_array($ownId, $validOwners)) {
                        $allAssignments[] = [
                            'account_id' => $accId,
                            'suggested_owner_id' => $ownId,
                            'reason' => $a['reason'] ?? '',
                            'score' => $a['score'] ?? 50,
                            'overridden' => false,
                        ];
                        $accumulated[$ownId]++;
                    }
                }
            }

            // Cobrir missing deste lote
            $coveredBatch = collect($allAssignments)->pluck('account_id')->toArray();
            foreach ($batch as $acc) {
                if (!in_array($acc->id, $coveredBatch)) {
                    $minOwner = collect($validOwners)->sortBy(fn($id) => $accumulated[$id])->first();
                    $allAssignments[] = [
                        'account_id' => $acc->id,
                        'suggested_owner_id' => $minOwner,
                        'reason' => 'Fallback lote',
                        'score' => 25,
                        'overridden' => false,
                    ];
                    $accumulated[$minOwner]++;
                }
            }

            if ($onProgress) $onProgress("  Lote {$bNum} OK. Patricia={$accumulated[3]}, Anelise={$accumulated[7]}, Francieli={$accumulated[8]}");
        }

        return $allAssignments;
    }

    /**
     * Chamada OpenAI com Structured Outputs (response_format json_schema strict).
     */
    private function callOpenAI(string $prompt, array $schema, int $maxTokens, int $timeout): ?array
    {
        $apiKey = config('services.openai.api_key');

        try {
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->timeout($timeout)->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-5-mini',
                'messages' => [
                    ['role' => 'system', 'content' => 'Voce e um consultor de gestao de carteira de clientes de um escritorio de advocacia. Siga as instrucoes com precisao.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'max_completion_tokens' => $maxTokens,
                'response_format' => [
                    'type' => 'json_schema',
                    'json_schema' => [
                        'name' => 'distribution_result',
                        'strict' => true,
                        'schema' => $schema,
                    ],
                ],
            ]);

            if (!$response->successful()) {
                Log::error('CRM Distribution OpenAI error', ['status' => $response->status(), 'body' => substr($response->body(), 0, 500)]);
                return null;
            }

            $raw = trim($response->json('choices.0.message.content', ''));
            return json_decode($raw, true);

        } catch (\Throwable $e) {
            Log::error('CRM Distribution OpenAI exception', ['msg' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Aplicar proposta aprovada.
     */
    public function aplicarProposta(CrmDistributionProposal $proposal, int $userId, array $overrides = []): void
    {
        if ($proposal->status === 'applied') {
            throw new \RuntimeException('Proposta já foi aplicada.');
        }

        $assignments = $proposal->assignments;

        // Aplicar overrides manuais
        foreach ($overrides as $accountId => $newOwnerId) {
            foreach ($assignments as &$a) {
                if ($a['account_id'] == $accountId) {
                    $a['original_owner_id'] = $a['suggested_owner_id'];
                    $a['suggested_owner_id'] = (int) $newOwnerId;
                    $a['overridden'] = true;
                    break;
                }
            }
        }

        // Aplicar no banco
        DB::transaction(function () use ($assignments) {
            foreach ($assignments as $a) {
                CrmAccount::where('id', $a['account_id'])
                    ->update(['owner_user_id' => $a['suggested_owner_id']]);
            }
        });

        $proposal->update([
            'status' => 'applied',
            'assignments' => $assignments,
            'summary' => $this->buildSummary($assignments, CrmOwnerProfile::active()->with('user')->get()),
            'approved_by' => $userId,
            'applied_at' => now(),
        ]);
    }

    /**
     * Sugerir owner para um novo cliente (fila de revisão).
     */
    public function sugerirOwner(CrmAccount $account): ?int
    {
        $profiles = CrmOwnerProfile::active()->with('user')->get();

        // Calcular receita do account
        $receita = DB::table('contas_receber')
            ->where('pessoa_datajuri_id', $account->datajuri_pessoa_id)
            ->where('is_stale', 0)
            ->whereIn('status', ['Concluído', 'Concluido'])
            ->sum('valor');

        $procAtivos = DB::table('processos')
            ->where('cliente_datajuri_id', $account->datajuri_pessoa_id)
            ->where('status', 'Ativo')
            ->count();

        // Cliente de alto valor → Rafael (se não estourou limite)
        $rafael = $profiles->where('user_id', 1)->first();
        if ($rafael && $receita >= 50000 && $rafael->currentCount() < $rafael->max_accounts) {
            return 1;
        }

        // Distribuir para quem tem menos carga relativa
        $best = null;
        $bestRatio = PHP_FLOAT_MAX;

        foreach ($profiles->where('user_id', '!=', 1) as $p) {
            $current = $p->currentCount();
            if ($current >= $p->max_accounts) continue;

            $ratio = $current / max($p->max_accounts, 1);
            if ($ratio < $bestRatio) {
                $bestRatio = $ratio;
                $best = $p->user_id;
            }
        }

        return $best;
    }

    private function loadAccountData(): array
    {
        return DB::select("
            SELECT
                a.id, a.name, a.kind, a.segment, a.lifecycle,
                COALESCE((SELECT SUM(cr.valor) FROM contas_receber cr
                    WHERE cr.pessoa_datajuri_id = a.datajuri_pessoa_id
                    AND cr.is_stale = 0
                    AND cr.status IN ('Concluído','Concluido')), 0) as receita,
                COALESCE((SELECT SUM(cr2.valor) FROM contas_receber cr2
                    WHERE cr2.pessoa_datajuri_id = a.datajuri_pessoa_id
                    AND cr2.is_stale = 0
                    AND cr2.status NOT IN ('Concluído','Concluido','Excluido','Excluído')
                    AND cr2.data_vencimento < CURDATE()), 0) as valor_vencido,
                (SELECT COUNT(*) FROM processos p
                    WHERE p.cliente_datajuri_id = a.datajuri_pessoa_id
                    AND p.status = 'Ativo') as proc_ativos,
                (SELECT COUNT(*) FROM crm_opportunities o
                    WHERE o.account_id = a.id AND o.status = 'open') as opps_abertas
            FROM crm_accounts a
            WHERE a.lifecycle = 'ativo'
            ORDER BY receita DESC
        ");
    }

    private function buildPrompt(object $profiles, array $accounts): string
    {
        $profilesText = "";
        foreach ($profiles as $p) {
            $specs = implode(', ', $p->specialties ?? []);
            $profilesText .= "- " . $p->user->name . " (ID:" . $p->user_id . "): max=" . $p->max_accounts . ", peso=" . $p->priority_weight . "/10, especialidades=[" . $specs . "]\n";
        }

        $csv = "id,nome,tipo,receita,vencido,proc,opps\n";
        foreach ($accounts as $a) {
            $nome = str_replace(',', ' ', mb_substr($a->name, 0, 30));
            $csv .= $a->id . "," . $nome . "," . $a->kind . "," . round($a->receita) . "," . round($a->valor_vencido) . "," . $a->proc_ativos . "," . $a->opps_abertas . "\n";
        }

        $total = count($accounts);

        $prompt = "Distribua " . $total . " clientes ativos de um escritorio de advocacia entre os responsaveis abaixo.\n\n";
        $prompt .= "RESPONSAVEIS:\n" . $profilesText . "\n";
        $prompt .= "REGRAS OBRIGATORIAS:\n";
        $prompt .= "1. Rafael (ID:1): APENAS top clientes (receita>20000 OU proc>5). MAXIMO 20 clientes.\n";
        $prompt .= "2. Patricia (ID:3), Anelise (ID:7), Francieli (ID:8): dividir restante equilibradamente.\n";
        $prompt .= "3. Respeitar max_accounts de cada um.\n";
        $prompt .= "4. Clientes com proc>10 sao complexos - priorizar Patricia ou Rafael.\n";
        $prompt .= "5. Balancear carga (total proc_ativos) entre os 3 advogados.\n\n";
        $prompt .= "CLIENTES (CSV):\n" . $csv . "\n";
        $prompt .= "Responda APENAS com JSON valido. Formato exato:\n";
        $prompt .= '{"assignments":[{"account_id":ID,"suggested_owner_id":OWNER_ID,"reason":"motivo","score":NUMERO}],"reasoning":"explicacao"}' . "\n\n";
        $prompt .= "CRITICO: O array assignments DEVE conter exatamente " . $total . " itens, um para cada cliente. Nao omita nenhum.";

        return $prompt;
    }

    private function validateAssignments(array $assignments, $profiles, array $accounts): array
    {
        $accountIds = collect($accounts)->pluck('id')->toArray();
        $ownerIds = $profiles->pluck('user_id')->toArray();
        $validated = [];

        foreach ($assignments as $a) {
            if (!in_array($a['account_id'] ?? 0, $accountIds)) continue;
            if (!in_array($a['suggested_owner_id'] ?? 0, $ownerIds)) continue;

            $validated[] = [
                'account_id' => (int) $a['account_id'],
                'suggested_owner_id' => (int) $a['suggested_owner_id'],
                'reason' => $a['reason'] ?? '',
                'score' => $a['score'] ?? 50,
                'overridden' => false,
            ];
        }

        // Atribuir clientes não cobertos pela IA ao responsável com menos carga
        $covered = collect($validated)->pluck('account_id')->toArray();
        $missing = array_diff($accountIds, $covered);

        if (!empty($missing)) {
            $counts = [];
            foreach ($ownerIds as $oid) {
                $counts[$oid] = collect($validated)->where('suggested_owner_id', $oid)->count();
            }

            foreach ($missing as $accId) {
                // Não atribuir ao Rafael (ID:1) clientes remanescentes
                $candidates = array_filter($ownerIds, fn($id) => $id !== 1);
                $minId = collect($candidates)->sortBy(fn($id) => $counts[$id] ?? 0)->first();

                $validated[] = [
                    'account_id' => (int) $accId,
                    'suggested_owner_id' => (int) $minId,
                    'reason' => 'Distribuído automaticamente (fallback)',
                    'score' => 30,
                    'overridden' => false,
                ];
                $counts[$minId] = ($counts[$minId] ?? 0) + 1;
            }
        }

        return $validated;
    }

    private function buildSummary(array $assignments, $profiles): array
    {
        $summary = [];
        foreach ($profiles as $p) {
            $mine = collect($assignments)->where('suggested_owner_id', $p->user_id);
            $summary[] = [
                'user_id' => $p->user_id,
                'name' => $p->user->name,
                'qty' => $mine->count(),
                'max' => $p->max_accounts,
                'overridden' => $mine->where('overridden', true)->count(),
            ];
        }
        return $summary;
    }
}
