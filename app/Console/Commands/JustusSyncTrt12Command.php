<?php

namespace App\Console\Commands;

use App\Models\JustusJurisprudencia;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class JustusSyncTrt12Command extends Command
{
    protected $signature = 'justus:sync-trt12
        {--termo=* : Termo de busca (default: * = todos)}
        {--colecao=acordaos : Coleção: acordaos, sentencas, recursorevista}
        {--data-inicio= : Data início DD/MM/YYYY}
        {--data-fim= : Data fim DD/MM/YYYY}
        {--meses-atras=1 : Quantos meses para trás (default 1)}
        {--ps=10 : Resultados por página (5 ou 10)}
        {--max-paginas=0 : Limite de páginas (0=todas)}
        {--dry-run : Simular sem gravar}
        {--force : Reimportar mesmo já existentes}';

    protected $description = 'Sincroniza jurisprudência do TRT12 via API Falcão (jurisprudencia.jt.jus.br)';

    private string $baseUrl = 'https://jurisprudencia.jt.jus.br/jurisprudencia-nacional-backend/api/no-auth/pesquisa';
    private string $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';
    private string $salt = 'T9!juris#F4LKN';

    private int $imported = 0;
    private int $updated = 0;
    private int $skipped = 0;
    private int $errors = 0;
    private int $requestCount = 0;

    public function handle(): int
    {
        // Usar banco separado do tribunal
        \App\Models\JustusJurisprudencia::setTribunalConnection('TRT12');

        $dryRun = $this->option('dry-run');
        $force = $this->option('force');
        $ps = in_array((int) $this->option('ps'), [5, 10]) ? (int) $this->option('ps') : 10;
        $maxPaginas = (int) $this->option('max-paginas');
        $colecao = $this->option('colecao');
        $termo = $this->option('termo') ?: '*';

        $this->info('=== JUSTUS: Sincronização TRT12 via API Falcão ===');
        $this->info('Dry-run: ' . ($dryRun ? 'SIM' : 'NÃO'));
        $this->info("Coleção: {$colecao} | Termo: {$termo} | Resultados/página: {$ps}");

        // Período
        $dataInicio = $this->option('data-inicio');
        $dataFim = $this->option('data-fim');
        if (!$dataInicio || !$dataFim) {
            $mesesAtras = (int) $this->option('meses-atras');
            $inicio = Carbon::now('America/Sao_Paulo')->subMonths($mesesAtras)->startOfMonth();
            $fim = Carbon::now('America/Sao_Paulo');
            $dataInicio = $inicio->format('d/m/Y');
            $dataFim = $fim->format('d/m/Y');
        }
        $this->info("Período: {$dataInicio} a {$dataFim}");
        $this->newLine();

        // Gerar tokens
        $sessionId = $this->gerarSessionId();
        $juristkn = $this->gerarJuristkn($sessionId);
        $this->info("Token gerado: sid={$sessionId} tkn={$juristkn}");

        // Primeira página
        $response = $this->buscarPagina($sessionId, $juristkn, $termo, $colecao, $dataInicio, $dataFim, 0, $ps);
        if (!$response) {
            $this->error('Falha ao buscar primeira página');
            return self::FAILURE;
        }

        $total = $response['total'] ?? 0;
        $totalPaginas = (int) ceil($total / $ps);
        $this->info("Total: {$total} documentos em {$totalPaginas} páginas");

        if ($total === 0) {
            $this->warn('Nenhum resultado encontrado');
            return self::SUCCESS;
        }

        if ($maxPaginas > 0 && $totalPaginas > $maxPaginas) {
            $totalPaginas = $maxPaginas;
            $this->warn("Limitado a {$maxPaginas} páginas conforme --max-paginas");
        }

        // Processar página 0
        $docs = $response['documentos'] ?? [];
        $this->info("Página 1/{$totalPaginas}: " . count($docs) . " documentos");
        $this->salvarDocumentos($docs, $dryRun, $force);

        // Páginas subsequentes
        for ($pg = 1; $pg < $totalPaginas; $pg++) {
            usleep(500000); // 500ms rate limiting

            // Renovar tokens a cada 100 páginas (precaução)
            if ($pg % 100 === 0) {
                $sessionId = $this->gerarSessionId();
                $juristkn = $this->gerarJuristkn($sessionId);
            }

            $response = $this->buscarPagina($sessionId, $juristkn, $termo, $colecao, $dataInicio, $dataFim, $pg, $ps);
            if (!$response) {
                $this->warn("Página " . ($pg + 1) . "/{$totalPaginas}: falha, pulando");
                $this->errors++;

                // Se 3 falhas seguidas, renovar token
                if ($this->errors % 3 === 0) {
                    $sessionId = $this->gerarSessionId();
                    $juristkn = $this->gerarJuristkn($sessionId);
                    $this->info("  Token renovado após falhas");
                }
                continue;
            }

            $docs = $response['documentos'] ?? [];
            $this->info("Página " . ($pg + 1) . "/{$totalPaginas}: " . count($docs) . " documentos");
            $this->salvarDocumentos($docs, $dryRun, $force);

            if ($pg % 50 === 0) {
                $this->info("  [Progresso] Importados: {$this->imported} | Atualizados: {$this->updated} | Pulados: {$this->skipped} | Erros: {$this->errors}");
            }
        }

        // Resumo
        $this->newLine();
        $this->info('=== RESUMO FINAL TRT12 ===');
        $this->info("Importados: {$this->imported}");
        $this->info("Atualizados: {$this->updated}");
        $this->info("Pulados: {$this->skipped}");
        $this->info("Erros: {$this->errors}");
        $this->info("Requests HTTP: {$this->requestCount}");
        $total = JustusJurisprudencia::where('tribunal', 'TRT12')->count();
        $this->info("Total TRT12 na base: {$total}");

        Log::channel('single')->info('JUSTUS TRT12 sync finalizado', [
            'imported' => $this->imported,
            'updated' => $this->updated,
            'skipped' => $this->skipped,
            'errors' => $this->errors,
            'requests' => $this->requestCount,
        ]);

        return self::SUCCESS;
    }

    private function gerarSessionId(): string
    {
        return '_' . substr(bin2hex(random_bytes(4)), 0, 7);
    }

    private function gerarJuristkn(string $sessionId): string
    {
        return substr(md5($sessionId . $this->salt), 3, 14);
    }

    private function buscarPagina(string $sid, string $tkn, string $termo, string $colecao, string $dataInicio, string $dataFim, int $page, int $size): ?array
    {
        $params = [
            'sessionId' => $sid,
            'juristkn' => $tkn,
            'texto' => $termo,
            'tribunais' => 'TRT12',
            'colecao' => $colecao,
            'page' => $page,
            'size' => $size,
            'dataJulgamentoInicio' => $dataInicio,
            'dataJulgamentoFim' => $dataFim,
        ];

        $url = $this->baseUrl . '?' . http_build_query($params);

        $cmd = 'curl -s --max-time 60'
            . ' ' . escapeshellarg($url)
            . ' -H ' . escapeshellarg('User-Agent: ' . $this->userAgent)
            . ' -H ' . escapeshellarg('Accept: application/json, text/plain, */*')
            . ' -H ' . escapeshellarg('Referer: https://jurisprudencia.jt.jus.br/jurisprudencia-nacional/')
            . ' -H ' . escapeshellarg('Origin: https://jurisprudencia.jt.jus.br')
            . ' -H ' . escapeshellarg('Sec-Fetch-Dest: empty')
            . ' -H ' . escapeshellarg('Sec-Fetch-Mode: cors')
            . ' -H ' . escapeshellarg('Sec-Fetch-Site: same-origin');

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $this->requestCount++;
        $proc = proc_open($cmd, $descriptors, $pipes);

        if (!is_resource($proc)) {
            return null;
        }

        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($proc);

        if ($exitCode !== 0 || empty($output)) {
            return null;
        }

        $data = json_decode($output, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Pode ser 403/401 HTML
            if (strpos($output, '403') !== false || strpos($output, '401') !== false) {
                $this->warn("  Resposta não-JSON (possível bloqueio WAF)");
            }
            return null;
        }

        return $data;
    }

    private function salvarDocumentos(array $docs, bool $dryRun, bool $force): void
    {
        foreach ($docs as $doc) {
            try {
                $record = $this->mapearDocumento($doc);
                if (!$record) {
                    $this->errors++;
                    continue;
                }

                if ($dryRun) {
                    $this->skipped++;
                    continue;
                }

                $existing = JustusJurisprudencia::where('external_id', $record['external_id'])
                    ->where('tribunal', 'TRT12')
                    ->first();

                if ($existing && !$force) {
                    $this->skipped++;
                    continue;
                }

                if ($existing) {
                    $existing->update($record);
                    $this->updated++;
                } else {
                    JustusJurisprudencia::create($record);
                    $this->imported++;
                }
            } catch (\Throwable $e) {
                $this->errors++;
                if ($this->errors <= 5) {
                    $this->warn("  Erro: " . $e->getMessage());
                }
            }
        }
    }

    private function mapearDocumento(array $doc): ?array
    {
        $processo = $doc['numeroProcesso'] ?? null;
        if (!$processo) {
            return null;
        }

        // Limpar ementa (remover HTML)
        $ementa = $doc['ementa'] ?? '';
        $ementa = strip_tags($ementa);
        $ementa = html_entity_decode($ementa, ENT_QUOTES, 'UTF-8');
        $ementa = trim(preg_replace('/\s+/', ' ', $ementa));

        // Texto completo do acórdão (usar versão anonimizada limpa se disponível)
        $decisao = '';
        if (!empty($doc['highlightTextoAcordaoAnonimizado'])) {
            $decisao = strip_tags($doc['highlightTextoAcordaoAnonimizado']);
        } elseif (!empty($doc['textoAcordao'])) {
            $decisao = strip_tags($doc['textoAcordao']);
        }
        $decisao = html_entity_decode($decisao, ENT_QUOTES, 'UTF-8');
        $decisao = trim(preg_replace('/\s+/', ' ', $decisao));

        // Data julgamento (DD/MM/YYYY -> Y-m-d)
        $dataDecisao = null;
        if (!empty($doc['dataJulgamento'])) {
            $parts = explode('/', $doc['dataJulgamento']);
            if (count($parts) === 3) {
                $dataDecisao = "{$parts[2]}-{$parts[1]}-{$parts[0]}";
            }
        }

        // Data publicação
        $dataPub = null;
        if (!empty($doc['dataJuntada'])) {
            $parts = explode('/', $doc['dataJuntada']);
            if (count($parts) === 3) {
                $dataPub = "{$parts[2]}-{$parts[1]}-{$parts[0]}";
            }
        }

        // Referências legislativas
        $refs = null;
        if (!empty($doc['referenciaLegislativa']) && is_array($doc['referenciaLegislativa'])) {
            $refs = $doc['referenciaLegislativa'];
        }

        return [
            'external_id' => 'TRT12_' . ($doc['idDocumentoAcordao'] ?? md5($processo)),
            'tribunal' => 'TRT12',
            'numero_processo' => $processo,
            'sigla_classe' => $doc['siglaClasseProcesso'] ?? null,
            'descricao_classe' => $doc['classeProcesso'] ?? null,
            'orgao_julgador' => $doc['turma'] ?? null,
            'relator' => $doc['relator'] ?? null,
            'data_decisao' => $dataDecisao,
            'data_publicacao' => $dataPub,
            'ementa' => $ementa ?: null,
            'decisao' => $decisao ?: null,
            'tipo_decisao' => 'Acórdão',
            'referencias_legislativas' => $refs,
            'area_direito' => 'trabalhista',
            'fonte_dataset' => 'falcao_api',
            'fonte_resource' => 'jurisprudencia_trt12',
        ];
    }
}
