<?php

namespace App\Console\Commands;

use App\Models\JustusJurisprudencia;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * ============================================================
 * ARQUIVO CRIADO em 18/03/2026
 * Scraper TJSC via eproc — baseado no JustusSyncTrf4Command.php
 * O TJSC migrou do busca.tjsc.jus.br para o sistema eproc,
 * idêntico ao utilizado pelo TRF4.
 * 
 * DEDUPLICAÇÃO: Usa external_id numérico do eproc (mesmo padrão
 * dos 245k registros já existentes). Índice único em
 * (tribunal, external_id) impede duplicatas.
 * ============================================================
 */
class JustusSyncTjscEprocCommand extends Command
{
    protected $signature = 'justus:sync-tjsc-eproc
        {--termo=* : Termo de busca (default: * = todos)}
        {--tipo= : Tipo documento: 1=Acórdãos TJ, 2=Monocráticas TJ, etc}
        {--data-inicio= : Data início DD/MM/YYYY}
        {--data-fim= : Data fim DD/MM/YYYY}
        {--meses-atras=1 : Quantos meses para trás (default 1)}
        {--ps=10 : Resultados por página (max 50)}
        {--max-paginas=0 : Limite de páginas (0=todas)}
        {--dry-run : Simular sem gravar}
        {--force : Reimportar mesmo já existentes}';

    protected $description = 'Sincroniza jurisprudência do TJSC via scraping do portal eproc (novo sistema)';

    private string $baseUrl = 'https://eproc1g.tjsc.jus.br/eproc/externo_controlador.php';
    private string $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';

    private int $imported = 0;
    private int $updated = 0;
    private int $skipped = 0;
    private int $errors = 0;
    private int $requestCount = 0;
    private int $consecutiveSkips = 0;
    private int $maxConsecutiveSkips = 100;

    public function handle(): int
    {
        // Usar banco separado do tribunal
        JustusJurisprudencia::setTribunalConnection('TJSC');

        $dryRun = $this->option('dry-run');
        $force = $this->option('force');
        $ps = min((int) $this->option('ps'), 50);
        $maxPaginas = (int) $this->option('max-paginas');

        $this->info('=== JUSTUS: Sincronização TJSC via Scraping eproc ===');
        $this->info('Dry-run: ' . ($dryRun ? 'SIM' : 'NÃO'));
        $this->info('Force: ' . ($force ? 'SIM' : 'NÃO'));
        $this->info("Resultados/página: {$ps}");
        $this->newLine();

        // Montar parâmetros de busca
        $params = $this->montarParametros($ps);

        if (empty($params)) {
            $this->error('Falha ao montar parâmetros de busca');
            return self::FAILURE;
        }

        $this->info("Termo: " . $params['txtPesquisa']);
        $this->info("Período: " . ($params['dtDecisaoInicio'] ?? '?') . " a " . ($params['dtDecisaoFim'] ?? '?'));
        $this->newLine();

        // Buscar primeira página
        $html = $this->buscarPagina($params, 1);
        if (!$html) {
            $this->error('Falha ao buscar primeira página');
            return self::FAILURE;
        }

        // Extrair total
        $totalPaginas = $this->extrairTotalPaginas($html);
        $totalRegistros = $this->extrairTotalRegistros($html);
        $this->info("Total: {$totalRegistros} registros em {$totalPaginas} páginas");

        if ($totalRegistros === 0) {
            $this->warn('Nenhum resultado encontrado');
            return self::SUCCESS;
        }

        if ($maxPaginas > 0 && $totalPaginas > $maxPaginas) {
            $totalPaginas = $maxPaginas;
            $this->warn("Limitado a {$maxPaginas} páginas conforme --max-paginas");
        }

        // Processar página 1
        $resultados = $this->parsearResultados($html);
        $this->info("Página 1/{$totalPaginas}: " . count($resultados) . " resultados");
        $this->salvarResultados($resultados, $dryRun, $force);

        // Páginas subsequentes
        for ($pg = 2; $pg <= $totalPaginas; $pg++) {
            usleep(600000); // 600ms rate limiting

            $html = $this->buscarPagina($params, $pg);
            if (!$html) {
                $this->warn("Página {$pg}/{$totalPaginas}: falha, pulando");
                $this->errors++;
                continue;
            }

            $resultados = $this->parsearResultados($html);
            $this->info("Página {$pg}/{$totalPaginas}: " . count($resultados) . " resultados");
            $this->salvarResultados($resultados, $dryRun, $force);

            // Early-stop: se N registros consecutivos já existem, parar
            if ($this->consecutiveSkips >= $this->maxConsecutiveSkips) {
                $this->warn("Early-stop: {$this->maxConsecutiveSkips} registros consecutivos já existentes. Parando.");
                break;
            }

            // Log progresso a cada 50 páginas
            if ($pg % 50 === 0) {
                $this->info("  [Progresso] Importados: {$this->imported} | Atualizados: {$this->updated} | Pulados: {$this->skipped} | Erros: {$this->errors}");
            }
        }

        // Resumo final
        $this->newLine();
        $this->info('=== RESUMO FINAL TJSC ===');
        $this->info("Importados: {$this->imported}");
        $this->info("Atualizados: {$this->updated}");
        $this->info("Pulados: {$this->skipped}");
        $this->info("Erros: {$this->errors}");
        $this->info("Requests HTTP: {$this->requestCount}");
        $total = JustusJurisprudencia::where('tribunal', 'TJSC')->count();
        $this->info("Total TJSC na base: {$total}");

        Log::channel('single')->info('JUSTUS TJSC eproc sync finalizado', [
            'imported' => $this->imported,
            'updated' => $this->updated,
            'skipped' => $this->skipped,
            'errors' => $this->errors,
            'requests' => $this->requestCount,
        ]);

        return self::SUCCESS;
    }

