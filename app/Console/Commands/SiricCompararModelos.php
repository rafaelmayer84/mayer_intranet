<?php

namespace App\Console\Commands;

use App\Models\SiricConsulta;
use App\Services\SiricOpenAIService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Script provisório para comparar modelos de IA no SIRIC.
 *
 * Usa consultas reais já analisadas/decididas como benchmark.
 * Roda o mesmo prompt (Gate + Relatório) em cada modelo candidato
 * e exibe resultados lado a lado para avaliação humana.
 *
 * Uso: php artisan siric:comparar-modelos
 *      php artisan siric:comparar-modelos --limite=3
 *      php artisan siric:comparar-modelos --consulta=42
 */
class SiricCompararModelos extends Command
{
    protected $signature = 'siric:comparar-modelos
        {--limite=5 : Quantas consultas usar como benchmark}
        {--consulta= : ID específico de uma consulta para testar}
        {--modelos= : Modelos separados por vírgula (ex: gpt-5.2,o3,gpt-5.4)}
        {--somente-relatorio : Pular Gate Decision, rodar só Relatório Final}
        {--salvar : Salvar resultados em JSON no storage}';

    protected $description = 'Compara modelos de IA usando consultas SIRIC reais como benchmark';

    // Modelos candidatos (padrão)
    private array $modelosPadrao = [
        'gpt-5.2',         // Atual
        'o3',              // Reasoning (mais barato)
        'gpt-5.4',         // Mais capaz
        'o4-mini',         // Reasoning compacto
    ];

    private string $apiKey;
    private array $resultados = [];

    public function handle(): int
    {
        $this->apiKey = config('services.siric.openai_api_key', env('SIRIC_OPENAI_API_KEY', env('OPENAI_API_KEY')));

        if (empty($this->apiKey)) {
            $this->error('OPENAI_API_KEY não configurada.');
            return 1;
        }

        // Determinar modelos
        $modelos = $this->option('modelos')
            ? explode(',', $this->option('modelos'))
            : $this->modelosPadrao;

        $this->info("╔══════════════════════════════════════════════════════╗");
        $this->info("║  SIRIC — Comparação de Modelos de IA                ║");
        $this->info("╚══════════════════════════════════════════════════════╝");
        $this->newLine();
        $this->info("Modelos: " . implode(', ', $modelos));

        // Carregar consultas benchmark
        $consultas = $this->carregarConsultas();

        if ($consultas->isEmpty()) {
            $this->error('Nenhuma consulta encontrada para benchmark.');
            $this->info('Necessário: consultas com status "analisado" ou "decidido" e snapshot_interno preenchido.');
            return 1;
        }

        $this->info("Consultas benchmark: {$consultas->count()}");
        $this->newLine();

        // Listar consultas que serão usadas
        $this->table(
            ['ID', 'Nome', 'CPF/CNPJ', 'Valor', 'Rating Original', 'Score Original', 'Decisão Humana'],
            $consultas->map(fn($c) => [
                $c->id,
                mb_substr($c->nome, 0, 25),
                $c->cpf_cnpj_formatado,
                'R$ ' . number_format($c->valor_total, 2, ',', '.'),
                $c->rating ?? '—',
                $c->score ?? '—',
                $c->decisao_humana ?? '—',
            ])->toArray()
        );

        if (!$this->confirm('Prosseguir com a comparação? (custos de API serão gerados)')) {
            return 0;
        }

        // Rodar comparação
        foreach ($consultas as $consulta) {
            $this->compararConsulta($consulta, $modelos);
        }

        // Exibir resumo final
        $this->exibirResumo($modelos);

        // Salvar se solicitado
        if ($this->option('salvar')) {
            $this->salvarResultados();
        }

        return 0;
    }

    private function carregarConsultas()
    {
        if ($id = $this->option('consulta')) {
            return SiricConsulta::where('id', $id)
                ->whereNotNull('snapshot_interno')
                ->get();
        }

        return SiricConsulta::whereIn('status', ['analisado', 'decidido'])
            ->whereNotNull('snapshot_interno')
            ->orderByDesc('updated_at')
            ->limit((int) $this->option('limite'))
            ->get();
    }

