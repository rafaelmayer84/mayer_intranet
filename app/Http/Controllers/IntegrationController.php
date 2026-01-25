<?php

namespace App\Http\Controllers;

use App\Models\IntegrationLog;
use Illuminate\Http\Request;

class IntegrationController extends Controller
{
    /**
     * Exibir página de integrações
     */
    public function index()
    {
        // Obter logs de sincronização recentes
        $recentLogs = IntegrationLog::orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        // Estatísticas
        $stats = [
            'total_syncs' => IntegrationLog::count(),
            'successful_syncs' => IntegrationLog::where('status', 'success')->count(),
            'failed_syncs' => IntegrationLog::where('status', 'failed')->count(),
            'pending_syncs' => IntegrationLog::where('status', 'pending')->count(),
            'last_sync' => IntegrationLog::orderBy('created_at', 'desc')->first(),
        ];

        return view('integration.index', compact('recentLogs', 'stats'));
    }

    /**
     * Exibir detalhes de um log de sincronização
     */
    public function show(IntegrationLog $log)
    {
        return view('integration.show', compact('log'));
    }

    /**
     * Executar sincronização manual
     */
    public function sync(Request $request)
    {
        $type = $request->input('type', 'all'); // all, datajuri, espocrm

        try {
            $message = "Sincronização iniciada: {$type}";
            
            return redirect()->route('integration.index')
                ->with('success', $message);
        } catch (\Exception $e) {
            return redirect()->route('integration.index')
                ->with('error', 'Erro ao iniciar sincronização: ' . $e->getMessage());
        }
    }

    /**
     * Obter status de sincronização em tempo real (AJAX)
     */
    public function status()
    {
        $lastSync = IntegrationLog::orderBy('created_at', 'desc')->first();
        
        return response()->json([
            'last_sync' => $lastSync,
            'status' => $lastSync?->status ?? 'never',
            'timestamp' => now(),
        ]);
    }
}
