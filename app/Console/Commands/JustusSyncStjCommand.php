<?php

namespace App\Console\Commands;

use App\Models\JustusJurisprudencia;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class JustusSyncStjCommand extends Command
{
    protected $signature = 'justus:sync-stj {--dataset= : Sync apenas um dataset específico} {--force : Reimportar mesmo arquivos já processados} {--dry-run : Simular sem gravar}';
    protected $description = 'Sincroniza jurisprudência do STJ via Portal de Dados Abertos (CKAN)';

    private int $imported = 0;
    private int $updated = 0;
    private int $skipped = 0;
    private int $errors = 0;

    public function handle(): int
    {
        $datasets = config('justus.stj_datasets', []);
        $specificDataset = $this->option('dataset');
        $force = $this->option('force');
        $dryRun = $this->option('dry-run');

        if ($specificDataset) {
            $datasets = [$specificDataset];
        }

        $this->info('=== JUSTUS: Sincronização STJ via CKAN ===');
        $this->info('Datasets: ' . count($datasets));
        $this->info('Force: ' . ($force ? 'SIM' : 'NÃO'));
        $this->info('Dry-run: ' . ($dryRun ? 'SIM' : 'NÃO'));
        $this->newLine();

        $baseUrl = config('justus.stj_ckan_base_url', 'https://dadosabertos.web.stj.jus.br');
        $timeout = config('justus.stj_sync_timeout', 300);

        foreach ($datasets as $datasetName) {
            $this->info(">>> Dataset: {$datasetName}");

            try {
                // 1. Listar resources via API CKAN
                $apiUrl = "{$baseUrl}/api/3/action/package_show?id={$datasetName}";
                $apiResp = $this->curlGet($apiUrl, 30);
                if (!$apiResp) {
                    $this->error("  ERRO: Falha ao consultar CKAN para {$datasetName}");
                    $this->errors++;
                    continue;
                }

                $packageData = json_decode($apiResp, true);
                if (!$packageData || !($packageData['success'] ?? false)) {
                    $this->error("  ERRO: Resposta CKAN inválida para {$datasetName}");
                    $this->errors++;
                    continue;
                }

                // 2. Filtrar resources JSON (excluir ZIP e CSV de dicionário)
                $resources = $packageData['result']['resources'] ?? [];
                $jsonResources = array_filter($resources, function ($r) {
                    if ($r['format'] !== 'JSON') return false;
                    $name = pathinfo($r['name'] ?? $r['url'], PATHINFO_FILENAME);
                    return preg_match('/^\d{8}$/', $name);
                });

                // Ordenar por nome (YYYYMMDD) para processar cronologicamente
                usort($jsonResources, function ($a, $b) {
                    return strcmp($a['name'] ?? '', $b['name'] ?? '');
                });

                $this->info("  Resources JSON encontrados: " . count($jsonResources));

                // 3. Identificar quais já foram importados
                $importedResources = DB::table('justus_jurisprudencia')
                    ->where('fonte_dataset', $datasetName)
                    ->distinct()
                    ->pluck('fonte_resource')
                    ->toArray();

                foreach ($jsonResources as $resource) {
                    $resourceName = pathinfo($resource['name'] ?? $resource['url'], PATHINFO_FILENAME);

                    if (!preg_match('/^\d{8}$/', $resourceName)) {
                        continue;
                    }

                    if (!$force && in_array($resourceName, $importedResources)) {
                        $this->line("  Pulando {$resourceName} (já importado)");
                        $this->skipped++;
                        continue;
                    }

                    try {
                        $this->info("  Baixando {$resourceName}...");
                        $jsonData = $this->curlGet($resource['url'], $timeout);
                        if (!$jsonData) {
                            $this->error("  ERRO ao baixar {$resourceName}");
                            $this->errors++;
                            continue;
                        }
                        $acordaos = json_decode($jsonData, true);
                        if (!is_array($acordaos) || empty($acordaos)) {
                            $this->error("  ERRO: JSON inválido em {$resourceName}");
                            $this->errors++;
                            continue;
                        }
                        $this->info("  Processando " . count($acordaos) . " acórdãos...");
                        if (!$dryRun) {
                            $this->processAcordaos($acordaos, $datasetName, $resourceName);
                        } else {
                            $this->info("  [DRY-RUN] Seriam processados " . count($acordaos) . " acórdãos");
                        }
                    } catch (\Exception $e) {
                        $this->error("  ERRO resource {$resourceName}: " . mb_substr($e->getMessage(), 0, 120));
                        $this->errors++;
                    }

                    unset($jsonData, $acordaos);
                }

            } catch (\Exception $e) {
                $this->error("  EXCEÇÃO em {$datasetName}: " . $e->getMessage());
                Log::error('justus:sync-stj exception', ['dataset' => $datasetName, 'error' => $e->getMessage()]);
                $this->errors++;
            }

            $this->newLine();
        }

        $this->newLine();
        $this->info('=== RESULTADO ===');
        $this->info("Importados: {$this->imported}");
        $this->info("Atualizados: {$this->updated}");
        $this->info("Pulados: {$this->skipped}");
        $this->info("Erros: {$this->errors}");
        $this->info("Total na base: " . JustusJurisprudencia::count());

        Log::info('justus:sync-stj concluído', [
            'imported' => $this->imported,
            'updated' => $this->updated,
            'skipped' => $this->skipped,
            'errors' => $this->errors,
        ]);

        return $this->errors > 0 ? 1 : 0;
    }

    private function processAcordaos(array $acordaos, string $datasetName, string $resourceName): void
    {
        $batch = [];
        $batchSize = 100;

        foreach ($acordaos as $ac) {
            $stjId = $ac['id'] ?? null;
            if (!$stjId || empty($ac['ementa'])) {
                continue;
            }

            $dataDecisao = null;
            if (!empty($ac['dataDecisao']) && strlen($ac['dataDecisao']) === 8) {
                try {
                    $dataDecisao = substr($ac['dataDecisao'], 0, 4) . '-' . substr($ac['dataDecisao'], 4, 2) . '-' . substr($ac['dataDecisao'], 6, 2);
                } catch (\Exception $e) {
                    $dataDecisao = null;
                }
            }

            $orgao = $ac['nomeOrgaoJulgador'] ?? '';
            $areaDireito = JustusJurisprudencia::inferAreaDireito($orgao);

            $record = [
                'stj_id' => $stjId,
                'tribunal' => 'STJ',
                'numero_processo' => $ac['numeroProcesso'] ?? null,
                'numero_registro' => $ac['numeroRegistro'] ?? null,
                'numero_documento' => $ac['numeroDocumento'] ?? null,
                'sigla_classe' => $ac['siglaClasse'] ?? null,
                'descricao_classe' => $ac['descricaoClasse'] ?? null,
                'classe_padronizada' => $ac['classePadronizada'] ?? null,
                'orgao_julgador' => $orgao,
                'relator' => $ac['ministroRelator'] ?? null,
                'data_publicacao' => $ac['dataPublicacao'] ?? null,
                'data_decisao' => $dataDecisao,
                'ementa' => $ac['ementa'],
                'tipo_decisao' => $ac['tipoDeDecisao'] ?? null,
                'decisao' => $ac['decisao'] ?? null,
                'tese_juridica' => $ac['teseJuridica'] ?? null,
                'termos_auxiliares' => $ac['termosAuxiliares'] ?? null,
                'referencias_legislativas' => !empty($ac['referenciasLegislativas']) ? json_encode($ac['referenciasLegislativas']) : null,
                'acordaos_similares' => !empty($ac['acordaosSimilares']) ? json_encode($ac['acordaosSimilares']) : null,
                'area_direito' => $areaDireito,
                'fonte_dataset' => $datasetName,
                'fonte_resource' => $resourceName,
                'updated_at' => now(),
            ];

            $batch[] = $record;

            if (count($batch) >= $batchSize) {
                $this->upsertBatch($batch);
                $batch = [];
            }
        }

        if (!empty($batch)) {
            $this->upsertBatch($batch);
        }
    }

    private function upsertBatch(array $batch): void
    {
        foreach ($batch as $record) {
            try {
                $record = $this->truncateFields($record);
                $existing = DB::table('justus_jurisprudencia')->where('stj_id', $record['stj_id'])->exists();

                if ($existing) {
                    DB::table('justus_jurisprudencia')
                        ->where('stj_id', $record['stj_id'])
                        ->update($record);
                    $this->updated++;
                } else {
                    $record['created_at'] = now();
                    DB::table('justus_jurisprudencia')->insert($record);
                    $this->imported++;
                }
            } catch (\Exception $e) {
                $this->errors++;
            }
        }
    }

    private function truncateFields(array $record): array
    {
        $limits = [
            'sigla_classe' => 80, 'classe_padronizada' => 80, 'tipo_decisao' => 50,
            'relator' => 100, 'orgao_julgador' => 80, 'data_publicacao' => 100,
            'area_direito' => 30, 'tribunal' => 10, 'stj_id' => 20,
        ];
        foreach ($limits as $field => $max) {
            if (!empty($record[$field]) && mb_strlen($record[$field]) > $max) {
                $record[$field] = mb_substr($record[$field], 0, $max);
            }
        }
        return $record;
    }

    private function curlGet(string $url, int $timeout = 30): ?string
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'MayerAdvogados-JUSTUS/1.0');
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error || $httpCode !== 200) {
            Log::warning('justus:sync-stj curl error', ['url' => $url, 'http_code' => $httpCode, 'error' => $error]);
            return null;
        }

        return $response;
    }
}