    private function compararConsulta(SiricConsulta $consulta, array $modelos): void
    {
        $this->newLine();
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("Consulta #{$consulta->id} — {$consulta->nome}");
        $this->info("Valor: R$ " . number_format($consulta->valor_total, 2, ',', '.')
            . " | Parcelas: {$consulta->parcelas_desejadas}"
            . " | Rating original: " . ($consulta->rating ?? '—')
            . " | Score original: " . ($consulta->score ?? '—'));
        $this->info("Decisão humana: " . ($consulta->decisao_humana ?? 'pendente'));
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        $snapshot = $consulta->snapshot_interno ?? [];
        $dadosFormulario = [
            'cpf_cnpj'                     => $consulta->cpf_cnpj,
            'nome'                         => $consulta->nome,
            'telefone'                     => $consulta->telefone,
            'email'                        => $consulta->email,
            'valor_total'                  => (float) ($consulta->valor_total ?? 0),
            'parcelas_desejadas'           => (int) ($consulta->parcelas_desejadas ?? 1),
            'renda_declarada'              => $consulta->renda_declarada ? (float) $consulta->renda_declarada : null,
            'observacoes'                  => $consulta->observacoes,
            'autorizou_consultas_externas' => (bool) $consulta->autorizou_consultas_externas,
        ];

        // Recuperar dados Serasa originais se existirem
        $dadosSerasa = null;
        $actionsIa = $consulta->actions_ia ?? [];
        if (!empty($actionsIa['gate_decision']['serasa_data'])) {
            $dadosSerasa = $actionsIa['gate_decision']['serasa_data'];
        }

        $resultadosConsulta = [];
        $openAIService = app(SiricOpenAIService::class);

        foreach ($modelos as $modelo) {
            $this->newLine();
            $this->comment("  ▸ Testando: {$modelo}...");

            $resultado = [
                'modelo' => $modelo,
                'gate' => null,
                'relatorio' => null,
                'tokens_total' => 0,
                'tempo_total_ms' => 0,
                'erro' => null,
            ];

            // -- Gate Decision --
            if (!$this->option('somente-relatorio')) {
                $startGate = microtime(true);
                $gateResult = $this->chamarModelo(
                    $modelo,
                    $this->buildGateMessages($dadosFormulario, $snapshot),
                    'gate'
                );
                $resultado['tempo_gate_ms'] = round((microtime(true) - $startGate) * 1000);

                if ($gateResult['success']) {
                    $resultado['gate'] = $gateResult['data'];
                    $resultado['tokens_total'] += $gateResult['tokens'];
                    $gateParaRelatorio = $gateResult['data'];
                } else {
                    $resultado['erro'] = "Gate: {$gateResult['error']}";
                    $this->warn("    ✗ Gate falhou: {$gateResult['error']}");
                    $resultadosConsulta[$modelo] = $resultado;
                    continue;
                }
            } else {
                // Usar gate original
                $gateParaRelatorio = $actionsIa['gate_decision'] ?? [];
            }

            // -- Relatório Final --
            $startRel = microtime(true);
            $relResult = $this->chamarModelo(
                $modelo,
                $this->buildRelatorioMessages($dadosFormulario, $snapshot, $gateParaRelatorio, $dadosSerasa),
                'relatorio'
            );
            $resultado['tempo_relatorio_ms'] = round((microtime(true) - $startRel) * 1000);

            if ($relResult['success']) {
                $resultado['relatorio'] = $relResult['data'];
                $resultado['tokens_total'] += $relResult['tokens'];
            } else {
                $resultado['erro'] = "Relatório: {$relResult['error']}";
                $this->warn("    ✗ Relatório falhou: {$relResult['error']}");
            }

            $resultado['tempo_total_ms'] = ($resultado['tempo_gate_ms'] ?? 0) + ($resultado['tempo_relatorio_ms'] ?? 0);

            $resultadosConsulta[$modelo] = $resultado;

            // Mini output
            if ($resultado['relatorio']) {
                $rel = $resultado['relatorio'];
                $this->info(sprintf(
                    "    ✓ Rating: %s | Score: %s | Recomendação: %s | Tokens: %d | Tempo: %dms",
                    $rel['rating'] ?? '?',
                    $rel['score_final'] ?? '?',
                    $rel['recomendacao'] ?? '?',
                    $resultado['tokens_total'],
                    $resultado['tempo_total_ms']
                ));
            }
        }

        // Tabela comparativa da consulta
        $this->newLine();
        $this->info("  Comparação — Consulta #{$consulta->id}:");

        $headers = ['Métrica', 'Original'];
        foreach ($modelos as $m) $headers[] = $m;

        $rows = [];

        // Rating
        $row = ['Rating', $consulta->rating ?? '—'];
        foreach ($modelos as $m) {
            $row[] = $resultadosConsulta[$m]['relatorio']['rating'] ?? ($resultadosConsulta[$m]['erro'] ? 'ERRO' : '—');
        }
        $rows[] = $row;

        // Score
        $row = ['Score', $consulta->score ?? '—'];
        foreach ($modelos as $m) {
            $row[] = $resultadosConsulta[$m]['relatorio']['score_final'] ?? '—';
        }
        $rows[] = $row;

        // Recomendação
        $row = ['Recomendação', $consulta->recomendacao ?? '—'];
        foreach ($modelos as $m) {
            $row[] = $resultadosConsulta[$m]['relatorio']['recomendacao'] ?? '—';
        }
        $rows[] = $row;

        // Gate Score
        if (!$this->option('somente-relatorio')) {
            $gateOriginal = $actionsIa['gate_decision']['gate_score'] ?? '—';
            $row = ['Gate Score', $gateOriginal];
            foreach ($modelos as $m) {
                $row[] = $resultadosConsulta[$m]['gate']['gate_score'] ?? '—';
            }
            $rows[] = $row;

            // Need Serasa
            $serasaOrig = isset($actionsIa['gate_decision']['need_serasa'])
                ? ($actionsIa['gate_decision']['need_serasa'] ? 'SIM' : 'NÃO') : '—';
            $row = ['Need Serasa', $serasaOrig];
            foreach ($modelos as $m) {
                $val = $resultadosConsulta[$m]['gate']['need_serasa'] ?? null;
                $row[] = $val === null ? '—' : ($val ? 'SIM' : 'NÃO');
            }
            $rows[] = $row;
        }

        // Tokens
        $row = ['Tokens Total', '—'];
        foreach ($modelos as $m) {
            $row[] = number_format($resultadosConsulta[$m]['tokens_total']);
        }
        $rows[] = $row;

        // Tempo
        $row = ['Tempo (ms)', '—'];
        foreach ($modelos as $m) {
            $row[] = number_format($resultadosConsulta[$m]['tempo_total_ms']);
        }
        $rows[] = $row;

        // Decisão humana (benchmark real)
        $row = ['Decisão Humana', $consulta->decisao_humana ?? 'pendente'];
        foreach ($modelos as $m) $row[] = '—';
        $rows[] = $row;

        $this->table($headers, $rows);

        // Mostrar resumos executivos
        $this->newLine();
        $this->info("  Resumos Executivos:");
        foreach ($modelos as $m) {
            $resumo = $resultadosConsulta[$m]['relatorio']['resumo_executivo'] ?? null;
            if ($resumo) {
                $this->line("    [{$m}]: {$resumo}");
            }
        }

        // Mostrar fatores positivos/negativos
        foreach ($modelos as $m) {
            $rel = $resultadosConsulta[$m]['relatorio'] ?? null;
            if (!$rel) continue;

            $positivos = $rel['fatores_positivos'] ?? [];
            $negativos = $rel['fatores_negativos'] ?? [];

            if (!empty($positivos) || !empty($negativos)) {
                $this->newLine();
                $this->comment("    [{$m}] Fatores:");
                foreach ($positivos as $f) $this->line("      + {$f}");
                foreach ($negativos as $f) $this->line("      - {$f}");
            }
        }

        $this->resultados[$consulta->id] = [
            'consulta' => [
                'id' => $consulta->id,
                'nome' => $consulta->nome,
                'cpf_cnpj' => $consulta->cpf_cnpj,
                'valor_total' => $consulta->valor_total,
                'parcelas' => $consulta->parcelas_desejadas,
                'rating_original' => $consulta->rating,
                'score_original' => $consulta->score,
                'recomendacao_original' => $consulta->recomendacao,
                'decisao_humana' => $consulta->decisao_humana,
            ],
            'modelos' => $resultadosConsulta,
        ];
    }

