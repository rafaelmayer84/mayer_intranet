<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\IntegrationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class IntegracoesController extends Controller
{
    public function index()
    {
        $logs = IntegrationLog::orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        $stats = [
            'total_syncs' => IntegrationLog::count(),
            'syncs_today' => IntegrationLog::whereDate('created_at', today())->count(),
            'syncs_success' => IntegrationLog::where('status', 'concluido')->count(),
            'syncs_error' => IntegrationLog::where('status', 'erro')->count(),
            'last_sync' => IntegrationLog::where('status', 'concluido')
                ->latest('fim')
                ->first(),
        ];

        return view('admin.integracoes.index', compact('logs', 'stats'));
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
            $service = app(\App\Services\Integration\DataJuriService::class);
            $connected = $service->testarConexao();
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
            $service = app(\App\Services\Integration\EspoCrmService::class);
            $connected = $service->testarConexao();
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
