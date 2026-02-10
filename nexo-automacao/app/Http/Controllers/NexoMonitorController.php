<?php

namespace App\Http\Controllers;

use App\Models\NexoAutomationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NexoMonitorController extends Controller
{
    public function index()
    {
        $estatisticasHoje = $this->obterEstatisticasHoje();
        $ultimasAutomacoes = $this->obterUltimasAutomacoes();
        $graficoHora = $this->obterGraficoUltimaHora();

        return view('nexo.monitor', compact(
            'estatisticasHoje',
            'ultimasAutomacoes',
            'graficoHora'
        ));
    }

    public function dados()
    {
        return response()->json([
            'estatisticas' => $this->obterEstatisticasHoje(),
            'ultimas' => $this->obterUltimasAutomacoes(5),
            'grafico' => $this->obterGraficoUltimaHora()
        ]);
    }

    private function obterEstatisticasHoje(): array
    {
        $hoje = NexoAutomationLog::hoje();

        return [
            'total_tentativas' => $hoje->count(),
            'sucesso' => $hoje->sucesso()->count(),
            'falhas' => $hoje->falha()->count(),
            'taxa_sucesso' => $hoje->count() > 0 
                ? round(($hoje->sucesso()->count() / $hoje->count()) * 100, 1)
                : 0
        ];
    }

    private function obterUltimasAutomacoes(int $limit = 10): array
    {
        return NexoAutomationLog::with('cliente')
            ->recentes($limit)
            ->get()
            ->map(function($log) {
                return [
                    'id' => $log->id,
                    'horario' => $log->created_at->format('H:i'),
                    'telefone' => $log->telefone,
                    'nome' => $log->cliente?->nome_mae ?? 'Não identificado',
                    'acao' => $this->traduzirAcao($log->acao),
                    'status' => $this->obterStatus($log->acao),
                    'processo' => $log->cliente?->numero_processo,
                    'dados' => $log->dados
                ];
            })
            ->toArray();
    }

    private function obterGraficoUltimaHora(): array
    {
        $dados = DB::table('nexo_automation_logs')
            ->select(
                DB::raw('DATE_FORMAT(created_at, "%H:%i") as hora'),
                DB::raw('COUNT(*) as total')
            )
            ->where('created_at', '>=', now()->subHour())
            ->groupBy('hora')
            ->orderBy('hora')
            ->get();

        return [
            'labels' => $dados->pluck('hora')->toArray(),
            'values' => $dados->pluck('total')->toArray()
        ];
    }

    private function traduzirAcao(string $acao): string
    {
        return match($acao) {
            'identificacao' => 'Identificação',
            'auth_sucesso' => 'Autenticado',
            'auth_falha' => 'Falha Auth',
            'auth_bloqueio' => 'Bloqueado',
            'consulta_status' => 'Status Processo',
            'erro' => 'Erro',
            default => $acao
        };
    }

    private function obterStatus(string $acao): string
    {
        return match($acao) {
            'auth_sucesso', 'consulta_status' => 'sucesso',
            'auth_falha', 'auth_bloqueio', 'erro' => 'erro',
            default => 'pendente'
        };
    }
}
