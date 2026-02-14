<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\IntegrationLog;

class IntegracaoController extends Controller
{
    /**
     * Exibir página de integrações
     */
    public function index()
    {
        // Buscar logs de integração
        $recentLogs = IntegrationLog::orderBy('created_at', 'desc')->take(50)->get();
        
        // Calcular estatísticas
        $stats = [
            'total_syncs' => IntegrationLog::count(),
            'successful_syncs' => IntegrationLog::where('status', 'concluido')->count(),
            'failed_syncs' => IntegrationLog::where('status', 'erro')->count(),
            'last_sync' => IntegrationLog::orderBy('created_at', 'desc')->first(),
        ];
        
        return view('integracao.index', compact('stats', 'recentLogs'));
    }
    
    /**
     * Executar sincronização
     */
    public function sync(Request $request)
    {
        $type = $request->input('type', 'all');
        $syncId = Str::uuid();
        
        try {
            // Determinar tipo de sincronização
            $tipoSync = match($type) {
                'datajuri' => 'sync_clientes',
                'espocrm' => 'sync_leads',
                default => 'sync_full',
            };
            
            // Criar log de sincronização
            $log = IntegrationLog::create([
                'sync_id' => $syncId,
                'tipo' => $tipoSync,
                'fonte' => $type === 'all' ? 'manual' : ($type === 'datajuri' ? 'datajuri' : 'espocrm'),
                'status' => 'iniciado',
                'inicio' => now(),
                'registros_processados' => 0,
            ]);
            
            // Executar sincronização baseado no tipo
            $resultado = match($type) {
                'datajuri' => $this->sincronizarDataJuri($log),
                'espocrm' => $this->sincronizarEspoCRM($log),
                'all' => $this->sincronizarTudo($log),
                default => ['sucesso' => false, 'erro' => 'Tipo inválido'],
            };
            
            // Atualizar log com resultado
            if ($resultado['sucesso']) {
                $log->update([
                    'status' => 'concluido',
                    'registros_processados' => $resultado['registros'] ?? 0,
                    'fim' => now(),
                    'duracao_segundos' => now()->diffInSeconds($log->inicio),
                ]);
                
                return redirect()->route('integration.index')
                    ->with('success', "Sincronização concluída com sucesso! {$resultado['registros']} registros processados.");
            } else {
                $log->update([
                    'status' => 'erro',
                    'mensagem_erro' => $resultado['erro'] ?? 'Erro desconhecido',
                    'fim' => now(),
                    'duracao_segundos' => now()->diffInSeconds($log->inicio),
                ]);
                
                return redirect()->route('integration.index')
                    ->with('error', "Erro na sincronização: " . ($resultado['erro'] ?? 'Erro desconhecido'));
            }
        } catch (\Exception $e) {
            // Atualizar log com erro
            if (isset($log)) {
                $log->update([
                    'status' => 'erro',
                    'mensagem_erro' => $e->getMessage(),
                    'fim' => now(),
                    'duracao_segundos' => now()->diffInSeconds($log->inicio),
                ]);
            }
            
            return redirect()->route('integration.index')
                ->with('error', "Erro na sincronização: " . $e->getMessage());
        }
    }
    
    /**
     * Sincronizar DataJuri
     */
    private function sincronizarDataJuri($log)
    {
        try {
            // Aqui seria a chamada real ao serviço DataJuri
            // Por enquanto, simular com dados aleatórios
            $registros = rand(50, 200);
            
            // Simular processamento
            sleep(1);
            
            return [
                'sucesso' => true,
                'registros' => $registros,
            ];
        } catch (\Exception $e) {
            return [
                'sucesso' => false,
                'erro' => $e->getMessage(),
            ];
        }
    }
    
    /**
     * Sincronizar ESPO CRM
     */
    private function sincronizarEspoCRM($log)
    {
        try {
            // Aqui seria a chamada real ao serviço ESPO CRM
            // Por enquanto, simular com dados aleatórios
            $registros = rand(50, 200);
            
            // Simular processamento
            sleep(1);
            
            return [
                'sucesso' => true,
                'registros' => $registros,
            ];
        } catch (\Exception $e) {
            return [
                'sucesso' => false,
                'erro' => $e->getMessage(),
            ];
        }
    }
    
    /**
     * Sincronizar tudo
     */
    private function sincronizarTudo($log)
    {
        try {
            $totalRegistros = 0;
            
            // Sincronizar DataJuri
            $resultadoDataJuri = $this->sincronizarDataJuri($log);
            if ($resultadoDataJuri['sucesso']) {
                $totalRegistros += $resultadoDataJuri['registros'];
            }
            
            // Sincronizar ESPO CRM
            $resultadoEspoCRM = $this->sincronizarEspoCRM($log);
            if ($resultadoEspoCRM['sucesso']) {
                $totalRegistros += $resultadoEspoCRM['registros'];
            }
            
            return [
                'sucesso' => true,
                'registros' => $totalRegistros,
            ];
        } catch (\Exception $e) {
            return [
                'sucesso' => false,
                'erro' => $e->getMessage(),
            ];
        }
    }
    
    /**
     * Exibir detalhes de uma sincronização
     */
    public function show(IntegrationLog $log)
    {
        return view('integracao.show', compact('log'));
    }
}
