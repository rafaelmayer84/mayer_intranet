<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Lead;
use App\Models\Oportunidade;
use Illuminate\Http\Request;

class ClientesMercadoController extends Controller
{
    public function index(Request $request)
    {
        $kpis = $this->calcularKPIs();
        return view('clientes-mercado.index', compact('kpis'));
    }

    private function calcularKPIs()
    {
        $totalClientes = Cliente::count();
        $clientesAtivos = Cliente::where('status', 'ativo')->count();
        $totalLeads = Lead::count();
        $totalOportunidades = Oportunidade::count();
        $oportunidadesAbertas = Oportunidade::whereIn('estagio', ['prospectando', 'qualificacao', 'proposta', 'negociacao'])->count();

        $valorCarteira = Cliente::where('status', 'ativo')->sum('valor_carteira') ?? 0;
        $valorOportunidades = Oportunidade::sum('valor') ?? 0;
        $ticketMedio = $clientesAtivos > 0 ? $valorCarteira / $clientesAtivos : 0;

        $leadsConvertidos = Oportunidade::whereNotNull('lead_id')->count();
        $taxaConversao = $totalLeads > 0 ? ($leadsConvertidos / $totalLeads) * 100 : 0;

        $clientesPF = Cliente::where('tipo', 'PF')->count();
        $clientesPJ = Cliente::where('tipo', 'PJ')->count();

        return [
            'total_clientes' => $totalClientes,
            'clientes_ativos' => $clientesAtivos,
            'total_leads' => $totalLeads,
            'total_oportunidades' => $totalOportunidades,
            'oportunidades_abertas' => $oportunidadesAbertas,
            'valor_carteira' => $valorCarteira,
            'valor_oportunidades' => $valorOportunidades,
            'ticket_medio' => $ticketMedio,
            'taxa_conversao' => $taxaConversao,
            'taxa_retencao' => $this->calcularTaxaRetencao(),
            'concentracao_top10' => $this->calcularConcentracao(),
            'crescimento_mom' => $this->calcularCrescimentoMoM(),
            'win_rate' => $this->calcularWinRate(),
            'clientes_pf' => $clientesPF,
            'clientes_pj' => $clientesPJ,
            'novos_mes' => $this->calcularNovosMes(),
            'valor_medio_oportunidade' => $this->calcularValorMedioOportunidade(),
        ];
    }

    private function calcularTaxaRetencao()
    {
        $clientesInicioAno = Cliente::where('created_at', '<=', now()->subMonths(12))->count();
        if ($clientesInicioAno == 0) {
            return 0;
        }
        $clientesAindaAtivos = Cliente::where('created_at', '<=', now()->subMonths(12))
            ->where('status', 'ativo')
            ->count();
        return ($clientesAindaAtivos / $clientesInicioAno) * 100;
    }

    private function calcularConcentracao()
    {
        $valorTotal = Cliente::sum('valor_carteira') ?? 0;
        if ($valorTotal == 0) {
            return 0;
        }
        $valorTop10 = Cliente::orderBy('valor_carteira', 'desc')
            ->limit(10)
            ->sum('valor_carteira') ?? 0;
        return ($valorTop10 / $valorTotal) * 100;
    }

    private function calcularCrescimentoMoM()
    {
        $mesAtual = Cliente::whereYear('created_at', date('Y'))
            ->whereMonth('created_at', date('n'))
            ->count();
        $mesAnterior = Cliente::whereYear('created_at', date('Y'))
            ->whereMonth('created_at', date('n') - 1)
            ->count();
        if ($mesAnterior == 0) {
            return 0;
        }
        return (($mesAtual - $mesAnterior) / $mesAnterior) * 100;
    }

    private function calcularWinRate()
    {
        $totalOportunidades = Oportunidade::whereIn('estagio', ['ganha', 'perdida'])->count();
        if ($totalOportunidades == 0) {
            return 0;
        }
        $oportunidadesGanhas = Oportunidade::where('estagio', 'ganha')->count();
        return ($oportunidadesGanhas / $totalOportunidades) * 100;
    }

    private function calcularNovosMes()
    {
        return Cliente::whereYear('created_at', date('Y'))
            ->whereMonth('created_at', date('n'))
            ->count();
    }

    private function calcularValorMedioOportunidade()
    {
        $total = Oportunidade::count();
        if ($total == 0) {
            return 0;
        }
        $soma = Oportunidade::sum('valor') ?? 0;
        return $soma / $total;
    }

    public function topClientes()
    {
        $clientes = Cliente::select('nome', 'valor_carteira as valor')
            ->where('status', 'ativo')
            ->orderBy('valor_carteira', 'desc')
            ->limit(10)
            ->get();
        return response()->json($clientes);
    }

    public function leadsRecentes()
    {
        $leads = Lead::select('nome', 'origem', 'created_at')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function($lead) {
                return [
                    'nome' => $lead->nome,
                    'origem' => $lead->origem ?? 'Não informado',
                    'data' => $lead->created_at->format('d/m/Y')
                ];
            });
        return response()->json($leads);
    }
}
