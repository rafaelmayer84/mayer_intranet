<?php

namespace App\Console\Commands;

use App\Models\JustusJurisprudencia;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * ============================================================
 * ARQUIVO ESTÁVEL desde 12/03/2026
 * Scraper TRT12 via API REST Falcão — funcional e validado
 * em produção. 4.629 acórdãos importados.
 * Alterações devem ser feitas SOMENTE após profundo estudo
 * de sua pertinência e confiabilidade.
 * ============================================================
 */
class JustusSyncTrt12Command extends Command
{
    protected $signature = 'justus:sync-trt12
        {--modo=recentes : Modo: recentes (cron diario) ou historico (carga completa)}
        {--ps=10 : Resultados por pagina}
        {--max-paginas=20 : Limite de paginas por query (max 20)}
        {--dry-run : Simular sem gravar}
        {--force : Reimportar mesmo ja existentes}';

    protected $description = 'Sincroniza jurisprudencia do TRT12 via API Falcao';

    private string $baseUrl = 'https://jurisprudencia.jt.jus.br/jurisprudencia-nacional-backend/api/no-auth/pesquisa';
    private string $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';
    private string $salt = 'T9!juris#F4LKN';

    private int $imported = 0;
    private int $updated = 0;
    private int $skipped = 0;
    private int $errors = 0;
    private int $requestCount = 0;

    private array $orgaos = [
        '1ª Turma', '2ª Turma', '3ª Turma', '4ª Turma', '5ª Turma',
        'Seção Especializada 1', 'Seção Especializada 2', 'Tribunal Pleno',
    ];

    public function handle(): int
    {
        JustusJurisprudencia::setTribunalConnection('TRT12');

        $modo = $this->option('modo');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');
        $ps = min((int) $this->option('ps'), 10);
        $maxPaginas = min((int) $this->option('max-paginas'), 20);

        $this->info('=== JUSTUS: Sincronizacao TRT12 via API Falcao ===');
        $this->info("Modo: {$modo} | Dry-run: " . ($dryRun ? 'SIM' : 'NAO') . " | Max paginas/query: {$maxPaginas}");
        $this->newLine();

        if ($modo === 'historico') {
            $this->carregarHistorico($ps, $maxPaginas, $dryRun, $force);
        } else {
            $this->carregarRecentes($ps, $maxPaginas, $dryRun, $force);
        }

        $this->newLine();
        $this->info('=== RESUMO FINAL TRT12 ===');
        $this->info("Importados: {$this->imported}");
        $this->info("Atualizados: {$this->updated}");
        $this->info("Pulados: {$this->skipped}");
        $this->info("Erros: {$this->errors}");
        $this->info("Requests HTTP: {$this->requestCount}");
        $total = JustusJurisprudencia::onTribunal('TRT12')->count();
        $this->info("Total TRT12 na base: {$total}");

        return self::SUCCESS;
    }

    private function carregarHistorico(int $ps, int $maxPag, bool $dryRun, bool $force): void
    {
        $janelas = [30, 60, 365, 730, 1825];
        foreach ($this->orgaos as $orgao) {
            $this->info("--- Orgao: {$orgao} ---");
            foreach ($janelas as $dias) {
                foreach (['mais_recente', 'menos_recente'] as $ordem) {
                    $this->info("  Janela: {$dias}d | Ordem: {$ordem}");
                    $this->varrerPaginas($orgao, $dias, $ordem, $ps, $maxPag, $dryRun, $force);
                }
            }
        }
    }

    private function carregarRecentes(int $ps, int $maxPag, bool $dryRun, bool $force): void
    {
        foreach ($this->orgaos as $orgao) {
            $this->info("--- Orgao: {$orgao} (ultimos 7 dias) ---");
            $this->varrerPaginas($orgao, 7, 'mais_recente', $ps, $maxPag, $dryRun, $force);
        }
    }

