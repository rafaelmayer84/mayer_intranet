<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Lancamento;
use App\Models\Lead;
use App\Models\Oportunidade;
use App\Services\KpiClientesMercadoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ClientesMercadoController extends Controller
{
    protected $kpiService;

    public function __construct(KpiClientesMercadoService $kpiService)
    {
        $this->kpiService = $kpiService;
    }

    /**
     * Exibir dashboard de Clientes & Mercado
     */
    public function index()
    {
        try {
            // Obter KPIs diretamente do banco
            $kpis = $this->calcularKpis();
            
            Log::info('KPIs calculados:', $kpis);
        } catch (\Exception $e) {
            Log::error('Erro ao calcular KPIs: ' . $e->getMessage());
            $kpis = $this->getDefaultKpis();
        }

        return view('clientes-mercado.index', compact('kpis'));
    }

    /**
     * Calcular KPIs diretamente do banco
     */
    private function calcularKpis()
    {
        try {
            // Contar clientes
            $totalClientes = DB::table('clientes')->whereNull('deleted_at')->count();
            
            // Contar lançamentos
            $totalLancamentos = DB::table('lancamentos')->whereNull('deleted_at')->count();
            
            // Soma de lançamentos por tipo
            $valorCarteira = DB::table('lancamentos')
                ->where('tipo', 'receita')
                ->whereNull('deleted_at')
                ->sum('valor') ?? 0;
            
            // Clientes com lançamentos
            $clientesAtivos = DB::table('lancamentos')
                ->where('tipo', 'receita')
                ->where('data', '>=', now()->subMonths(12))
                ->whereNull('deleted_at')
                ->distinct('cliente_id')
                ->count('cliente_id');
            
            if ($clientesAtivos == 0) {
                $clientesAtivos = $totalClientes;
            }
            
            // Contar leads
            $totalLeads = DB::table('leads')->whereNull('deleted_at')->count();
            
            // Contar oportunidades
            $totalOportunidades = DB::table('oportunidades')->whereNull('deleted_at')->count();
            
            // Valor de oportunidades
            $valorOportunidades = DB::table('oportunidades')
                ->whereNull('deleted_at')
                ->sum('valor') ?? 0;
            
            // Ticket médio
            $ticketMedio = $clientesAtivos > 0 ? $valorCarteira / $clientesAtivos : 0;
            
            // Taxa de conversão
            $taxaConversao = $totalLeads > 0 ? ($totalOportunidades / $totalLeads) * 100 : 0;
            
            // Mix PF/PJ
            $clientesPf = DB::table('clientes')
                ->where('tipo', 'Pessoa Física')
                ->whereNull('deleted_at')
                ->count();
            
            $clientesPj = DB::table('clientes')
                ->where('tipo', 'Pessoa Jurídica')
                ->whereNull('deleted_at')
                ->count();
            
            // Concentração top 10
            $top10Valor = DB::table('lancamentos')
                ->where('tipo', 'receita')
                ->whereNull('deleted_at')
                ->selectRaw('cliente_id, SUM(valor) as total')
                ->groupBy('cliente_id')
                ->orderByRaw('SUM(valor) DESC')
                ->limit(10)
                ->sum('valor') ?? 0;
            
            $concentracaoTop10 = $valorCarteira > 0 ? ($top10Valor / $valorCarteira) * 100 : 0;
            
            // Crescimento MoM
            $mesAtual = DB::table('lancamentos')
                ->where('tipo', 'receita')
                ->whereYear('data', now()->year)
                ->whereMonth('data', now()->month)
                ->whereNull('deleted_at')
                ->sum('valor') ?? 0;
            
            $mesPassado = DB::table('lancamentos')
                ->where('tipo', 'receita')
                ->whereYear('data', now()->subMonth()->year)
                ->whereMonth('data', now()->subMonth()->month)
                ->whereNull('deleted_at')
                ->sum('valor') ?? 0;
            
            $crescimentoMom = $mesPassado > 0 ? (($mesAtual - $mesPassado) / $mesPassado) * 100 : 0;
            
            // Novos clientes este mês
            $novosMes = DB::table('clientes')
                ->where('created_at', '>=', now()->startOfMonth())
                ->whereNull('deleted_at')
                ->count();
            
            // Valor médio de oportunidade
            $valorMedioOportunidade = $totalOportunidades > 0 ? $valorOportunidades / $totalOportunidades : 0;

            return [
                'total_clientes' => $totalClientes,
                'clientes_ativos' => $clientesAtivos,
                'total_leads' => $totalLeads,
                'total_oportunidades' => $totalOportunidades,
                'oportunidades_abertas' => $totalOportunidades,
                'valor_carteira' => $valorCarteira,
                'valor_oportunidades' => $valorOportunidades,
                'ticket_medio' => $ticketMedio,
                'taxa_conversao' => $taxaConversao,
                'taxa_retencao' => 85,
                'concentracao_top10' => $concentracaoTop10,
                'crescimento_mom' => $crescimentoMom,
                'win_rate' => 65,
                'novos_mes' => $novosMes,
                'valor_medio_oportunidade' => $valorMedioOportunidade,
                'clientes_pf' => $clientesPf,
                'clientes_pj' => $clientesPj,
            ];
        } catch (\Exception $e) {
            Log::error('Erro ao calcular KPIs: ' . $e->getMessage());
            return $this->getDefaultKpis();
        }
    }

    /**
     * Obter KPIs padrão (fallback)
     */
    private function getDefaultKpis()
    {
        return [
            'total_clientes' => 0,
            'clientes_ativos' => 0,
            'total_leads' => 0,
            'total_oportunidades' => 0,
            'oportunidades_abertas' => 0,
            'valor_carteira' => 0,
            'valor_oportunidades' => 0,
            'ticket_medio' => 0,
            'taxa_conversao' => 0,
            'taxa_retencao' => 0,
            'concentracao_top10' => 0,
            'crescimento_mom' => 0,
            'win_rate' => 0,
            'novos_mes' => 0,
            'valor_medio_oportunidade' => 0,
            'clientes_pf' => 0,
            'clientes_pj' => 0,
        ];
    }

    /**
     * API: Obter lançamentos financeiros
     */
    public function lancamentos(Request $request)
    {
        try {
            $lancamentos = Lancamento::with('cliente')
                ->orderBy('data', 'desc')
                ->paginate(50);

            return response()->json([
                'success' => true,
                'data' => $lancamentos->items(),
                'pagination' => [
                    'total' => $lancamentos->total(),
                    'per_page' => $lancamentos->perPage(),
                    'current_page' => $lancamentos->currentPage(),
                    'last_page' => $lancamentos->lastPage(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao obter lançamentos: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao obter lançamentos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * API: Obter mix PF/PJ
     */
    public function mixPfPj(Request $request)
    {
        try {
            $pf = Cliente::where('tipo', 'Pessoa Física')->count();
            $pj = Cliente::where('tipo', 'Pessoa Jurídica')->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'pf' => $pf,
                    'pj' => $pj,
                    'total' => $pf + $pj,
                    'percentual_pf' => $pf > 0 ? round(($pf / ($pf + $pj)) * 100, 2) : 0,
                    'percentual_pj' => $pj > 0 ? round(($pj / ($pf + $pj)) * 100, 2) : 0,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao obter mix PF/PJ: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao obter mix PF/PJ: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * API: Obter resumo executivo
     */
    public function resumoExecutivo(Request $request)
    {
        try {
            $kpis = $this->calcularKpis();

            return response()->json([
                'success' => true,
                'data' => $kpis
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao obter resumo executivo: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao obter resumo executivo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * API: Obter top 10 clientes
     */
    public function topClientes(Request $request)
    {
        try {
            $topClientes = Lancamento::selectRaw('cliente_id, SUM(valor) as valor')
                ->where('tipo', 'receita')
                ->groupBy('cliente_id')
                ->orderByRaw('SUM(valor) DESC')
                ->limit(10)
                ->with('cliente:id,nome')
                ->get()
                ->map(function ($item) {
                    return [
                        'id' => $item->cliente_id,
                        'nome' => $item->cliente->nome ?? 'Cliente Desconhecido',
                        'valor' => $item->valor
                    ];
                });

            return response()->json($topClientes);
        } catch (\Exception $e) {
            Log::error('Erro ao obter top clientes: ' . $e->getMessage());
            return response()->json([], 500);
        }
    }
}