    private function exibirResumo(array $modelos): void
    {
        $this->newLine(2);
        $this->info("╔══════════════════════════════════════════════════════╗");
        $this->info("║  RESUMO GERAL DA COMPARAÇÃO                        ║");
        $this->info("╚══════════════════════════════════════════════════════╝");
        $this->newLine();

        $totalConsultas = count($this->resultados);

        foreach ($modelos as $modelo) {
            $scores = [];
            $ratings = [];
            $recomendacoes = [];
            $tokensTotal = 0;
            $tempoTotal = 0;
            $erros = 0;
            $aderenciaDecisao = 0;
            $totalComDecisao = 0;
            $serasaSim = 0;

            foreach ($this->resultados as $consultaId => $data) {
                $r = $data['modelos'][$modelo] ?? null;
                if (!$r) { $erros++; continue; }

                if ($r['erro']) {
                    $erros++;
                    continue;
                }

                $rel = $r['relatorio'] ?? [];
                if (!empty($rel['score_final'])) $scores[] = (int) $rel['score_final'];
                if (!empty($rel['rating'])) $ratings[] = $rel['rating'];
                if (!empty($rel['recomendacao'])) $recomendacoes[] = $rel['recomendacao'];
                $tokensTotal += $r['tokens_total'];
                $tempoTotal += $r['tempo_total_ms'];

                // Aderência à decisão humana
                $decisao = $data['consulta']['decisao_humana'] ?? null;
                $recIA = $rel['recomendacao'] ?? null;
                if ($decisao && $recIA) {
                    $totalComDecisao++;
                    // Mapear: aprovado↔aprovado, negado↔negado, condicionado↔condicional
                    $match = ($decisao === $recIA)
                        || ($decisao === 'condicionado' && $recIA === 'condicional')
                        || ($decisao === 'aprovado' && $recIA === 'condicional'); // aprovado com condição = ok
                    if ($match) $aderenciaDecisao++;
                }

                // Gate Serasa
                if (($r['gate']['need_serasa'] ?? null) === true) $serasaSim++;
            }

            $avgScore = !empty($scores) ? round(array_sum($scores) / count($scores)) : '—';
            $avgTempo = $totalConsultas > 0 ? round($tempoTotal / ($totalConsultas - $erros)) : '—';
            $avgTokens = $totalConsultas > 0 ? round($tokensTotal / ($totalConsultas - $erros)) : '—';
            $aderencia = $totalComDecisao > 0 ? round(($aderenciaDecisao / $totalComDecisao) * 100) . '%' : '—';

            // Rating mais comum
            $ratingFreq = !empty($ratings) ? array_count_values($ratings) : [];
            arsort($ratingFreq);
            $ratingModa = !empty($ratingFreq) ? key($ratingFreq) : '—';

            // Recomendação mais comum
            $recFreq = !empty($recomendacoes) ? array_count_values($recomendacoes) : [];
            arsort($recFreq);
            $recModa = !empty($recFreq) ? key($recFreq) : '—';

            // Variância do score (consistência)
            $variancia = '—';
            if (count($scores) >= 2) {
                $mean = array_sum($scores) / count($scores);
                $sumSqDiff = array_sum(array_map(fn($s) => pow($s - $mean, 2), $scores));
                $variancia = round(sqrt($sumSqDiff / count($scores)));
            }

            $this->info("  {$modelo}:");
            $this->line("    Score médio: {$avgScore} (σ = {$variancia}) | Rating mais comum: {$ratingModa}");
            $this->line("    Recomendação mais comum: {$recModa}");
            $this->line("    Aderência à decisão humana: {$aderencia} ({$aderenciaDecisao}/{$totalComDecisao})");
            $this->line("    Serasa solicitado: {$serasaSim}/{$totalConsultas} consultas");
            $this->line("    Tokens médio: {$avgTokens} | Tempo médio: {$avgTempo}ms | Erros: {$erros}");
            $this->newLine();
        }

        // Análise de concordância entre modelos
        $this->info("  Concordância entre modelos (Rating):");
        $concordancia = [];
        foreach ($this->resultados as $consultaId => $data) {
            $ratingsConsulta = [];
            foreach ($modelos as $m) {
                $ratingsConsulta[$m] = $data['modelos'][$m]['relatorio']['rating'] ?? null;
            }
            $unicos = array_unique(array_filter($ratingsConsulta));
            $concordancia[$consultaId] = count($unicos) <= 1;
        }
        $totalConcordantes = count(array_filter($concordancia));
        $pctConcordancia = $totalConsultas > 0 ? round(($totalConcordantes / $totalConsultas) * 100) : 0;
        $this->line("    {$totalConcordantes}/{$totalConsultas} consultas com rating unânime ({$pctConcordancia}%)");

        if ($pctConcordancia < 70) {
            $this->warn("    ⚠ Baixa concordância — modelos divergem significativamente.");
            $this->warn("    Considere: prompts mais restritivos ou scoring determinístico.");
        }
    }

