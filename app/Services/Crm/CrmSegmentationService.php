<?php

namespace App\Services\Crm;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;

class CrmSegmentationService
{
    private const CACHE_DAYS = 7;

    public function segmentar(int $accountId, bool $forceRefresh = false): ?array
    {
        $account = DB::table('crm_accounts')->where('id', $accountId)->first();
        if (!$account) return null;

        if (!$forceRefresh && $account->segment_cached_at
            && Carbon::parse($account->segment_cached_at)->diffInDays(now()) < self::CACHE_DAYS) {
            return [
                'segment' => $account->segment,
                'summary' => $account->segment_summary,
                'cached'  => true,
            ];
        }

        $snapshot = $this->montarSnapshot($account);

        if (empty($snapshot['nome'])) {
            return null;
        }

        $result = $this->chamarIA($snapshot);

        if ($result) {
            DB::table('crm_accounts')->where('id', $accountId)->update([
                'segment'           => $result['segment'],
                'segment_summary'   => $result['summary'],
                'segment_cached_at' => now(),
                'updated_at'        => now(),
            ]);

            return array_merge($result, ['cached' => false]);
        }

        return null;
    }

    private function montarSnapshot(object $account): array
    {
        $djId = $account->datajuri_pessoa_id;
        $snap = [
            'nome'      => $account->name,
            'tipo'      => $account->kind,
            'lifecycle' => $account->lifecycle,
        ];

        if (!$djId) {
            $snap['dados_datajuri'] = false;
            return $snap;
        }

        $cli = DB::table('clientes')->where('datajuri_id', $djId)->first([
            'tipo', 'profissao', 'total_processos', 'total_contratos',
            'valor_contas_abertas', 'total_contas_receber', 'total_contas_vencidas',
            'data_primeiro_contato', 'data_ultimo_contato',
        ]);

        if ($cli) {
            $snap['pessoa_tipo'] = $cli->tipo;
            $snap['profissao'] = $cli->profissao;
            $snap['total_processos'] = $cli->total_processos ?? 0;
            $snap['total_contratos'] = $cli->total_contratos ?? 0;
            $snap['valor_contas_abertas'] = round((float)($cli->valor_contas_abertas ?? 0), 2);
            $snap['total_contas_receber'] = $cli->total_contas_receber ?? 0;
            $snap['total_contas_vencidas'] = $cli->total_contas_vencidas ?? 0;
            $snap['primeiro_contato'] = $cli->data_primeiro_contato;
            $snap['ultimo_contato'] = $cli->data_ultimo_contato;
        }

        $snap['receita_total'] = round((float)DB::table('movimentos')
            ->where('pessoa_id_datajuri', $djId)
            ->whereIn('classificacao', ['RECEITA_PF', 'RECEITA_PJ'])
            ->where('valor', '>', 0)
            ->sum('valor'), 2);

        $snap['receita_12m'] = round((float)DB::table('movimentos')
            ->where('pessoa_id_datajuri', $djId)
            ->whereIn('classificacao', ['RECEITA_PF', 'RECEITA_PJ'])
            ->where('valor', '>', 0)
            ->where('data', '>=', now()->subMonths(12)->toDateString())
            ->sum('valor'), 2);

        $procs = DB::table('processos')
            ->where('cliente_datajuri_id', $djId)
            ->select('status', DB::raw('COUNT(*) as qty'))
            ->groupBy('status')->get();
        $snap['processos_por_status'] = $procs->pluck('qty', 'status')->toArray();

        $areas = DB::table('processos')
            ->where('cliente_datajuri_id', $djId)
            ->whereNotNull('tipo_acao')
            ->select('tipo_acao', DB::raw('COUNT(*) as qty'))
            ->groupBy('tipo_acao')->get();
        $snap['areas_juridicas'] = $areas->pluck('qty', 'tipo_acao')->toArray();

        $contasAbertasQty = DB::table('contas_receber')
            ->where('pessoa_datajuri_id', $djId)
            ->whereNotIn('status', ['Concluído', 'Concluido', 'Excluido', 'Excluído'])
            ->count();
        $contasVencidasQty = DB::table('contas_receber')
            ->where('pessoa_datajuri_id', $djId)
            ->whereNotIn('status', ['Concluído', 'Concluido', 'Excluido', 'Excluído'])
            ->where('data_vencimento', '<', now()->toDateString())
            ->count();
        $snap['contas_abertas_real'] = $contasAbertasQty;
        $snap['contas_vencidas_real'] = $contasVencidasQty;

        $opps = DB::table('crm_opportunities')
            ->where('account_id', $account->id)
            ->select('status', DB::raw('COUNT(*) as qty'))
            ->groupBy('status')->get();
        $snap['oportunidades'] = $opps->pluck('qty', 'status')->toArray();

        $snap['dias_sem_contato'] = $account->last_touch_at
            ? Carbon::parse($account->last_touch_at)->diffInDays(now())
            : null;

        return $snap;
    }

