<?php

namespace App\Console\Commands;

use App\Models\Cliente;
use App\Models\Lancamento;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncLancamentos extends Command
{
    protected $signature = 'sync:lancamentos';
    protected $description = 'Sincronizar lançamentos financeiros do DataJuri';

    public function handle()
    {
        $this->info('Iniciando sincronização de lançamentos...');

        try {
            $baseUrl = config('services.datajuri.base_url');
            $apiKey = config('services.datajuri.api_key');

            if (!$baseUrl || !$apiKey) {
                $this->error('Credenciais do DataJuri não configuradas');
                return 1;
            }

            // Obter todos os clientes sincronizados
            $clientes = Cliente::where('source', 'datajuri')->get();
            $this->info("Encontrados {$clientes->count()} clientes do DataJuri");

            $totalLancamentos = 0;
            $erros = 0;

            foreach ($clientes as $cliente) {
                try {
                    // Obter lançamentos do cliente via API DataJuri
                    $response = Http::withHeaders([
                        'Authorization' => "Bearer {$apiKey}",
                        'Accept' => 'application/json',
                    ])->get("{$baseUrl}/api/clientes/{$cliente->external_id}/lancamentos");

                    if ($response->failed()) {
                        Log::warning("Erro ao obter lançamentos do cliente {$cliente->id}: " . $response->status());
                        $erros++;
                        continue;
                    }

                    $lancamentos = $response->json('data') ?? [];

                    foreach ($lancamentos as $lancamento) {
                        // Verificar se já existe
                        $existe = Lancamento::where('cliente_id', $cliente->id)
                            ->where('referencia', $lancamento['id'] ?? '')
                            ->exists();

                        if ($existe) {
                            continue;
                        }

                        // Criar novo lançamento
                        Lancamento::create([
                            'cliente_id' => $cliente->id,
                            'tipo' => strtolower($lancamento['tipo'] ?? 'receita'),
                            'valor' => floatval($lancamento['valor'] ?? 0),
                            'descricao' => $lancamento['descricao'] ?? '',
                            'data' => $lancamento['data'] ?? now(),
                            'referencia' => $lancamento['id'] ?? '',
                            'status' => $lancamento['status'] ?? 'pendente',
                        ]);

                        $totalLancamentos++;
                    }
                } catch (\Exception $e) {
                    Log::error("Erro ao sincronizar lançamentos do cliente {$cliente->id}: " . $e->getMessage());
                    $erros++;
                }
            }

            $this->info("✅ Sincronização concluída!");
            $this->info("   Lançamentos sincronizados: {$totalLancamentos}");
            $this->info("   Erros: {$erros}");

            return 0;
        } catch (\Exception $e) {
            $this->error("Erro na sincronização: " . $e->getMessage());
            Log::error("Erro na sincronização de lançamentos: " . $e->getMessage());
            return 1;
        }
    }
}
