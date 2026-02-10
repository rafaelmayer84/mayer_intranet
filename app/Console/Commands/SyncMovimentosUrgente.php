<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class SyncMovimentosUrgente extends Command
{
    protected $signature = 'sync:movimentos-urgente {--limit=10000}';
    protected $description = 'Sync movimentos com progresso - URGENTE';

    private $token;
    private $baseUrl = 'https://api.datajuri.com.br';

    public function handle()
    {
        $this->info('=== SYNC MOVIMENTOS URGENTE ===');
        $this->newLine();

        // Autenticar
        $this->info('[1/4] Autenticando...');
        if (!$this->authenticate()) {
            $this->error('Falha na autenticação!');
            return 1;
        }
        $this->info('✅ Autenticado!');

        // Buscar movimentos
        $this->info('[2/4] Buscando movimentos da API...');
        $limit = (int) $this->option('limit');
        $movimentos = $this->fetchMovimentos($limit);
        $this->info('✅ ' . count($movimentos) . ' movimentos obtidos');

        // Carregar regras
        $this->info('[3/4] Carregando regras de classificação...');
        $regras = DB::table('classificacao_regras')
            ->where('ativo', true)
            ->pluck('classificacao', 'codigo_plano')
            ->toArray();
        $this->info('✅ ' . count($regras) . ' regras carregadas');

        // Inserir
        $this->info('[4/4] Inserindo no banco...');
        $bar = $this->output->createProgressBar(count($movimentos));
        $bar->start();

        $inseridos = 0;
        $erros = 0;

        foreach ($movimentos as $mov) {
            try {
                $id = $mov['id'] ?? null;
                if (!$id) continue;

                $data = $this->parseData($mov['data'] ?? null);
                $valor = $this->parseValor($mov['valorComSinal'] ?? '0');
                $planoCompleto = $mov['planoConta.nomeCompleto'] ?? $mov['planoConta']['nomeCompleto'] ?? '';
                $codigoPlano = $this->extrairCodigo($planoCompleto);
                $classificacao = $this->classificar($codigoPlano, $valor, $regras);

                DB::table('movimentos')->updateOrInsert(
                    ['datajuri_id' => $id],
                    [
                        'origem' => 'datajuri',
                        'data' => $data,
                        'valor' => $valor,
                        'descricao' => mb_substr($mov['descricao'] ?? '', 0, 500),
                        'plano_contas' => mb_substr($planoCompleto, 0, 255),
                        'codigo_plano' => $codigoPlano,
                        'conciliado' => ($mov['conciliado'] ?? '') === 'Sim' ? 1 : 0,
                        'ano' => $data ? (int) date('Y', strtotime($data)) : null,
                        'mes' => $data ? (int) date('n', strtotime($data)) : null,
                        'classificacao' => $classificacao,
                        'proprietario_nome' => $mov['proprietario.nome'] ?? $mov['proprietario']['nome'] ?? null,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
                $inseridos++;
            } catch (\Exception $e) {
                $erros++;
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // Resultado
        $this->info('=== RESULTADO ===');
        $this->info("Inseridos: {$inseridos}");
        $this->info("Erros: {$erros}");

        // Verificar
        $total = DB::table('movimentos')->count();
        $jan26 = DB::table('movimentos')->where('ano', 2026)->where('mes', 1)->count();
        $this->newLine();
        $this->info("Total no banco: {$total}");
        $this->info("Jan/2026: {$jan26}");

        // Distribuição
        $this->newLine();
        $this->info('=== DISTRIBUIÇÃO POR CLASSIFICAÇÃO ===');
        $dist = DB::table('movimentos')
            ->select('classificacao', DB::raw('count(*) as qtd'), DB::raw('sum(valor) as total'))
            ->groupBy('classificacao')
            ->orderBy('qtd', 'desc')
            ->get();
        foreach ($dist as $d) {
            $class = $d->classificacao ?? 'NULL';
            $this->line("{$class}: {$d->qtd} | R$ " . number_format($d->total, 2, ',', '.'));
        }

        // Limpar cache
        \Illuminate\Support\Facades\Cache::flush();
        $this->info('Cache limpo!');

        return 0;
    }

    private function authenticate(): bool
    {
        $clientId = config('services.datajuri.client_id') ?: env('DATAJURI_CLIENT_ID');
        $secretId = config('services.datajuri.secret_id') ?: env('DATAJURI_SECRET_ID');
        $username = config('services.datajuri.username') ?: env('DATAJURI_USERNAME');
        $password = config('services.datajuri.password') ?: env('DATAJURI_PASSWORD');

        $credentials = base64_encode("{$clientId}@{$secretId}");

        $response = Http::withHeaders([
            'Authorization' => "Basic {$credentials}",
            'Content-Type' => 'application/x-www-form-urlencoded',
        ])->asForm()->post("{$this->baseUrl}/oauth/token", [
            'grant_type' => 'password',
            'username' => $username,
            'password' => $password,
        ]);

        if ($response->successful()) {
            $this->token = $response->json()['access_token'] ?? null;
            return (bool) $this->token;
        }

        return false;
    }

    private function fetchMovimentos(int $limit): array
    {
        $all = [];
        $page = 1;
        $pageSize = 100;

        while (count($all) < $limit) {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->token}",
                'Content-Type' => 'application/json',
            ])->get("{$this->baseUrl}/v1/entidades/Movimento", [
                'pagina' => $page,
                'porPagina' => $pageSize,
                'campos' => 'id,data,valorComSinal,descricao,planoConta.nomeCompleto,conciliado,proprietario.nome',
            ]);

            if (!$response->successful()) {
                $this->warn("Erro na página {$page}");
                break;
            }

            $data = $response->json();
            $rows = $data['rows'] ?? [];

            if (empty($rows)) break;

            $all = array_merge($all, $rows);
            $this->line("  Página {$page}: +" . count($rows) . " (total: " . count($all) . ")");

            if (count($rows) < $pageSize) break;
            $page++;
        }

        return $all;
    }

    private function parseData($data): ?string
    {
        if (!$data) return null;
        if (preg_match('/(\d{2})\/(\d{2})\/(\d{4})/', $data, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }
        return $data;
    }

    private function parseValor($valor): float
    {
        if (is_numeric($valor)) return (float) $valor;
        if (!is_string($valor)) return 0.0;

        $negativo = stripos($valor, 'negativo') !== false || strpos($valor, '-') !== false;
        $valor = strip_tags($valor);
        $valor = str_replace(['.', ','], ['', '.'], $valor);
        $float = (float) preg_replace('/[^0-9.\-]/', '', $valor);

        return $negativo && $float > 0 ? -$float : $float;
    }

    private function extrairCodigo(?string $plano): ?string
    {
        if (!$plano) return null;
        // 5 níveis
        if (preg_match('/(\d+\.\d+\.\d+\.\d+\.\d+)/', $plano, $m)) return $m[1];
        // 4 níveis
        if (preg_match('/(\d+\.\d+\.\d+\.\d+)/', $plano, $m)) return $m[1];
        // 3 níveis
        if (preg_match('/(\d+\.\d+\.\d+)/', $plano, $m)) return $m[1];
        return null;
    }

    private function classificar(?string $codigo, float $valor, array $regras): ?string
    {
        if (!$codigo) return 'PENDENTE_CLASSIFICACAO';

        // Busca exata
        if (isset($regras[$codigo])) {
            return $regras[$codigo];
        }

        // Busca parcial (código filho)
        foreach ($regras as $codigoRegra => $classificacao) {
            if (str_starts_with($codigo, $codigoRegra . '.')) {
                return $classificacao;
            }
        }

        // Inferência padrão
        if (in_array($codigo, ['3.01.01.01', '3.01.01.03', '3.01.01.06'])) return 'RECEITA_PF';
        if (in_array($codigo, ['3.01.01.02', '3.01.01.05', '3.01.01.07'])) return 'RECEITA_PJ';
        if (str_starts_with($codigo, '3.01.02')) return 'RECEITA_FINANCEIRA';
        if (str_starts_with($codigo, '3.01.03')) return 'DEDUCAO';
        if (str_starts_with($codigo, '3.02')) return 'DESPESA';
        if (str_starts_with($codigo, '3.03')) return 'DESPESA';
        if (str_starts_with($codigo, '3.04')) return 'DESPESA_FINANCEIRA';

        return 'PENDENTE_CLASSIFICACAO';
    }
}