    private function montarParametros(int $ps): array
    {
        $termo = $this->option('termo') ?: '*';
        $params = [
            'txtPesquisa' => $termo,
            'numResultadosPagina' => $ps,
        ];

        // Tipo documento
        $tipo = $this->option('tipo');
        if ($tipo) {
            $params['selTipoDocumento[]'] = $tipo;
        }

        // Datas
        $dataInicio = $this->option('data-inicio');
        $dataFim = $this->option('data-fim');

        if ($dataInicio && $dataFim) {
            $params['dtDecisaoInicio'] = $dataInicio;
            $params['dtDecisaoFim'] = $dataFim;
        } else {
            $mesesAtras = (int) $this->option('meses-atras');
            $inicio = Carbon::now('America/Sao_Paulo')->subMonths($mesesAtras)->startOfMonth();
            $fim = Carbon::now('America/Sao_Paulo');
            $params['dtDecisaoInicio'] = $inicio->format('d/m/Y');
            $params['dtDecisaoFim'] = $fim->format('d/m/Y');
        }

        return $params;
    }

    private function buscarPagina(array $params, int $pagina): ?string
    {
        $params['numPaginaAtual'] = $pagina;

        $postParts = [];
        foreach ($params as $key => $value) {
            $postParts[] = urlencode($key) . '=' . urlencode($value);
        }
        $postData = implode('&', $postParts);

        $url = $this->baseUrl . '?acao=jurisprudencia@jurisprudencia/listar_resultados';

        $cmd = 'curl -s --max-time 60'
            . ' -X POST ' . escapeshellarg($url)
            . ' -H ' . escapeshellarg('User-Agent: ' . $this->userAgent)
            . ' -H ' . escapeshellarg('Content-Type: application/x-www-form-urlencoded')
            . ' -H ' . escapeshellarg('Referer: https://eproc1g.tjsc.jus.br/eproc/externo_controlador.php?acao=jurisprudencia@jurisprudencia/pesquisar')
            . ' -d ' . escapeshellarg($postData);

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

        // Converter ISO-8859-1 para UTF-8
        $output = mb_convert_encoding($output, 'UTF-8', 'ISO-8859-1');

        return $output;
    }

