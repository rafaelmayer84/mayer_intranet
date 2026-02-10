<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\DataJuriService;
use Illuminate\Support\Facades\DB;

class SyncMovimentosVerbose extends Command
{
    protected $signature = 'sync:movimentos-verbose {--limit=500}';
    protected $description = 'Sync movimentos com progresso visível';

    public function handle()
    {
        $this->info('=== SYNC MOVIMENTOS COM PROGRESSO ===');
        
        $service = app(DataJuriService::class);
        
        $this->info('Autenticando...');
        $service->authenticate();
        $this->info('✅ Autenticado!');
        
        $limit = (int) $this->option('limit');
        $pagina = 1;
        $totalSync = 0;
        $erros = 0;
        
        do {
            $this->line("📄 Buscando página {$pagina}...");
            
            $response = $service->getMovimentos($pagina, 100);
            $rows = $response['rows'] ?? [];
            $total = $response['listSize'] ?? 0;
            
            if ($pagina === 1) {
                $this->info("📊 Total na API: {$total} movimentos");
            }
            
            if (empty($rows)) {
                $this->warn('Nenhum registro na página.');
                break;
            }
            
            foreach ($rows as $mov) {
                $id = $mov['id'] ?? null;
                if (!$id) continue;
                
                try {
                    $data = $this->parseData($mov['data'] ?? null);
                    $valor = $this->parseValor($mov['valorComSinal'] ?? 0);
                    $plano = $mov['planoConta.nomeCompleto'] ?? $mov['planoConta']['nomeCompleto'] ?? '';
                    $codigo = $this->extrairCodigo($plano);
                    
                    DB::table('movimentos')->updateOrInsert(
                        ['datajuri_id' => $id],
                        [
                            'data' => $data,
                            'valor' => $valor,
                            'descricao' => mb_substr($mov['descricao'] ?? '', 0, 500),
                            'plano_contas' => mb_substr($plano, 0, 255),
                            'codigo_plano' => $codigo,
                            'conciliado' => ($mov['conciliado'] ?? '') === 'Sim' ? 1 : 0,
                            'ano' => $data ? (int) date('Y', strtotime($data)) : null,
                            'mes' => $data ? (int) date('n', strtotime($data)) : null,
                            'classificacao' => $this->classificar($codigo),
                            'updated_at' => now(),
                            'created_at' => now(),
                        ]
                    );
                    $totalSync++;
                } catch (\Exception $e) {
                    $erros++;
                }
            }
            
            $this->info("  ✅ Página {$pagina}: +".count($rows)." | Total: {$totalSync} | Erros: {$erros}");
            
            $pagina++;
            
        } while (count($rows) === 100 && $totalSync < $limit);
        
        $this->newLine();
        $this->info("=== CONCLUÍDO ===");
        $this->info("Sincronizados: {$totalSync}");
        $this->info("Erros: {$erros}");
        
        $jan = DB::table('movimentos')->where('ano', 2026)->where('mes', 1)->count();
        $this->info("Jan/2026: {$jan} movimentos");
        
        return 0;
    }
    
    private function parseData($data)
    {
        if (!$data) return null;
        if (preg_match('/(\d{2})\/(\d{2})\/(\d{4})/', $data, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }
        return $data;
    }
    
    private function parseValor($valor)
    {
        if (is_numeric($valor)) return (float) $valor;
        if (!is_string($valor)) return 0.0;
        
        $negativo = stripos($valor, 'negativo') !== false || strpos($valor, '-') !== false;
        $valor = strip_tags($valor);
        $valor = str_replace(['.', ','], ['', '.'], $valor);
        $float = (float) preg_replace('/[^0-9.\-]/', '', $valor);
        
        return $negativo && $float > 0 ? -$float : $float;
    }
    
    private function extrairCodigo($plano)
    {
        if (preg_match('/(\d+\.\d+\.\d+\.\d+)/', $plano, $m)) return $m[1];
        if (preg_match('/(\d+\.\d+\.\d+)/', $plano, $m)) return $m[1];
        if (preg_match('/(\d+\.\d+)/', $plano, $m)) return $m[1];
        return null;
    }
    
    private function classificar($codigo)
    {
        if (!$codigo) return null;
        if (in_array($codigo, ['3.01.01.01', '3.01.01.03'])) return 'RECEITA_PF';
        if (in_array($codigo, ['3.01.01.02', '3.01.01.05'])) return 'RECEITA_PJ';
        if (str_starts_with($codigo, '3.01.02') || str_starts_with($codigo, '3.01.03')) return 'DEDUCAO';
        if (str_starts_with($codigo, '3.02')) return 'DESPESA';
        return null;
    }
}
