<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\IntegrationLog;
use App\Services\DataJuriSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class IntegracoesController extends Controller
{
    public function index()
    {
        $logs = IntegrationLog::orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        $stats = [
            'total_syncs' => IntegrationLog::count(),
            'syncs_today' => IntegrationLog::whereDate('created_at', today())->count(),
            'syncs_success' => IntegrationLog::where('status', 'concluido')->count(),
            'syncs_error' => IntegrationLog::where('status', 'erro')->count(),
            'last_sync' => IntegrationLog::where('status', 'concluido')
                ->latest('created_at')
                ->first(),
        ];

        // Contagem de registros por tabela
        $counts = [
            'clientes' => DB::table('clientes')->count(),
            'processos' => DB::table('processos')->count(),
            'fases_processo' => DB::table('fases_processo')->count(),
            'movimentos' => DB::table('movimentos')->count(),
            'contratos' => DB::table('contratos')->count(),
            'atividades_datajuri' => DB::table('atividades_datajuri')->count(),
            'horas_trabalhadas_datajuri' => DB::table('horas_trabalhadas_datajuri')->count(),
            'ordens_servico' => DB::table('ordens_servico')->count(),
            'contas_receber' => DB::table('contas_receber')->count(),
        ];

        return view('admin.integracoes.index', compact('logs', 'stats', 'counts'));
    }

    public function checkStatus()
    {
        $status = [
            'datajuri' => $this->checkDataJuriStatus(),
            'espocrm' => $this->checkEspoCrmStatus(),
        ];

        return response()->json($status);
    }

    private function checkDataJuriStatus()
    {
        try {
            $service = app(DataJuriSyncService::class);
            $connected = $service->authenticate();
            return [
                'status' => $connected ? 'online' : 'offline',
                'message' => $connected ? 'Conexão OK' : 'Falha na conexão',
                'last_check' => now()->format('d/m/Y H:i:s'),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Erro: ' . $e->getMessage(),
                'last_check' => now()->format('d/m/Y H:i:s'),
            ];
        }
    }

    private function checkEspoCrmStatus()
    {
        try {
            // Tenta verificar se o service existe
            if (class_exists(\App\Services\EspoCrmService::class)) {
                $service = app(\App\Services\EspoCrmService::class);
                if (method_exists($service, 'testarConexao')) {
                    $connected = $service->testarConexao();
                    return [
                        'status' => $connected ? 'online' : 'offline',
                        'message' => $connected ? 'Conexão OK' : 'Falha na conexão',
                        'last_check' => now()->format('d/m/Y H:i:s'),
                    ];
                }
            }
            return [
                'status' => 'unknown',
                'message' => 'Service não configurado',
                'last_check' => now()->format('d/m/Y H:i:s'),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Erro: ' . $e->getMessage(),
                'last_check' => now()->format('d/m/Y H:i:s'),
            ];
        }
    }

    /**
     * Sincronização DataJuri com progresso
     */
    public function syncDataJuri(Request $request)
    {
        $modulo = $request->input('modulo', 'all');
        
        try {
            $service = app(DataJuriSyncService::class);
            
            if (!$service->authenticate()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Falha na autenticação com DataJuri'
                ], 401);
            }

            $startTime = microtime(true);
            $results = [];

            // Mapeamento de módulos
            $modulosMap = [
                'pessoas' => ['method' => 'syncPessoas', 'label' => 'Pessoas/Clientes', 'table' => 'clientes'],
                'processos' => ['method' => 'syncProcessos', 'label' => 'Processos', 'table' => 'processos'],
                'fases' => ['method' => 'syncFasesProcesso', 'label' => 'Fases do Processo', 'table' => 'fases_processo'],
                'movimentos' => ['method' => 'syncMovimentos', 'label' => 'Movimentos Financeiros', 'table' => 'movimentos'],
                'contratos' => ['method' => 'syncContratos', 'label' => 'Contratos', 'table' => 'contratos'],
                'atividades' => ['method' => 'syncAtividades', 'label' => 'Atividades', 'table' => 'atividades_datajuri'],
                'horas' => ['method' => 'syncHorasTrabalhadas', 'label' => 'Horas Trabalhadas', 'table' => 'horas_trabalhadas_datajuri'],
                'ordens' => ['method' => 'syncOrdensServico', 'label' => 'Ordens de Serviço', 'table' => 'ordens_servico'],
            ];

            if ($modulo === 'all') {
                foreach ($modulosMap as $key => $config) {
                    $method = $config['method'];
                    $count = $service->$method();
                    $results[$key] = [
                        'label' => $config['label'],
                        'count' => $count,
                        'table' => $config['table']
                    ];
                }
            } else if (isset($modulosMap[$modulo])) {
                $config = $modulosMap[$modulo];
                $method = $config['method'];
                $count = $service->$method();
                $results[$modulo] = [
                    'label' => $config['label'],
                    'count' => $count,
                    'table' => $config['table']
                ];
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Módulo inválido: ' . $modulo
                ], 400);
            }

            $duration = round(microtime(true) - $startTime, 2);
            $totalProcessed = array_sum(array_column($results, 'count'));

            // Registrar log
            IntegrationLog::create([
                'tipo' => $modulo === 'all' ? 'sync_completo' : 'sync_' . $modulo,
                'fonte' => 'datajuri',
                'status' => 'concluido',
                'registros_processados' => $totalProcessed,
                'registros_criados' => 0,
                'registros_atualizados' => $totalProcessed,
                'duracao_segundos' => $duration,
                'detalhes' => json_encode($results),
                'inicio' => now()->subSeconds($duration),
                'fim' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Sincronização concluída',
                'results' => $results,
                'total' => $totalProcessed,
                'duration' => $duration
            ]);

        } catch (\Exception $e) {
            Log::error('Sync DataJuri Error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            
            IntegrationLog::create([
                'tipo' => 'sync_' . $modulo,
                'fonte' => 'datajuri',
                'status' => 'erro',
                'registros_processados' => 0,
                'detalhes' => json_encode(['error' => $e->getMessage()]),
                'inicio' => now(),
                'fim' => now(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sincronização de módulo individual com streaming de progresso
     */
    public function syncModuloStream(Request $request, string $modulo)
    {
        return response()->stream(function () use ($modulo) {
            $service = app(DataJuriSyncService::class);
            
            $this->sendEvent('start', ['modulo' => $modulo, 'message' => 'Autenticando...']);
            
            if (!$service->authenticate()) {
                $this->sendEvent('error', ['message' => 'Falha na autenticação']);
                return;
            }

            $this->sendEvent('progress', ['step' => 1, 'message' => 'Autenticado. Iniciando sync...']);

            $modulosMap = [
                'pessoas' => 'syncPessoas',
                'processos' => 'syncProcessos',
                'fases' => 'syncFasesProcesso',
                'movimentos' => 'syncMovimentos',
                'contratos' => 'syncContratos',
                'atividades' => 'syncAtividades',
                'horas' => 'syncHorasTrabalhadas',
                'ordens' => 'syncOrdensServico',
            ];

            if (!isset($modulosMap[$modulo])) {
                $this->sendEvent('error', ['message' => 'Módulo inválido']);
                return;
            }

            $method = $modulosMap[$modulo];
            $count = $service->$method();

            $this->sendEvent('complete', [
                'modulo' => $modulo,
                'count' => $count,
                'message' => "Sincronizados {$count} registros"
            ]);

        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function sendEvent(string $event, array $data): void
    {
        echo "event: {$event}\n";
        echo "data: " . json_encode($data) . "\n\n";
        ob_flush();
        flush();
    }

    public function triggerSync(Request $request)
    {
        $tipo = $request->input('tipo', 'full');

        try {
            Artisan::call('sync:' . $tipo);
            $output = Artisan::output();

            return response()->json([
                'success' => true,
                'message' => 'Sincronização iniciada com sucesso',
                'output' => $output
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro: ' . $e->getMessage()
            ], 500);
        }
    }
}