    private function extrairTotalPaginas(string $html): int
    {
        if (preg_match('/name="hdnTotalPaginas"[^>]*value="(\d+)"/', $html, $m)) {
            return (int) $m[1];
        }
        return 0;
    }

    private function extrairTotalRegistros(string $html): int
    {
        if (preg_match('/Documento\s+\d+\s+de\s+([\d.]+)/', $html, $m)) {
            return (int) str_replace('.', '', $m[1]);
        }
        $paginas = $this->extrairTotalPaginas($html);
        if ($paginas > 0) {
            return $paginas * 10;
        }
        return 0;
    }

    private function parsearResultados(string $html): array
    {
        $resultados = [];

        $blocos = preg_split('/<div class="card mb-3 resultadoItem"/', $html);
        array_shift($blocos);

        foreach ($blocos as $bloco) {
            $resultado = $this->parsearBloco($bloco);
            if ($resultado) {
                $resultados[] = $resultado;
            }
        }

        return $resultados;
    }

    private function parsearBloco(string $bloco): ?array
    {
        $record = [
            'tribunal' => 'TJSC',
            'fonte_dataset' => 'eproc_scraping',
            'fonte_resource' => 'jurisprudencia_tjsc_eproc',
        ];

        // 1. External ID do id="resultado{ID}" — usar ID numérico direto (padrão existente)
        if (preg_match('/id="resultado(\d+)"/', $bloco, $m)) {
            $record['external_id'] = $m[1]; // ID numérico direto, sem prefixo
        } else {
            return null;
        }

        // 2. Extrair campos dos pares resLabel/resValue
        $pairs = [];
        if (preg_match_all('/<div class="resLabel">\s*(.*?)\s*<\/div>\s*<div class="resValue[^"]*">\s*(.*?)\s*<\/div>/s', $bloco, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $label = trim(strip_tags($match[1]));
                $value = trim(strip_tags($match[2]));
                $pairs[$label] = $value;
            }
        }

        // PROCESSO
        if (isset($pairs['PROCESSO'])) {
            $record['numero_processo'] = preg_replace('/\/TJSC$/', '', trim($pairs['PROCESSO']));
        }

        // ÓRGÃO JULGADOR
        if (isset($pairs['ÓRGÃO JULGADOR'])) {
            $record['orgao_julgador'] = trim($pairs['ÓRGÃO JULGADOR']);
        }

        // DATA DO JULGAMENTO
        if (isset($pairs['DATA DO JULGAMENTO'])) {
            $record['data_decisao'] = $this->parseDate($pairs['DATA DO JULGAMENTO']);
        }

        // DATA DA PUBLICAÇÃO
        if (isset($pairs['DATA DA PUBLICAÇÃO'])) {
            $record['data_publicacao'] = $pairs['DATA DA PUBLICAÇÃO'];
        }

        // RELATOR
        if (isset($pairs['RELATOR'])) {
            $record['relator'] = trim($pairs['RELATOR']);
        }

        // 3. Tipo documento
        if (preg_match('/resValueTipoJurisprudencia[^>]*>(.*?)<\/div>/s', $bloco, $m)) {
            $record['tipo_decisao'] = trim(strip_tags($m[1]));
        }

        // 4. Ementa/Decisão do data-citacao
        if (preg_match('/data-citacao="(.*?)">/s', $bloco, $m)) {
            $citacao = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
            $citacao = str_replace('&#039;', "'", $citacao);

            // Separar ementa do rodapé de citação: (TJSC, TIPO PROCESSO, ...)
            if (preg_match('/^(.*?)\s*\(TJSC,/s', $citacao, $cm)) {
                $record['ementa'] = trim($cm[1]);
            } else {
                $record['ementa'] = trim($citacao);
            }

            // Extrair dados da citação formal
            if (preg_match('/\(TJSC,\s*(\w+)\s+([\d.\-]+),\s*(.*?),\s*Relator\w*\s+(.*?),\s*julgado em\s+([\d\/]+)\)/', $citacao, $cm)) {
                if (empty($record['sigla_classe'])) {
                    $record['sigla_classe'] = trim($cm[1]);
                }
                if (empty($record['numero_processo'])) {
                    $record['numero_processo'] = trim($cm[2]);
                }
                if (empty($record['orgao_julgador'])) {
                    $record['orgao_julgador'] = trim($cm[3]);
                }
                if (empty($record['relator'])) {
                    $record['relator'] = trim($cm[4]);
                }
                if (empty($record['data_decisao'])) {
                    $record['data_decisao'] = $this->parseDate(trim($cm[5]));
                }
            }
        }

        // 5. Número processo do link consultaProcessual (fallback)
        if (empty($record['numero_processo'])) {
            if (preg_match('/num_processo=(\d{20})/', $bloco, $m)) {
                $p = $m[1];
                $record['numero_processo'] = substr($p, 0, 7) . '-' . substr($p, 7, 2) . '.'
                    . substr($p, 9, 4) . '.' . substr($p, 13, 1) . '.'
                    . substr($p, 14, 2) . '.' . substr($p, 16, 4);
            }
        }

        // 6. Área do direito baseada no órgão julgador
        if (!empty($record['orgao_julgador'])) {
            $record['area_direito'] = $this->inferAreaDireito($record['orgao_julgador']);
        }

        // Validar campos mínimos
        if (empty($record['numero_processo']) && empty($record['ementa'])) {
            return null;
        }

        return $record;
    }

