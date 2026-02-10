<?php

namespace App\Console\Commands;

use App\Services\DataJuriService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncContasReceberRapido extends Command
{
    protected $signature = 'sync:contas-receber-rapido';
    protected $description = 'Sincroniza Contas a Receber do DataJuri (versão otimizada)';

    public function handle(DataJuriService $dj): int
    {
        $params = ['campos' => 'id,dataVencimento,valor,status,pessoa.nome,descricao,dataPagamento'];
        
        $page = 1;
        $pageSize = 100;
        $total = 0;
        
        $this->info("Iniciando sync de ContasReceber...");
        
        while (true) {
            $resultado = $dj->buscarModuloPagina('ContasReceber', $page, $pageSize, $params);
            $rows = $resultado['rows'] ?? [];
            
            if (empty($rows)) break;
            
            foreach ($rows as $row) {
                $nome = $row['pessoa.nome'] ?? null;
                $id = $row['id'] ?? null;
                if (!$id) continue;
                
                $valorRaw = $row['valor'] ?? '0';
                $valor = (float)str_replace(['.', ','], ['', '.'], $valorRaw);
                
                $dataVenc = null;
                if (!empty($row['dataVencimento'])) {
                    try {
                        $dataVenc = \Carbon\Carbon::createFromFormat('d/m/Y', $row['dataVencimento'])->toDateString();
                    } catch (\Exception $e) {}
                }
                
                DB::table('contas_receber')->updateOrInsert(
                    ['datajuri_id' => $id],
                    [
                        'cliente' => $nome,
                        'valor' => $valor,
                        'status' => $row['status'] ?? 'Desconhecido',
                        'data_vencimento' => $dataVenc,
                        'updated_at' => now(),
                    ]
                );
                $total++;
            }
            
            $this->line("Página $page: " . count($rows) . " registros (total: $total)");
            
            if (count($rows) < $pageSize) break;
            $page++;
        }
        
        $this->info("✅ Sincronizados $total registros!");
        return self::SUCCESS;
    }
}