    private function chamarIA(array $snapshot): ?array
    {
        $systemPrompt = <<<'PROMPT'
Você é um consultor de gestão de escritórios de advocacia no Brasil. Sua tarefa é segmentar um cliente/prospect com base nos dados fornecidos.

CONTEXTO: Escritório de advocacia de médio porte em Santa Catarina, atuando em diversas áreas (cível, trabalhista, empresarial, tributário, família, criminal, etc.).

REGRAS:
1. Analise os dados do cliente e atribua UM segmento principal.
2. Os segmentos devem refletir a realidade de um escritório de advocacia, NÃO de um comércio.
3. Considere: receita gerada, complexidade, recorrência, pontualidade de pagamento, tempo de relacionamento, potencial de novos negócios, risco de inadimplência.
4. Para prospects sem dados financeiros, segmente pelo potencial baseado no tipo e contexto.
5. Se receita_12m = 0 E processos ativos = 0 → o cliente é INATIVO independente do campo lifecycle. Não classifique como "recuperável" sem evidência concreta de potencial.
6. Clientes com alto volume de processos ativos E receita histórica relevante são ESTRATÉGICOS.
7. Clientes com títulos vencidos são de RISCO DE INADIMPLÊNCIA — priorize isso na classificação.
8. Seja realista e pragmático nas recomendações, sem otimismo infundado.

Responda APENAS com JSON válido neste formato exato:
{"segment":"nome_curto_max_4_palavras","summary":"Explicação em 1-2 frases do motivo da classificação e recomendação de abordagem."}

IMPORTANTE: O campo "segment" deve ter NO MÁXIMO 4 palavras (ex: "Estratégico Recorrente", "Inativo Recuperável", "Prospect Qualificado"). Seja conciso no nome do segmento.

Não inclua markdown, backticks ou qualquer texto fora do JSON.
PROMPT;

        $userPrompt = "Dados do cliente:\n" . json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        try {
            $response = Http::timeout(30)->withHeaders([
                'Authorization' => 'Bearer ' . config('services.openai.api_key'),
                'Content-Type'  => 'application/json',
            ])->post('https://api.openai.com/v1/chat/completions', [
                'model'                  => 'gpt-5-mini',
                'max_completion_tokens'  => 1024,
                                'messages'               => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
            ]);

            if (!$response->successful()) {
                Log::error('[CrmSegmentation] API error', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return null;
            }

            $text = $response->json('choices.0.message.content', '');
            $text = trim(str_replace(['```json', '```'], '', $text));

            $parsed = json_decode($text, true);

            if (!$parsed || !isset($parsed['segment']) || !isset($parsed['summary'])) {
                Log::warning('[CrmSegmentation] Parse failed', ['raw' => $text]);
                return null;
            }

            return [
                'segment' => mb_substr($parsed['segment'], 0, 80),
                'summary' => mb_substr($parsed['summary'], 0, 1000),
            ];

        } catch (\Exception $e) {
            Log::error('[CrmSegmentation] Exception', ['msg' => $e->getMessage()]);
            return null;
        }
    }
}