    private function varrerPaginas(string $orgao, int $dias, string $ordem, int $ps, int $maxPag, bool $dryRun, bool $force): void
    {
        $sid = $this->gerarSessionId();
        $tkn = $this->gerarJuristkn($sid);
        $novidadesNaQuery = 0;

        for ($pg = 0; $pg < $maxPag; $pg++) {
            usleep(500000);

            $response = $this->buscarPagina($sid, $tkn, $orgao, $dias, $ordem, $pg, $ps);
            if (!$response || !isset($response['documentos'])) {
                if ($pg === 0) {
                    $this->warn("    Falha na primeira pagina, pulando");
                }
                break;
            }

            $docs = $response['documentos'];
            if (empty($docs)) {
                break;
            }

            $antesImport = $this->imported;
            $this->salvarDocumentos($docs, $dryRun, $force);
            $novos = $this->imported - $antesImport;
            $novidadesNaQuery += $novos;

            $this->info("    pg {$pg}: " . count($docs) . " docs, {$novos} novos");

            if ($pg >= 2 && $novidadesNaQuery === 0) {
                $this->info("    Sem novidades, avancando");
                break;
            }
        }
    }

    private function gerarSessionId(): string
    {
        return '_' . substr(bin2hex(random_bytes(4)), 0, 7);
    }

    private function gerarJuristkn(string $sessionId): string
    {
        return substr(md5($sessionId . $this->salt), 3, 14);
    }

    private function buscarPagina(string $sid, string $tkn, string $orgao, int $dias, string $ordem, int $page, int $size): ?array
    {
        $params = [
            'sessionId' => $sid,
            'juristkn' => $tkn,
            'texto' => '',
            'tribunais' => 'TRT12',
            'colecao' => 'acordaos',
            'page' => $page,
            'size' => $size,
            'orgaoJulgador' => $orgao,
            'filtroRapidoData' => $dias,
            'ordenacao' => $ordem,
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

        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $this->requestCount++;
        $proc = proc_open($cmd, $descriptors, $pipes);
        if (!is_resource($proc)) {
            return null;
        }

        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($proc);

        if (empty($output)) {
            return null;
        }

        $data = json_decode($output, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }
        if (is_array($data) && isset($data[0]['erro'])) {
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

                $existing = JustusJurisprudencia::onTribunal('TRT12')
                    ->where('external_id', $record['external_id'])
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
                    $j = new JustusJurisprudencia($record);
                    $j->setConnection('justus_falcao');
                    $j->save();
                    $this->imported++;
                }
            } catch (\Throwable $e) {
                $this->errors++;
                if ($this->errors <= 5) {
                    $this->warn('  Erro: ' . $e->getMessage());
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

        $ementa = $doc['ementa'] ?? '';
        $ementa = strip_tags($ementa);
        $ementa = html_entity_decode($ementa, ENT_QUOTES, 'UTF-8');
        $ementa = trim(preg_replace('/\s+/', ' ', $ementa));

        $decisao = '';
        if (!empty($doc['textoAcordao'])) {
            $decisao = strip_tags($doc['textoAcordao']);
            $decisao = html_entity_decode($decisao, ENT_QUOTES, 'UTF-8');
            $decisao = trim(preg_replace('/\s+/', ' ', $decisao));
        }

        $dataDecisao = null;
        if (!empty($doc['dataJulgamento'])) {
            $parts = explode('/', $doc['dataJulgamento']);
            if (count($parts) === 3) {
                $dataDecisao = "{$parts[2]}-{$parts[1]}-{$parts[0]}";
            }
        }

        $dataPub = null;
        if (!empty($doc['dataJuntada'])) {
            $parts = explode('/', $doc['dataJuntada']);
            if (count($parts) === 3) {
                $dataPub = "{$parts[2]}-{$parts[1]}-{$parts[0]}";
            }
        }

        return [
            'external_id' => 'TRT12_' . ($doc['idDocumentoAcordao'] ?? md5($processo)),
            'tribunal' => 'TRT12',
            'numero_processo' => $processo,
            'orgao_julgador' => $doc['turma'] ?? null,
            'relator' => $doc['relator'] ?? null,
            'data_decisao' => $dataDecisao,
            'data_publicacao' => $dataPub,
            'ementa' => $ementa ?: null,
            'decisao' => $decisao ?: null,
            'tipo_decisao' => 'Acordao',
            'area_direito' => 'trabalhista',
        ];
    }
}