    private function parseDate(string $dateStr): ?string
    {
        $dateStr = trim($dateStr);
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $dateStr, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }
        return null;
    }

    private function inferAreaDireito(string $orgao): ?string
    {
        $orgaoUpper = mb_strtoupper($orgao);

        // Turmas Recursais = JEF/Cível
        if (str_contains($orgaoUpper, 'TURMA RECURSAL') || str_contains($orgaoUpper, 'TURMAS RECURSAIS')) {
            return 'civel';
        }

        // Turmas de Uniformização
        if (str_contains($orgaoUpper, 'UNIFORMIZA')) {
            return 'civel';
        }

        // Câmaras Criminais
        if (str_contains($orgaoUpper, 'CRIMINAL') || str_contains($orgaoUpper, 'CRIME')) {
            return 'criminal';
        }

        // Câmaras de Direito Civil
        if (str_contains($orgaoUpper, 'DIREITO CIVIL') || str_contains($orgaoUpper, 'CÍVEL')) {
            return 'civel';
        }

        // Câmaras de Direito Comercial
        if (str_contains($orgaoUpper, 'COMERCIAL')) {
            return 'comercial';
        }

        // Câmaras de Direito Público
        if (str_contains($orgaoUpper, 'PÚBLICO') || str_contains($orgaoUpper, 'PUBLICA')) {
            return 'administrativo';
        }

        // Conselho da Magistratura
        if (str_contains($orgaoUpper, 'CONSELHO')) {
            return 'administrativo';
        }

        // Grupo de Câmaras
        if (str_contains($orgaoUpper, 'GRUPO')) {
            return 'civel';
        }

        return 'estadual_geral';
    }

    private function salvarResultados(array $resultados, bool $dryRun, bool $force): void
    {
        foreach ($resultados as $record) {
            try {
                if ($dryRun) {
                    $this->skipped++;
                    continue;
                }

                $existing = JustusJurisprudencia::onTribunal('TJSC')
                    ->where('external_id', $record['external_id'])
                    ->where('tribunal', 'TJSC')
                    ->first();

                if ($existing && !$force) {
                    $this->skipped++;
                    $this->consecutiveSkips++;
                    continue;
                }

                if ($existing) {
                    $existing->update($record);
                    $this->updated++;
                    $this->consecutiveSkips = 0;
                } else {
                    JustusJurisprudencia::onTribunal('TJSC')->create($record);
                    $this->imported++;
                    $this->consecutiveSkips = 0;
                }
            } catch (\Throwable $e) {
                $this->errors++;
                if ($this->errors <= 5) {
                    $this->warn("  Erro ao salvar {$record['external_id']}: " . $e->getMessage());
                }
            }
        }
    }
}
