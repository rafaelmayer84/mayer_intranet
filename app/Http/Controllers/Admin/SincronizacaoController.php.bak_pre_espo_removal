<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DataJuriSyncService;
use App\Services\EspoCrmSyncService;
use App\Models\IntegrationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class SincronizacaoController extends Controller
{
    /**
     * Exibir página de sincronização
     */
    public function index()
    {
        // Buscar últimos logs
        $logs = IntegrationLog::orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        // Estatísticas por sistema
        $stats = [
            'datajuri' => [
                'total' => IntegrationLog::where('sistema', 'DataJuri')->count(),
                'sucesso' => IntegrationLog::where('sistema', 'DataJuri')->where('status', 'sucesso')->count(),
                'erro' => IntegrationLog::where('sistema', 'DataJuri')->where('status', 'erro')->count(),
                'ultima_sync' => IntegrationLog::where('sistema', 'DataJuri')->where('status', 'sucesso')->latest()->first()?->created_at,
            ],
            'espocrm' => [
                'total' => IntegrationLog::where('sistema', 'ESPO CRM')->count(),
                'sucesso' => IntegrationLog::where('sistema', 'ESPO CRM')->where('status', 'sucesso')->count(),
                'erro' => IntegrationLog::where('sistema', 'ESPO CRM')->where('status', 'erro')->count(),
                'ultima_sync' => IntegrationLog::where('sistema', 'ESPO CRM')->where('status', 'sucesso')->latest()->first()?->created_at,
            ],
        ];

        return view('admin.sincronizacao.index', compact('logs', 'stats'));
    }

    /**
     * Disparar sincronização manual
     */
    public function sync(Request $request)
    {
        $sistema = $request->input('sistema'); // datajuri ou espocrm
        $entidade = $request->input('entidade', 'all');

        try {
            if ($sistema === 'datajuri') {
                Artisan::call('sync:datajuri', [
                    '--entity' => $entidade
                ]);
                $output = Artisan::output();
            } elseif ($sistema === 'espocrm') {
                Artisan::call('sync:espocrm', [
                    '--entity' => $entidade
                ]);
                $output = Artisan::output();
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Sistema inválido'
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => 'Sincronização iniciada com sucesso',
                'output' => $output
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao sincronizar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verificar status da API
     */
    public function checkStatus(Request $request)
    {
        $sistema = $request->input('sistema');

        try {
            if ($sistema === 'datajuri') {
                $service = new DataJuriSyncService();
                $auth = $service->authenticate();
                
                return response()->json([
                    'success' => $auth,
                    'message' => $auth ? 'DataJuri API online' : 'Falha na autenticação',
                    'sistema' => 'DataJuri'
                ]);
            } elseif ($sistema === 'espocrm') {
                $service = new EspoCrmSyncService();
                $result = $service->syncAccounts(); // Teste básico
                
                return response()->json([
                    'success' => true,
                    'message' => 'ESPO CRM API online',
                    'sistema' => 'ESPO CRM'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Sistema inválido'
            ], 400);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Limpar logs antigos
     */
    public function clearLogs()
    {
        try {
            // Deletar logs com mais de 30 dias
            IntegrationLog::where('created_at', '<', now()->subDays(30))->delete();

            return response()->json([
                'success' => true,
                'message' => 'Logs antigos removidos com sucesso'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao limpar logs: ' . $e->getMessage()
            ], 500);
        }
    }
}
