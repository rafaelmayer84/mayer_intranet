<?php

namespace App\Console\Commands;

use App\Models\JustusJurisprudencia;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class JustusSyncTjscCommand extends Command
{
    protected $signature = 'justus:sync-tjsc
        {--mes= : Mês específico (01-12)}
        {--ano= : Ano específico (ex: 2025)}
        {--meses-atras=1 : Quantos meses para trás sincronizar (default 1)}
        {--ps=50 : Resultados por página (max 50)}
        {--dry-run : Simular sem gravar}
        {--force : Reimportar mesmo já existentes}';

    protected $description = 'Sincroniza jurisprudência do TJSC via scraping do portal público';

    private string $baseUrl = 'https://busca.tjsc.jus.br/jurisprudencia';
    private string $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';
    private string $cookieFile = '';

    private int $imported = 0;
    private int $updated = 0;
    private int $skipped = 0;
    private int $errors = 0;
    private int $requestCount = 0;

    public function handle(): int
    {
        $this->cookieFile = tempnam(sys_get_temp_dir(), 'tjsc_');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');
        $ps = min((int) $this->option('ps'), 50);

        $this->info('=== JUSTUS: Sincronização TJSC via Scraping ===');
        $this->info('Dry-run: ' . ($dryRun ? 'SIM' : 'NÃO'));
        $this->info('Force: ' . ($force ? 'SIM' : 'NÃO'));
        $this->info('Resultados/página: ' . $ps);
        $this->newLine();

        // Determinar períodos
        $periodos = $this->calcularPeriodos();

        foreach ($periodos as $periodo) {
            $this->info(">>> Período: {$periodo['label']}");
            $this->syncPeriodo($periodo['inicio'], $periodo['fim'], $ps, $dryRun, $force);
            $this->newLine();
        }

        // Resumo
        $this->newLine();
        $this->info('=== RESUMO FINAL ===');
        $this->info("Importados: {$this->imported}");
        $this->info("Atualizados: {$this->updated}");
        $this->info("Pulados: {$this->skipped}");
        $this->info("Erros: {$this->errors}");
        $this->info("Requests HTTP: {$this->requestCount}");
        $total = JustusJurisprudencia::where('tribunal', 'TJSC')->count();
        $this->info("Total TJSC na base: {$total}");

        @unlink($this->cookieFile);

        Log::channel('single')->info('JUSTUS TJSC sync finalizado', [
            'imported' => $this->imported,
            'updated' => $this->updated,
            'skipped' => $this->skipped,
            'errors' => $this->errors,
            'requests' => $this->requestCount,
        ]);

        return self::SUCCESS;
    }

    private function calcularPeriodos(): array
    {
        $mesOpt = $this->option('mes');
        $anoOpt = $this->option('ano');

        if ($mesOpt && $anoOpt) {
            $inicio = Carbon::create((int)$anoOpt, (int)$mesOpt, 1)->startOfMonth();
            return [[
                'inicio' => $inicio->format('d/m/Y'),
                'fim' => $inicio->copy()->endOfMonth()->format('d/m/Y'),
                'label' => $inicio->format('m/Y'),
            ]];
        }

        $mesesAtras = (int) $this->option('meses-atras');
        $periodos = [];
        for ($i = $mesesAtras; $i >= 1; $i--) {
            $dt = Carbon::now('America/Sao_Paulo')->subMonths($i)->startOfMonth();
            $periodos[] = [
                'inicio' => $dt->format('d/m/Y'),
                'fim' => $dt->copy()->endOfMonth()->format('d/m/Y'),
                'label' => $dt->format('m/Y'),
            ];
        }
        // Mês atual (parcial)
        $now = Carbon::now('America/Sao_Paulo');
        $periodos[] = [
            'inicio' => $now->copy()->startOfMonth()->format('d/m/Y'),
            'fim' => $now->format('d/m/Y'),
            'label' => $now->format('m/Y') . ' (parcial)',
        ];

        return $periodos;
    }

    private function syncPeriodo(string $dataInicial, string $dataFinal, int $ps, bool $dryRun, bool $force): void
    {
        // Step 1: Estabelecer sessão
        if (!$this->estabelecerSessao($dataInicial, $dataFinal)) {
            $this->error('  Falha ao estabelecer sessão TJSC');
            $this->errors++;
            return;
        }

        // Step 2: Buscar primeira página para obter total
        $html = $this->buscarPagina($dataInicial, $dataFinal, 1, $ps);
        if (!$html) {
            $this->error('  Falha ao buscar primeira página');
            $this->errors++;
            return;
        }

        // Extrair total de resultados
        $total = $this->extrairTotal($html);
        if ($total === 0) {
            $this->warn('  Nenhum resultado encontrado');
            return;
        }

        $totalPaginas = (int) ceil($total / $ps);
        $this->info("  Total: {$total} acórdãos em {$totalPaginas} páginas");

        // Processar página 1
        $acordaos = $this->parsearAcordaos($html);
        $this->info("  Página 1/{$totalPaginas}: " . count($acordaos) . " acórdãos extraídos");
        $this->salvarAcordaos($acordaos, $dryRun, $force);

        // Páginas subsequentes
        for ($pg = 2; $pg <= $totalPaginas; $pg++) {
            // Rate limiting: 500ms entre requests
            usleep(500000);

            $html = $this->buscarPagina($dataInicial, $dataFinal, $pg, $ps);
            if (!$html) {
                $this->warn("  Página {$pg}/{$totalPaginas}: falha, pulando");
                $this->errors++;
                continue;
            }

            $acordaos = $this->parsearAcordaos($html);
            $this->info("  Página {$pg}/{$totalPaginas}: " . count($acordaos) . " acórdãos extraídos");
            $this->salvarAcordaos($acordaos, $dryRun, $force);

            // A cada 10 páginas, recriar sessão (prevenir timeout)
            if ($pg % 10 === 0 && $pg < $totalPaginas) {
                $this->estabelecerSessao($dataInicial, $dataFinal);
            }
        }
    }

    private function estabelecerSessao(string $dataInicial, string $dataFinal): bool
    {
        // Limpar cookies anteriores
        @unlink($this->cookieFile);
        $this->cookieFile = tempnam(sys_get_temp_dir(), 'tjsc_');

        // Step 1: GET buscaForm.do (obter JSESSIONID + cookies WAF)
        $this->curlGet("{$this->baseUrl}/buscaForm.do");

        // Step 2: GET avancada.jsp (registrar busca na sessão)
        $params = http_build_query([
            'q' => '',
            'only_ementa' => '',
            'frase' => '',
            'excluir' => '',
            'qualquer' => '',
            'prox1' => '',
            'prox2' => '',
            'proxc' => '',
            'sort' => 'dtJulgamento desc',
            'ps' => '50',
            'busca' => 'avancada',
            'categoria' => 'acordaos',
            'radio_campo' => 'ementa',
            'datainicial' => $dataInicial,
            'datafinal' => $dataFinal,
        ]);

        $resp = $this->curlGet("{$this->baseUrl}/avancada.jsp?{$params}", 30, "{$this->baseUrl}/buscaForm.do");
        return $resp !== false && strlen($resp) > 10000;
    }

    private function buscarPagina(string $dataInicial, string $dataFinal, int $pg, int $ps): ?string
    {
        $params = "q=&only_ementa=&frase=&excluir=&qualquer=&&prox1=&prox2=&proxc="
            . "&sort=dtJulgamento+desc&ps={$ps}&busca=avancada&pg={$pg}"
            . "&categoria=acordaos&flapto=1"
            . "&datainicial=" . urlencode($dataInicial)
            . "&datafinal=" . urlencode($dataFinal)
            . "&radio_campo=ementa";

        $url = "{$this->baseUrl}/buscaajax.do?{$params}";

        $html = $this->curlGet($url, 60, "{$this->baseUrl}/avancada.jsp", true);

        if (!$html || strlen($html) < 500) {
            return null;
        }

        // Converter ISO-8859-1 para UTF-8
        $detected = mb_detect_encoding($html, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
        if ($detected && $detected !== 'UTF-8') {
            $html = mb_convert_encoding($html, 'UTF-8', $detected);
        } else {
            $html = @iconv('ISO-8859-1', 'UTF-8//TRANSLIT//IGNORE', $html);
        }

        return $html;
    }

    private function extrairTotal(string $html): int
    {
        if (preg_match('/<b>(\d+)<\/b>\s*resultados encontrados/i', $html, $m)) {
            return (int) $m[1];
        }
        return 0;
    }

    private function parsearAcordaos(string $html): array
    {
        $acordaos = [];

        // Split por blocos de resultado
        $blocos = preg_split('/<div class="resultados">/', $html);
        array_shift($blocos); // remove antes do primeiro resultado

        foreach ($blocos as $bloco) {
            try {
                $ac = $this->parsearBloco($bloco);
                if ($ac && !empty($ac['numero_processo'])) {
                    $acordaos[] = $ac;
                }
            } catch (\Throwable $e) {
                $this->errors++;
                Log::warning('JUSTUS TJSC: erro ao parsear bloco', ['error' => $e->getMessage()]);
            }
        }

        return $acordaos;
    }

    private function parsearBloco(string $bloco): ?array
    {
        $ac = [
            'tribunal' => 'TJSC',
            'tipo_decisao' => 'ACORDAO',
            'fonte_dataset' => 'tjsc-portal-jurisprudencia',
        ];

        // Número do processo: openLinkAcompanhamento('acordao_eproc','5027322-90.2025.8.24.0000')
        if (preg_match("/openLinkAcompanhamento\('[^']+','([^']+)'\)/", $bloco, $m)) {
            $ac['numero_processo'] = trim($m[1]);
        } else {
            return null;
        }

        // Row ID (external_id): integra.do?rowid=XXXXX&tipo=
        if (preg_match('/integra\.do\?rowid=(\w+)&/', $bloco, $m)) {
            $ac['external_id'] = $m[1];
        } else {
            $ac['external_id'] = 'TJSC-' . md5($ac['numero_processo']);
        }

        // Relator
        if (preg_match('/<strong>Relator:<\/strong>\s*([^<]+)<br/i', $bloco, $m)) {
            $ac['relator'] = trim($m[1]);
        }

        // Origem
        // (informativo, sempre TJSC)

        // Órgão Julgador
        if (preg_match('/Julgador:<\/strong>\s*([^<]+)<br/i', $bloco, $m)) {
            $ac['orgao_julgador'] = trim($m[1]);
        }

        // Data Julgamento: "Julgado em:" seguido de data Java
        if (preg_match('/Julgado em:<\/strong>\s*\w+ (\w+) (\d+) .* (\d{4})/i', $bloco, $m)) {
            $meses = [
                'Jan'=>1,'Feb'=>2,'Mar'=>3,'Apr'=>4,'May'=>5,'Jun'=>6,
                'Jul'=>7,'Aug'=>8,'Sep'=>9,'Oct'=>10,'Nov'=>11,'Dec'=>12,
            ];
            $mesNum = $meses[$m[1]] ?? null;
            if ($mesNum) {
                $ac['data_decisao'] = sprintf('%04d-%02d-%02d', (int)$m[3], $mesNum, (int)$m[2]);
            }
        }

        // Classe
        if (preg_match('/<strong>Classe:<\/strong>\s*([^<]+)</i', $bloco, $m)) {
            $descClasse = trim($m[1]);
            $ac['descricao_classe'] = $descClasse;
            $ac['sigla_classe'] = $this->abreviarClasse($descClasse);
        }

        // Ementa: conteúdo do <textarea id="text_ementa_N">
        if (preg_match('/<textarea[^>]*id="text_ementa_\d+"[^>]*>(.*?)<\/textarea>/si', $bloco, $m)) {
            $ac['ementa'] = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES, 'UTF-8'));
        } else {
            // Fallback: pegar texto da ementa exibida (com <b> de highlight)
            if (preg_match_all('/<p>\s*((?:(?!<p>).)*?(?:RECURSO|APELA|AGRAVO|EMBARGOS|HABEAS|MANDADO|AÇÃO|CONFLITO|EXCEÇÃO|INCIDENTE).*?)\s*<\/p>/si', $bloco, $ms)) {
                $ementa = strip_tags(end($ms[1]));
                $ac['ementa'] = trim(html_entity_decode($ementa, ENT_QUOTES, 'UTF-8'));
            }
        }

        // Inferir área do direito pelo órgão julgador
        $ac['area_direito'] = $this->inferAreaTjsc($ac['orgao_julgador'] ?? '');

        // fonte_resource = mês/ano do período
        $ac['fonte_resource'] = $ac['data_decisao'] ? substr($ac['data_decisao'], 0, 7) : 'unknown';

        return $ac;
    }

    private function abreviarClasse(string $classe): string
    {
        $mapa = [
            'Agravo de Instrumento' => 'AI',
            'Apelação' => 'APL',
            'Apelação Cível' => 'APL',
            'Embargos de Declaração' => 'ED',
            'Mandado de Segurança' => 'MS',
            'Habeas Corpus' => 'HC',
            'Conflito de Competência' => 'CC',
            'Recurso Inominado' => 'RI',
            'Ação Rescisória' => 'AR',
            'Embargos Infringentes' => 'EI',
            'Agravo Regimental' => 'AGR',
            'Agravo Interno' => 'AGI',
            'Exceção de Suspeição' => 'ES',
            'Correição Parcial' => 'CP',
            'Revisão Criminal' => 'RC',
        ];
        return $mapa[$classe] ?? mb_strtoupper(mb_substr($classe, 0, 10));
    }

    private function inferAreaTjsc(string $orgao): ?string
    {
        $orgaoUpper = mb_strtoupper($orgao);
        if (str_contains($orgaoUpper, 'CRIMINAL') || str_contains($orgaoUpper, 'CRIMINAL')) {
            return 'penal';
        }
        if (str_contains($orgaoUpper, 'DIREITO CIVIL') || str_contains($orgaoUpper, 'CÍVEL')) {
            return 'civil';
        }
        if (str_contains($orgaoUpper, 'COMERCIAL')) {
            return 'comercial';
        }
        if (str_contains($orgaoUpper, 'PÚBLICO') || str_contains($orgaoUpper, 'PUBLICO')) {
            return 'publico';
        }
        if (str_contains($orgaoUpper, 'RECURSAL') || str_contains($orgaoUpper, 'RECURSOS')) {
            return 'civil'; // Turmas recursais = JEC, predominância cível
        }
        return null;
    }

    private function salvarAcordaos(array $acordaos, bool $dryRun, bool $force): void
    {
        foreach ($acordaos as $ac) {
            try {
                if ($dryRun) {
                    $this->skipped++;
                    continue;
                }

                $existing = JustusJurisprudencia::where('tribunal', 'TJSC')
                    ->where('external_id', $ac['external_id'])
                    ->first();

                if ($existing && !$force) {
                    $this->skipped++;
                    continue;
                }

                $data = [
                    'external_id' => $ac['external_id'],
                    'tribunal' => 'TJSC',
                    'numero_processo' => $ac['numero_processo'] ?? null,
                    'sigla_classe' => $ac['sigla_classe'] ?? null,
                    'descricao_classe' => $ac['descricao_classe'] ?? null,
                    'orgao_julgador' => $ac['orgao_julgador'] ?? null,
                    'relator' => $ac['relator'] ?? null,
                    'data_decisao' => $ac['data_decisao'] ?? null,
                    'ementa' => $ac['ementa'] ?? null,
                    'tipo_decisao' => $ac['tipo_decisao'] ?? 'ACORDAO',
                    'area_direito' => $ac['area_direito'] ?? null,
                    'fonte_dataset' => $ac['fonte_dataset'] ?? 'tjsc-portal-jurisprudencia',
                    'fonte_resource' => $ac['fonte_resource'] ?? null,
                ];

                if ($existing) {
                    $existing->update($data);
                    $this->updated++;
                } else {
                    JustusJurisprudencia::create($data);
                    $this->imported++;
                }
            } catch (\Throwable $e) {
                $this->errors++;
                Log::warning('JUSTUS TJSC: erro ao salvar', [
                    'processo' => $ac['numero_processo'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function curlGet(string $url, int $timeout = 30, ?string $referer = null, bool $ajax = false): string|false
    {
        $this->requestCount++;
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_USERAGENT => $this->userAgent,
            CURLOPT_COOKIEJAR => $this->cookieFile,
            CURLOPT_COOKIEFILE => $this->cookieFile,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_ENCODING => '',
        ]);

        $headers = ['Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'];
        if ($referer) {
            $headers[] = "Referer: {$referer}";
        }
        if ($ajax) {
            $headers[] = 'X-Requested-With: XMLHttpRequest';
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || $response === false) {
            Log::warning("JUSTUS TJSC: HTTP {$httpCode} para {$url}");
            return false;
        }

        return $response;
    }
}
