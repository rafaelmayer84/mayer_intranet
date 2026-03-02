<?php

namespace App\Console\Commands;

use App\Models\JustusJurisprudencia;
use App\Services\Justus\JustusJurisprudenciaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class JustusValidateCommand extends Command
{
    protected $signature = 'justus:validate {--fix : Corrigir problemas encontrados quando possível} {--verbose-fails : Mostrar amostras dos registros com problema}';
    protected $description = 'Testes de validação e verdade da base de jurisprudência JUSTUS';

    private int $passed = 0;
    private int $failed = 0;
    private int $warnings = 0;
    private array $report = [];

    public function handle(): int
    {
        $this->info('');
        $this->info('=== JUSTUS — TESTES DE VALIDACAO E VERDADE ===');
        $this->info('=== Base de Jurisprudencia STJ + TJSC       ===');
        $this->info('');

        $start = microtime(true);

        $this->info('--- CATEGORIA 1: INTEGRIDADE ESTRUTURAL ---');
        $this->newLine();
        $this->test01_fulltext_indexes();
        $this->test02_campos_obrigatorios_nulos();
        $this->test03_duplicatas_external_id();
        $this->test04_coluna_stj_id_bug();

        $this->newLine();
        $this->info('--- CATEGORIA 2: QUALIDADE DOS DADOS ---');
        $this->newLine();
        $this->test05_ementas_curtas_ou_lixo();
        $this->test06_datas_fora_de_faixa();
        $this->test07_area_direito_preenchida();
        $this->test08_numero_processo_formato();

        $this->newLine();
        $this->info('--- CATEGORIA 3: LOGICA DE BUSCA (FULLTEXT) ---');
        $this->newLine();
        $this->test09_busca_termo_generico();
        $this->test10_busca_temas_escritorio();
        $this->test11_busca_retorna_ambos_tribunais();

        $this->newLine();
        $this->info('--- CATEGORIA 4: CONSISTENCIA DA IMPORTACAO ---');
        $this->newLine();
        $this->test12_stj_datasets_cobertura();
        $this->test13_tjsc_cobertura_temporal();
        $this->test14_prompt_injection_service();

        $elapsed = round(microtime(true) - $start, 2);

        $this->newLine();
        $this->info('=== RELATORIO FINAL ===');
        $this->info("Passou:   {$this->passed}");
        $this->info("Falhou:   {$this->failed}");
        $this->info("Alertas:  {$this->warnings}");
        $this->info("Tempo:    {$elapsed}s");
        $this->newLine();

        if (!empty($this->report)) {
            $this->info('DETALHAMENTO:');
            foreach ($this->report as $item) {
                $icon = $item['type'] === 'FAIL' ? 'FALHA' : 'ALERTA';
                $this->line("  [{$icon}] [{$item['test']}] {$item['message']}");
                if (!empty($item['detail']) && $this->option('verbose-fails')) {
                    foreach ((array) $item['detail'] as $d) {
                        $this->line("     > {$d}");
                    }
                }
                if (!empty($item['fix'])) {
                    $this->line("     FIX: {$item['fix']}");
                }
            }
        }

        Log::info('justus:validate finalizado', [
            'passed' => $this->passed, 'failed' => $this->failed,
            'warnings' => $this->warnings, 'elapsed' => $elapsed,
        ]);

        return $this->failed > 0 ? 1 : 0;
    }

    private function test01_fulltext_indexes(): void
    {
        $testName = 'T01-FULLTEXT-INDEXES';
        $this->info("  [{$testName}] Verificando indexes FULLTEXT...");
        $indexes = DB::select("SHOW INDEX FROM justus_jurisprudencia WHERE Index_type = 'FULLTEXT'");
        $indexedCols = collect($indexes)->pluck('Column_name')->unique()->toArray();
        if (!in_array('ementa', $indexedCols)) {
            $this->logFail($testName, 'Coluna ementa NAO possui FULLTEXT index', null, 'ALTER TABLE justus_jurisprudencia ADD FULLTEXT(ementa)');
            return;
        }
        $indexNames = collect($indexes)->pluck('Key_name')->unique();
        if ($indexNames->count() > 2) {
            $this->logWarn($testName, "Existem {$indexNames->count()} FULLTEXT indexes — possivel redundancia: " . $indexNames->implode(', '));
        } else {
            $this->logPass($testName, "FULLTEXT OK — indexes: " . $indexNames->implode(', '));
        }
    }

    private function test02_campos_obrigatorios_nulos(): void
    {
        $testName = 'T02-CAMPOS-OBRIGATORIOS';
        $this->info("  [{$testName}] Verificando campos criticos sem NULL...");
        $campos = ['ementa', 'tribunal', 'relator', 'tipo_decisao', 'numero_processo', 'external_id'];
        $total = JustusJurisprudencia::count();
        $problems = [];
        foreach ($campos as $campo) {
            $nulls = DB::table('justus_jurisprudencia')->whereNull($campo)->count();
            $empties = DB::table('justus_jurisprudencia')->where($campo, '')->count();
            if ($nulls > 0 || $empties > 0) {
                $problems[] = "{$campo}: {$nulls} nulls + {$empties} vazios (de {$total})";
            }
        }
        if (empty($problems)) {
            $this->logPass($testName, "6 campos criticos 100% preenchidos ({$total} registros)");
        } else {
            $this->logFail($testName, 'Campos com dados faltando', $problems);
        }
    }

    private function test03_duplicatas_external_id(): void
    {
        $testName = 'T03-DUPLICATAS-EXTERNAL-ID';
        $this->info("  [{$testName}] Verificando unicidade de external_id...");
        $dups = DB::selectOne('SELECT COUNT(*) - COUNT(DISTINCT external_id) as dups FROM justus_jurisprudencia');
        if ($dups->dups == 0) {
            $this->logPass($testName, 'Zero duplicatas de external_id');
        } else {
            $samples = DB::select('SELECT external_id, COUNT(*) as qty FROM justus_jurisprudencia GROUP BY external_id HAVING qty > 1 LIMIT 5');
            $detail = array_map(fn($s) => "{$s->external_id} ({$s->qty}x)", $samples);
            $this->logFail($testName, "{$dups->dups} duplicatas de external_id", $detail);
        }
    }

    private function test04_coluna_stj_id_bug(): void
    {
        $testName = 'T04-BUG-STJ-ID-VS-EXTERNAL-ID';
        $this->info("  [{$testName}] Verificando consistencia stj_id/external_id...");
        $columns = collect(DB::select('SHOW COLUMNS FROM justus_jurisprudencia'))->pluck('Field')->toArray();
        $hasStjId = in_array('stj_id', $columns);
        $hasExternalId = in_array('external_id', $columns);
        if ($hasStjId && $hasExternalId) {
            $stjWithStjId = DB::table('justus_jurisprudencia')->where('tribunal', 'STJ')->whereNotNull('stj_id')->count();
            $stjTotal = DB::table('justus_jurisprudencia')->where('tribunal', 'STJ')->count();
            $this->logWarn($testName, "Coluna stj_id existe separada de external_id. STJ com stj_id: {$stjWithStjId}/{$stjTotal}. Upsert por stj_id funciona mas inconsistente com TJSC");
        } elseif (!$hasStjId && $hasExternalId) {
            $commandPath = app_path('Console/Commands/JustusSyncStjCommand.php');
            if (file_exists($commandPath)) {
                $code = file_get_contents($commandPath);
                if (str_contains($code, "'stj_id'")) {
                    $this->logFail($testName, 'CRITICO: Command referencia stj_id que NAO existe na tabela. Upsert pode criar duplicatas.', null, 'Substituir stj_id por external_id no upsertBatch');
                } else {
                    $this->logPass($testName, 'Command nao referencia stj_id — OK');
                }
            }
        } else {
            $this->logPass($testName, 'Estrutura de colunas consistente');
        }
    }

    private function test05_ementas_curtas_ou_lixo(): void
    {
        $testName = 'T05-QUALIDADE-EMENTAS';
        $this->info("  [{$testName}] Verificando qualidade das ementas...");
        $total = JustusJurisprudencia::count();
        $curtas = DB::table('justus_jurisprudencia')->whereRaw('CHAR_LENGTH(ementa) < 50')->count();
        $comHtml = DB::table('justus_jurisprudencia')->whereRaw("ementa REGEXP '<[a-zA-Z/][^>]*>'")->count();
        $encodingQuebrado = DB::table('justus_jurisprudencia')->whereRaw("ementa LIKE '%Ã£%' OR ementa LIKE '%Ã§%' OR ementa LIKE '%Ã©%' OR ementa LIKE '%Ãµ%'")->count();
        $problems = [];
        if ($curtas > 0) { $problems[] = "Ementas < 50 chars: {$curtas} (" . round($curtas/$total*100,2) . "%)"; }
        if ($comHtml > 0) { $problems[] = "Ementas com HTML: {$comHtml} (" . round($comHtml/$total*100,2) . "%)"; }
        if ($encodingQuebrado > 0) { $problems[] = "Encoding quebrado: {$encodingQuebrado} (" . round($encodingQuebrado/$total*100,2) . "%)"; }
        if (empty($problems)) {
            $this->logPass($testName, "Qualidade OK — {$total} ementas validadas");
        } elseif ($curtas > $total*0.01 || $comHtml > $total*0.005 || $encodingQuebrado > $total*0.01) {
            $this->logFail($testName, 'Problemas de qualidade significativos', $problems);
        } else {
            $this->logWarn($testName, 'Problemas menores de qualidade', $problems);
        }
    }

    private function test06_datas_fora_de_faixa(): void
    {
        $testName = 'T06-DATAS-PLAUSIBILIDADE';
        $this->info("  [{$testName}] Verificando faixa de datas...");
        $stjAntigos = DB::table('justus_jurisprudencia')->where('tribunal','STJ')->where('data_decisao','<','2010-01-01')->count();
        $futuros = DB::table('justus_jurisprudencia')->where('data_decisao','>',now()->format('Y-m-d'))->count();
        $nulls = DB::table('justus_jurisprudencia')->whereNull('data_decisao')->count();
        $tjscAntes2020 = DB::table('justus_jurisprudencia')->where('tribunal','TJSC')->where('data_decisao','<','2020-01-01')->count();
        $problems = [];
        if ($stjAntigos > 0) $problems[] = "STJ data < 2010: {$stjAntigos}";
        if ($futuros > 0) $problems[] = "Datas futuras: {$futuros}";
        if ($nulls > 0) $problems[] = "data_decisao NULL: {$nulls}";
        if ($tjscAntes2020 > 0) $problems[] = "TJSC data < 2020: {$tjscAntes2020}";
        if (empty($problems)) {
            $this->logPass($testName, sprintf('Datas OK — STJ: %s a %s | TJSC: %s a %s',
                DB::table('justus_jurisprudencia')->where('tribunal','STJ')->min('data_decisao'),
                DB::table('justus_jurisprudencia')->where('tribunal','STJ')->max('data_decisao'),
                DB::table('justus_jurisprudencia')->where('tribunal','TJSC')->min('data_decisao'),
                DB::table('justus_jurisprudencia')->where('tribunal','TJSC')->max('data_decisao')
            ));
        } else {
            $this->logWarn($testName, 'Datas atipicas', $problems);
        }
    }

    private function test07_area_direito_preenchida(): void
    {
        $testName = 'T07-AREA-DIREITO';
        $this->info("  [{$testName}] Verificando classificacao por area...");
        $stats = DB::table('justus_jurisprudencia')->selectRaw('tribunal, area_direito, COUNT(*) as total')->groupBy('tribunal','area_direito')->orderBy('tribunal')->orderByDesc('total')->get();
        $total = JustusJurisprudencia::count();
        $totalNulls = $stats->whereNull('area_direito')->sum('total');
        $pctNulls = round($totalNulls/$total*100, 1);
        $detail = [];
        foreach ($stats->groupBy('tribunal') as $tribunal => $rows) {
            $parts = $rows->map(fn($r) => ($r->area_direito ?? 'NULL').":".$r->total)->implode(', ');
            $detail[] = "{$tribunal}: {$parts}";
        }
        if ($pctNulls > 30) {
            $this->logFail($testName, "{$pctNulls}% sem area ({$totalNulls}/{$total})", $detail);
        } elseif ($pctNulls > 10) {
            $this->logWarn($testName, "{$pctNulls}% sem area ({$totalNulls}/{$total})", $detail);
        } else {
            $this->logPass($testName, "Classificacao OK — {$pctNulls}% sem area ({$totalNulls}/{$total})");
        }
    }

    private function test08_numero_processo_formato(): void
    {
        $testName = 'T08-FORMATO-NUMERO-PROCESSO';
        $this->info("  [{$testName}] Verificando formato numero de processo...");
        $tjscTotal = DB::table('justus_jurisprudencia')->where('tribunal','TJSC')->count();
        $tjscCnj = DB::table('justus_jurisprudencia')->where('tribunal','TJSC')->whereRaw("numero_processo REGEXP '^[0-9]{7}-[0-9]{2}\\\\.[0-9]{4}\\\\.[0-9]\\\\.[0-9]{2}\\\\.[0-9]{4}$'")->count();
        $tjscPct = $tjscTotal > 0 ? round($tjscCnj/$tjscTotal*100, 1) : 0;
        $stjTotal = DB::table('justus_jurisprudencia')->where('tribunal','STJ')->count();
        $stjDig = DB::table('justus_jurisprudencia')->where('tribunal','STJ')->whereRaw("numero_processo REGEXP '^[0-9]+$'")->count();
        $stjPct = $stjTotal > 0 ? round($stjDig/$stjTotal*100, 1) : 0;
        $problems = [];
        if ($tjscPct < 95) $problems[] = "TJSC CNJ: {$tjscPct}% (" . ($tjscTotal-$tjscCnj) . " fora do padrao)";
        if ($stjPct < 95) $problems[] = "STJ numerico: {$stjPct}% (" . ($stjTotal-$stjDig) . " fora do padrao)";
        if (empty($problems)) {
            $this->logPass($testName, "Formato OK — TJSC CNJ: {$tjscPct}% | STJ numerico: {$stjPct}%");
        } else {
            $this->logWarn($testName, 'Processos com formato atipico', $problems);
        }
    }

    private function test09_busca_termo_generico(): void
    {
        $testName = 'T09-BUSCA-GENERICA';
        $this->info("  [{$testName}] Testando busca fulltext...");
        $termos = [
            'dano moral indenização' => 'Dano moral',
            'contrato inadimplemento rescisão' => 'Contratos',
            'consumidor defeito produto' => 'Consumidor',
            'recurso especial provimento' => 'Recurso especial',
            'execução fiscal penhora' => 'Execucao fiscal',
        ];
        $problems = [];
        foreach ($termos as $query => $label) {
            $results = JustusJurisprudencia::searchRelevant($query, 3);
            if ($results->isEmpty()) {
                $problems[] = "{$label}: ZERO resultados";
            } else {
                $words = explode(' ', $query);
                $ementaLower = mb_strtolower($results->first()->ementa);
                $match = false;
                foreach ($words as $w) {
                    if (mb_strlen($w) >= 4 && str_contains($ementaLower, mb_strtolower($w))) { $match = true; break; }
                }
                if (!$match) $problems[] = "{$label}: resultado nao contem termos da busca";
            }
        }
        if (empty($problems)) {
            $this->logPass($testName, '5/5 buscas genericas com resultados relevantes');
        } else {
            $this->logFail($testName, 'Busca fulltext com problemas', $problems);
        }
    }

    private function test10_busca_temas_escritorio(): void
    {
        $testName = 'T10-BUSCA-TEMAS-MAYER';
        $this->info("  [{$testName}] Testando temas do escritorio...");
        $temas = [
            'responsabilidade civil acidente transito' => 'Resp. civil transito',
            'cobranca inadimplencia contrato honorarios' => 'Cobranca/honorarios',
            'embargos declaracao omissao contradicao' => 'Embargos declaracao',
            'alimentos fixacao revisao' => 'Alimentos',
            'inventario partilha bens heranca' => 'Inventario/sucessoes',
            'trabalhista verbas rescisorias demissao' => 'Trabalhista',
            'tributario ICMS creditamento' => 'Tributario',
        ];
        $semResultado = [];
        foreach ($temas as $query => $label) {
            $results = JustusJurisprudencia::searchRelevant($query, 3);
            if ($results->isEmpty()) $semResultado[] = $label;
        }
        $totalTemas = count($temas);
        $encontrados = $totalTemas - count($semResultado);
        if ($encontrados >= $totalTemas - 1) {
            $this->logPass($testName, "{$encontrados}/{$totalTemas} temas com resultados");
        } elseif ($encontrados >= $totalTemas * 0.5) {
            $this->logWarn($testName, "{$encontrados}/{$totalTemas} temas. Sem resultado: " . implode(', ', $semResultado));
        } else {
            $this->logFail($testName, "Apenas {$encontrados}/{$totalTemas} temas", ["Sem resultado: " . implode(', ', $semResultado)]);
        }
    }

    private function test11_busca_retorna_ambos_tribunais(): void
    {
        $testName = 'T11-BUSCA-AMBOS-TRIBUNAIS';
        $this->info("  [{$testName}] Testando cobertura de tribunais na busca...");
        $results = JustusJurisprudencia::searchRelevant('dano moral indenizacao responsabilidade', 10);
        $tribunais = $results->pluck('tribunal')->unique()->toArray();
        if (count($tribunais) >= 2) {
            $porTribunal = $results->groupBy('tribunal')->map->count();
            $this->logPass($testName, "Ambos tribunais (" . $porTribunal->map(fn($c,$t)=>"{$t}:{$c}")->implode(', ') . ")");
        } elseif (count($tribunais) === 1) {
            $this->logWarn($testName, "Apenas {$tribunais[0]} — desbalanceamento por volume (TJSC 8x STJ)");
        } else {
            $this->logFail($testName, 'Nenhum resultado para tema generico');
        }
    }

    private function test12_stj_datasets_cobertura(): void
    {
        $testName = 'T12-STJ-DATASETS';
        $this->info("  [{$testName}] Verificando cobertura datasets STJ...");
        $configured = config('justus.stj_datasets', []);
        $imported = DB::table('justus_jurisprudencia')->where('tribunal','STJ')->distinct()->pluck('fonte_dataset')->toArray();
        $missing = array_diff($configured, $imported);
        $detail = [];
        foreach ($imported as $ds) {
            $count = DB::table('justus_jurisprudencia')->where('tribunal','STJ')->where('fonte_dataset',$ds)->count();
            $detail[] = "{$ds}: {$count}";
        }
        if (empty($missing)) {
            $this->logPass($testName, count($imported)."/".count($configured)." datasets importados");
        } else {
            $this->logFail($testName, count($missing)." datasets faltando: ".implode(', ',$missing), $detail);
        }
    }

    private function test13_tjsc_cobertura_temporal(): void
    {
        $testName = 'T13-TJSC-TEMPORAL';
        $this->info("  [{$testName}] Verificando cobertura temporal TJSC...");
        $porMes = DB::table('justus_jurisprudencia')->where('tribunal','TJSC')
            ->selectRaw("DATE_FORMAT(data_decisao, '%Y-%m') as mes, COUNT(*) as total")
            ->groupBy('mes')->orderBy('mes')->get();
        if ($porMes->isEmpty()) { $this->logFail($testName, 'Nenhum registro TJSC'); return; }
        $minMes = $porMes->first()->mes;
        $maxMes = $porMes->last()->mes;
        $current = Carbon::parse($minMes.'-01');
        $end = Carbon::parse($maxMes.'-01');
        $esperados = [];
        while ($current->lte($end)) { $esperados[] = $current->format('Y-m'); $current->addMonth(); }
        $comDados = $porMes->pluck('mes')->toArray();
        $semDados = array_diff($esperados, $comDados);
        $media = $porMes->avg('total');
        $detail = $porMes->map(fn($m) => "{$m->mes}: {$m->total}")->toArray();
        if (empty($semDados)) {
            $this->logPass($testName, "Cobertura OK — {$minMes} a {$maxMes}, ".count($comDados)." meses, media ".round($media)."/mes");
        } else {
            $this->logWarn($testName, count($semDados)." meses sem dados: ".implode(', ',$semDados), $detail);
        }
    }

    private function test14_prompt_injection_service(): void
    {
        $testName = 'T14-SERVICE-PROMPT';
        $this->info("  [{$testName}] Testando JustusJurisprudenciaService...");
        try {
            $service = app(JustusJurisprudenciaService::class);
            $conv = new \App\Models\JustusConversation();

            // Query vazia
            $r1 = $service->searchForPrompt($conv, '');
            if ($r1['found'] !== false) { $this->logFail($testName, 'Query vazia retornou found=true'); return; }

            // Query stopwords
            $service->searchForPrompt($conv, 'o que e isso');

            // Query valida
            $r3 = $service->searchForPrompt($conv, 'dano moral indenizacao valor quantum');
            if ($r3['found'] && empty($r3['context'])) { $this->logFail($testName, 'found=true mas contexto vazio'); return; }

            if ($r3['found']) {
                $ctx = $r3['context'];
                $hasHeader = str_contains($ctx, 'JURISPRUD');
                $hasFooter = str_contains($ctx, 'REGRA ABSOLUTA');
                if (!$hasHeader || !$hasFooter) {
                    $this->logWarn($testName, 'Contexto sem marcadores de seguranca');
                } else {
                    $this->logPass($testName, "Service OK — {$r3['count']} refs, ".strlen($ctx)." chars, marcadores presentes");
                }
            } else {
                $this->logWarn($testName, 'Query dano moral sem resultados — verificar FULLTEXT');
            }

            $service->getStats();
        } catch (\Throwable $e) {
            $this->logFail($testName, 'Excecao: ' . mb_substr($e->getMessage(), 0, 200));
        }
    }

    private function logPass(string $test, string $msg): void
    {
        $this->passed++;
        $this->line("  PASS [{$test}] {$msg}");
    }

    private function logFail(string $test, string $msg, ?array $detail = null, ?string $fix = null): void
    {
        $this->failed++;
        $this->error("  FAIL [{$test}] {$msg}");
        $this->report[] = ['test' => $test, 'type' => 'FAIL', 'message' => $msg, 'detail' => $detail, 'fix' => $fix];
    }

    private function logWarn(string $test, string $msg, ?array $detail = null): void
    {
        $this->warnings++;
        $this->line("  WARN [{$test}] {$msg}");
        $this->report[] = ['test' => $test, 'type' => 'WARN', 'message' => $msg, 'detail' => $detail, 'fix' => null];
    }
}