    private function salvarResultados(): void
    {
        $path = storage_path('app/siric_comparacao_' . date('Y-m-d_His') . '.json');
        file_put_contents($path, json_encode($this->resultados, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $this->info("Resultados salvos em: {$path}");
    }

    // ========================================================================
    // CHAMADA À API (reutiliza prompts do SiricOpenAIService)
    // ========================================================================

    private function chamarModelo(string $modelo, array $messages, string $etapa): array
    {
        try {
            $isReasoning = in_array($modelo, ['o3', 'o4-mini', 'o3-mini', 'o1', 'o1-mini']);

            $payload = [
                'model' => $modelo,
                'messages' => $messages,
                'response_format' => ['type' => 'json_object'],
            ];

            if ($isReasoning) {
                // Modelos reasoning usam max_completion_tokens, não max_tokens
                $payload['max_completion_tokens'] = 4000;
                // Não suportam temperature
            } else {
                $payload['max_completion_tokens'] = 4000;
                $payload['temperature'] = 0.3;
            }

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type' => 'application/json',
            ])
            ->timeout(180) // mais tempo para reasoning
            ->post('https://api.openai.com/v1/chat/completions', $payload);

            if (!$response->successful()) {
                $error = $response->json('error.message', $response->body());
                return ['success' => false, 'data' => null, 'tokens' => 0, 'error' => "API {$response->status()}: {$error}"];
            }

            $json = $response->json();
            $content = $json['choices'][0]['message']['content'] ?? '';
            $usage = $json['usage'] ?? [];

            // Limpar wrappers markdown
            $content = preg_replace('/^```json\s*/i', '', trim($content));
            $content = preg_replace('/\s*```$/i', '', $content);

            $parsed = json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return ['success' => false, 'data' => null, 'tokens' => 0, 'error' => 'JSON inválido: ' . json_last_error_msg()];
            }

            return [
                'success' => true,
                'data' => $parsed,
                'tokens' => $usage['total_tokens'] ?? 0,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'data' => null, 'tokens' => 0, 'error' => $e->getMessage()];
        }
    }

    // ========================================================================
    // CONSTRUÇÃO DOS PROMPTS (replicados do SiricOpenAIService)
    // ========================================================================

    private function buildGateMessages(array $form, array $snapshot): array
    {
        $metricas = $snapshot['metricas'] ?? [];
        $contasReceber = $snapshot['contas_receber'] ?? [];
        $movimentos = $snapshot['movimentos'] ?? [];
        $processos = $snapshot['processos'] ?? [];
        $leads = $snapshot['leads'] ?? [];
        $clienteInfo = $snapshot['cliente'] ?? [];
        $conversasWa = $snapshot['conversas_whatsapp'] ?? null;

        $valorPretendido = (float) ($form['valor_total'] ?? 0);
        $numParcelas = (int) ($form['parcelas_desejadas'] ?? 1);
        $parcelaEstimada = $numParcelas > 0 ? round($valorPretendido / $numParcelas, 2) : $valorPretendido;

        $systemPrompt = "Você é o Analista SIRIC do escritório Mayer Advogados.\n\n"
            . "OBJETIVO: Decidir se vale consultar Serasa (custo R\$17,00) e/ou fazer web_intel, antes de gerar o relatório de crédito.\n\n"
            . "REGRAS INVIOLÁVEIS:\n"
            . "- Decisão de crédito é SEMPRE humana; você só recomenda e justifica.\n"
            . "- Use APENAS dados fornecidos (formulário + interno + resultados de tools).\n"
            . "- NÃO use atributos sensíveis (raça/religião/política/saúde) nem inferências disso.\n"
            . "- Responda EXCLUSIVAMENTE em JSON válido, sem markdown.\n\n"
            . "CÁLCULO DO gate_score (0-100+):\n\n"
            . "HISTÓRICO:\n"
            . "+35 se sem histórico interno (cliente não encontrado no BD)\n"
            . "+40 se inadimplência atual (saldo vencido > 0)\n"
            . "+25 se max_dias_atraso >= 15\n"
            . "+25 se quebrou acordo anterior (parcelas canceladas existentes)\n\n"
            . "BOM HISTÓRICO (desconto):\n"
            . "-30 se relacionamento >= 12 meses E >= 6 parcelas pagas sem atraso E saldo_vencido = 0\n\n"
            . "RENDA:\n"
            . "+25 se renda ausente/não declarada\n"
            . "+15 se (parcela_estimada / renda) > 0.20\n"
            . "+30 se (parcela_estimada / renda) > 0.30\n"
            . "+25 se (renda - despesas_estimadas) < parcela_estimada\n\n"
            . "COMPLETUDE DE DADOS:\n"
            . "Campos-chave: telefone, email, endereço completo, profissão, empregador, tempo_emprego\n"
            . "+15 se faltam >= 2 campos-chave\n"
            . "+30 se faltam >= 4 campos-chave\n\n"
            . "CONSISTÊNCIA:\n"
            . "+25 se telefone/email divergem dos dados internos para o mesmo CPF/CNPJ\n\n"
            . "EXPOSIÇÃO (valor_pretendido):\n"
            . "+15 se valor_pretendido >= R\$800\n"
            . "+25 se valor_pretendido >= R\$2.000\n"
            . "-10 se valor_pretendido < R\$400\n\n"
            . "DECISÃO:\n"
            . "- Se autorizacao_consulta_externa = false OU cpf_cnpj inválido => need_serasa = false\n"
            . "- Se gate_score >= 30 => need_serasa = true\n"
            . "- Se 15 <= gate_score < 30 => need_web_intel = true primeiro; após web, se inconsistência factual => gate_score += 20 e redecide\n"
            . "- Se gate_score < 15 => need_serasa = false, need_web_intel = false\n\n"
            . "FORMATO DE RESPOSTA (JSON puro):\n"
            . "{\n"
            . "  \"gate_score\": <número>,\n"
            . "  \"gate_score_breakdown\": {\n"
            . "    \"historico\": <número>,\n"
            . "    \"bom_historico_desconto\": <número>,\n"
            . "    \"renda\": <número>,\n"
            . "    \"completude\": <número>,\n"
            . "    \"consistencia\": <número>,\n"
            . "    \"exposicao\": <número>\n"
            . "  },\n"
            . "  \"need_serasa\": <boolean>,\n"
            . "  \"need_web_intel\": <boolean>,\n"
            . "  \"justificativa\": \"<texto curto>\",\n"
            . "  \"riscos_preliminares\": [\"<risco1>\", \"<risco2>\"],\n"
            . "  \"dados_faltantes\": [\"<campo1>\", \"<campo2>\"]\n"
            . "}";

        $userContent = json_encode([
            'formulario' => [
                'cpf_cnpj' => $form['cpf_cnpj'] ?? '',
                'nome' => $form['nome'] ?? '',
                'valor_pretendido' => $valorPretendido,
                'num_parcelas' => $numParcelas,
                'parcela_estimada' => $parcelaEstimada,
                'renda_declarada' => $form['renda_declarada'] ?? null,
                'observacoes' => $form['observacoes'] ?? '',
                'autorizacao_consulta_externa' => (bool) ($form['autorizou_consultas_externas'] ?? false),
            ],
            'dados_internos' => [
                'cliente_encontrado' => !empty($clienteInfo),
                'cliente_info' => $clienteInfo,
                'metricas_financeiras' => $metricas,
                'contas_receber' => [
                    'total_registros' => $contasReceber['total_registros'] ?? 0,
                    'total_pago' => $contasReceber['total_pago'] ?? 0,
                    'saldo_aberto' => $contasReceber['saldo_aberto'] ?? 0,
                    'saldo_vencido' => $contasReceber['saldo_vencido'] ?? 0,
                    'qtd_atrasos' => $contasReceber['qtd_atrasos'] ?? 0,
                    'max_dias_atraso' => $contasReceber['max_dias_atraso'] ?? 0,
                    'media_dias_atraso' => $contasReceber['media_dias_atraso'] ?? 0,
                ],
                'movimentos' => [
                    'total' => $movimentos['total_registros'] ?? $movimentos['total'] ?? 0,
                    'total_creditos' => $movimentos['total_receita'] ?? $movimentos['total_creditos'] ?? 0,
                    'total_debitos' => $movimentos['total_despesa'] ?? $movimentos['total_debitos'] ?? 0,
                ],
                'processos' => [
                    'total_ativos' => $processos['total_ativos'] ?? 0,
                    'total_inativos' => $processos['total_inativos'] ?? 0,
                ],
                'leads' => $leads,
            ],
            'conversas_whatsapp' => $conversasWa ? [
                'total_conversas' => $conversasWa['total_conversas'] ?? 0,
                'resumo' => $conversasWa['resumo'] ?? 'Nenhuma conversa encontrada',
            ] : null,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => "Analise os seguintes dados e calcule o gate_score:\n\n" . $userContent],
        ];
    }

    private function buildRelatorioMessages(array $form, array $snapshot, array $gateResult, ?array $dadosSerasa): array
    {
        $metricas = $snapshot['metricas'] ?? [];
        $contasReceber = $snapshot['contas_receber'] ?? [];
        $movimentos = $snapshot['movimentos'] ?? [];
        $processos = $snapshot['processos'] ?? [];
        $leads = $snapshot['leads'] ?? [];
        $clienteInfo = $snapshot['cliente'] ?? [];
        $conversasWa = $snapshot['conversas_whatsapp'] ?? null;

        $valorPretendido = (float) ($form['valor_total'] ?? 0);
        $numParcelas = (int) ($form['parcelas_desejadas'] ?? 1);
        $parcelaEstimada = $numParcelas > 0 ? round($valorPretendido / $numParcelas, 2) : $valorPretendido;

        $systemPrompt = "Você é o Analista SIRIC do escritório Mayer Advogados.\n\n"
            . "OBJETIVO: Gerar relatório completo de análise de crédito para parcelamento de honorários advocatícios.\n\n"
            . "REGRAS INVIOLÁVEIS:\n"
            . "- Decisão de crédito é SEMPRE humana; você gera análise e RECOMENDAÇÃO.\n"
            . "- Use APENAS dados fornecidos. Não invente dados.\n"
            . "- NÃO use atributos sensíveis (raça/religião/política/saúde).\n"
            . "- Score de 0 a 1000. Rating de A (melhor) a E (pior).\n"
            . "- Responda EXCLUSIVAMENTE em JSON válido, sem markdown.\n\n"
            . "CRITÉRIOS DE SCORE:\n"
            . "- A (800-1000): Excelente histórico, baixo risco, aprovação recomendada\n"
            . "- B (600-799): Bom histórico, risco moderado-baixo, aprovação com monitoramento\n"
            . "- C (400-599): Histórico misto, risco moderado, aprovação condicional sugerida\n"
            . "- D (200-399): Histórico problemático, risco alto, condições restritivas ou negação\n"
            . "- E (0-199): Sem dados ou histórico muito negativo, negação recomendada\n\n"
            . "FATORES DE ANÁLISE:\n"
            . "1. Histórico de pagamentos (pontualidade, atrasos, inadimplência)\n"
            . "2. Capacidade de pagamento (renda vs compromisso, comprometimento mensal)\n"
            . "3. Relacionamento com escritório (tempo, frequência, volume)\n"
            . "4. Processos ativos (exposição, complexidade)\n"
            . "5. Completude de dados cadastrais\n"
            . "6. Dados Serasa/bureau (se disponíveis)\n"
            . "7. Conversas WhatsApp (tom, comprometimento, histórico de comunicação)\n\n"
            . "FORMATO DE RESPOSTA (JSON puro):\n"
            . "{\n"
            . "  \"score_final\": <0-1000>,\n"
            . "  \"rating\": \"<A|B|C|D|E>\",\n"
            . "  \"resumo_executivo\": \"<texto conciso 2-3 frases>\",\n"
            . "  \"recomendacao\": \"<aprovado|negado|condicional>\",\n"
            . "  \"comprometimento_max_sugerido\": \"<percentual ou valor>\",\n"
            . "  \"parcelas_max_sugeridas\": <número>,\n"
            . "  \"fatores_positivos\": [\"<fator1>\", \"<fator2>\"],\n"
            . "  \"fatores_negativos\": [\"<fator1>\", \"<fator2>\"],\n"
            . "  \"analise_detalhada\": {\n"
            . "    \"historico_pagamentos\": \"<análise>\",\n"
            . "    \"capacidade_pagamento\": \"<análise>\",\n"
            . "    \"riscos_identificados\": \"<riscos>\",\n"
            . "    \"pontos_positivos\": \"<fatores favoráveis>\",\n"
            . "    \"relacionamento_escritorio\": \"<análise>\"\n"
            . "  },\n"
            . "  \"alertas\": [\"<alerta1>\", \"<alerta2>\"]\n"
            . "}";

        $dadosCompletos = [
            'formulario' => [
                'cpf_cnpj' => $form['cpf_cnpj'] ?? '',
                'nome' => $form['nome'] ?? '',
                'valor_pretendido' => $valorPretendido,
                'num_parcelas' => $numParcelas,
                'parcela_estimada' => $parcelaEstimada,
                'renda_declarada' => $form['renda_declarada'] ?? null,
                'observacoes' => $form['observacoes'] ?? '',
            ],
            'dados_internos' => [
                'cliente' => $clienteInfo,
                'metricas' => $metricas,
                'contas_receber' => $contasReceber,
                'movimentos' => $movimentos,
                'processos' => $processos,
                'leads' => $leads,
            ],
            'gate_decision' => [
                'gate_score' => $gateResult['gate_score'] ?? null,
                'riscos_preliminares' => $gateResult['riscos_preliminares'] ?? [],
                'dados_faltantes' => $gateResult['dados_faltantes'] ?? [],
            ],
        ];

        if ($dadosSerasa) {
            $dadosCompletos['dados_serasa'] = $dadosSerasa;
        }

        if ($conversasWa && !empty($conversasWa['mensagens'])) {
            $dadosCompletos['conversas_whatsapp'] = [
                'total_conversas' => $conversasWa['total_conversas'] ?? 0,
                'total_mensagens' => $conversasWa['total_mensagens'] ?? 0,
                'periodo' => $conversasWa['periodo'] ?? 'N/A',
                'resumo_interacoes' => $conversasWa['resumo'] ?? '',
                'ultimas_mensagens' => array_slice($conversasWa['mensagens'] ?? [], -30),
            ];
        }

        $userContent = json_encode($dadosCompletos, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => "Gere o relatório de análise de crédito com base nos seguintes dados:\n\n" . $userContent],
        ];
    }
}
