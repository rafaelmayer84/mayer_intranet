<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\Lancamento;
use App\Models\Lead;
use App\Models\Oportunidade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class KpiClientesMercadoService
{
    /**
     * Calcular todos os KPIs do dashboard Clientes & Mercado
     */
    public function calcularTodosKpis(int $mes = null, int $ano = null): array
    {
        $mes = $mes ?? now()->month;
        $ano = $ano ?? now()->year;

        return [
            // KPIs de Clientes
            'total_clientes' => $this->getTotalClientes(),
            'clientes_ativos' => $this->getClientesAtivos($mes, $ano),
            'clientes_inativos' => $this->getClientesInativos($mes, $ano),
            
            // KPIs Financeiros (baseado em LANÇAMENTOS)
            'valor_carteira' => $this->getValorCarteira(),
            'receita_total' => $this->getReceitaTotal($mes, $ano),
            'receita_mes_atual' => $this->getReceitaMesAtual(),
            'receita_mes_anterior' => $this->getReceitaMesAnterior(),
            'ticket_medio' => $this->getTicketMedio($mes, $ano),
            'concentracao_top10' => $this->getConcentracaoTop10(),
            'mix_pf_pj' => $this->getMixPfPj($mes, $ano),
            'crescimento_mom' => $this->getCrescimentoMoM(),
            'taxa_retencao' => $this->getTaxaRetencao(),
            
            // KPIs de Leads
            'total_leads' => $this->getTotalLeads(),
            'leads_novos_mes' => $this->getLeadsNovosMes($mes, $ano),
            'taxa_conversao_lead_cliente' => $this->getTaxaConversaoLeadCliente(),
            
            // KPIs de Oportunidades
            'total_oportunidades' => $this->getTotalOportunidades(),
            'oportunidades_abertas' => $this->getOportunidadesAbertas(),
            'valor_oportunidades' => $this->getValorOportunidades(),
            'valor_medio_oportunidade' => $this->getValorMedioOportunidade(),
            'pipeline_vendas' => $this->getPipelineVendas(),
            'win_rate' => $this->getWinRate(),
        ];
    }

    /**
     * Total de clientes cadastrados
     */
    public function getTotalClientes(): int
    {
        return Cliente::count();
    }

    /**
     * Clientes ativos (com receita no período)
     */
    public function getClientesAtivos(int $mes = null, int $ano = null): int
    {
        $mes = $mes ?? now()->month;
        $ano = $ano ?? now()->year;

        return Cliente::whereHas('lancamentos', function ($query) use ($mes, $ano) {
            $query->where('tipo', 'Receita')
                  ->whereYear('data_lancamento', $ano)
                  ->whereMonth('data_lancamento', $mes);
        })->count();
    }

    /**
     * Clientes inativos (sem receita no período)
     */
    public function getClientesInativos(int $mes = null, int $ano = null): int
    {
        return $this->getTotalClientes() - $this->getClientesAtivos($mes, $ano);
    }

    /**
     * Valor total da carteira (soma de todos os lançamentos de receita)
     */
    public function getValorCarteira(): float
    {
        return Lancamento::where('tipo', 'Receita')->sum('valor');
    }

    /**
     * Receita total do período
     */
    public function getReceitaTotal(int $mes = null, int $ano = null): float
    {
        $query = Lancamento::where('tipo', 'Receita');

        if ($mes && $ano) {
            $query->whereYear('data_lancamento', $ano)
                  ->whereMonth('data_lancamento', $mes);
        } elseif ($ano) {
            $query->whereYear('data_lancamento', $ano);
        }

        return $query->sum('valor');
    }

    /**
     * Receita do mês atual
     */
    public function getReceitaMesAtual(): float
    {
        return Lancamento::where('tipo', 'Receita')
            ->mesAtual()
            ->sum('valor');
    }

    /**
     * Receita do mês anterior
     */
    public function getReceitaMesAnterior(): float
    {
        return Lancamento::where('tipo', 'Receita')
            ->mesAnterior()
            ->sum('valor');
    }

    /**
     * Ticket médio por cliente (receita total / clientes com receita)
     */
    public function getTicketMedio(int $mes = null, int $ano = null): float
    {
        $receitaTotal = $this->getReceitaTotal($mes, $ano);
        $clientesAtivos = $this->getClientesAtivos($mes, $ano);

        return $clientesAtivos > 0 ? $receitaTotal / $clientesAtivos : 0;
    }

    /**
     * Concentração dos Top 10 clientes (% da receita total)
     */
    public function getConcentracaoTop10(): float
    {
        $receitaTotal = $this->getValorCarteira();

        if ($receitaTotal == 0) {
            return 0;
        }

        $top10Receita = Cliente::orderBy('valor_carteira', 'desc')
            ->limit(10)
            ->sum('valor_carteira');

        return ($top10Receita / $receitaTotal) * 100;
    }

    /**
     * Mix PF/PJ baseado em receita (não em contagem)
     */
    public function getMixPfPj(int $mes = null, int $ano = null): array
    {
        $receitaTotal = $this->getReceitaTotal($mes, $ano);

        if ($receitaTotal == 0) {
            return [
                'pf_percentual' => 0,
                'pj_percentual' => 0,
                'pf_valor' => 0,
                'pj_valor' => 0
            ];
        }

        // Buscar receita por tipo de cliente
        $query = Lancamento::select('clientes.tipo', DB::raw('SUM(lancamentos.valor) as total'))
            ->join('clientes', 'lancamentos.cliente_id', '=', 'clientes.id')
            ->where('lancamentos.tipo', 'Receita');

        if ($mes && $ano) {
            $query->whereYear('lancamentos.data_lancamento', $ano)
                  ->whereMonth('lancamentos.data_lancamento', $mes);
        }

        $resultados = $query->groupBy('clientes.tipo')->get();

        $receitaPf = 0;
        $receitaPj = 0;

        foreach ($resultados as $resultado) {
            if ($resultado->tipo == 'PF') {
                $receitaPf = $resultado->total;
            } elseif ($resultado->tipo == 'PJ') {
                $receitaPj = $resultado->total;
            }
        }

        return [
            'pf_percentual' => ($receitaPf / $receitaTotal) * 100,
            'pj_percentual' => ($receitaPj / $receitaTotal) * 100,
            'pf_valor' => $receitaPf,
            'pj_valor' => $receitaPj
        ];
    }

    /**
     * Crescimento MoM (Month over Month)
     */
    public function getCrescimentoMoM(): float
    {
        $receitaMesAtual = $this->getReceitaMesAtual();
        $receitaMesAnterior = $this->getReceitaMesAnterior();

        if ($receitaMesAnterior == 0) {
            return $receitaMesAtual > 0 ? 100 : 0;
        }

        return (($receitaMesAtual - $receitaMesAnterior) / $receitaMesAnterior) * 100;
    }

    /**
     * Taxa de retenção (clientes com receita mês atual vs mês anterior)
     */
    public function getTaxaRetencao(): float
    {
        $mesAtual = now()->month;
        $anoAtual = now()->year;
        $mesAnterior = now()->subMonth()->month;
        $anoAnterior = now()->subMonth()->year;

        $clientesMesAtual = $this->getClientesAtivos($mesAtual, $anoAtual);
        $clientesMesAnterior = $this->getClientesAtivos($mesAnterior, $anoAnterior);

        if ($clientesMesAnterior == 0) {
            return 0;
        }

        return ($clientesMesAtual / $clientesMesAnterior) * 100;
    }

    /**
     * Total de leads
     */
    public function getTotalLeads(): int
    {
        return Lead::count();
    }

    /**
     * Novos leads no mês
     */
    public function getLeadsNovosMes(int $mes = null, int $ano = null): int
    {
        $mes = $mes ?? now()->month;
        $ano = $ano ?? now()->year;

        return Lead::whereYear('data_criacao', $ano)
            ->whereMonth('data_criacao', $mes)
            ->count();
    }

    /**
     * Taxa de conversão de Lead para Cliente (com receita)
     */
    public function getTaxaConversaoLeadCliente(): float
    {
        $totalLeads = $this->getTotalLeads();

        if ($totalLeads == 0) {
            return 0;
        }

        $totalClientes = $this->getTotalClientes();

        return ($totalClientes / $totalLeads) * 100;
    }

    /**
     * Total de oportunidades
     */
    public function getTotalOportunidades(): int
    {
        return Oportunidade::count();
    }

    /**
     * Oportunidades abertas (não ganhas nem perdidas)
     */
    public function getOportunidadesAbertas(): int
    {
        return Oportunidade::whereNotIn('estagio', ['Closed Won', 'Closed Lost', 'Ganha', 'Perdida'])
            ->count();
    }

    /**
     * Valor total de oportunidades
     */
    public function getValorOportunidades(): float
    {
        return Oportunidade::sum('valor');
    }

    /**
     * Valor médio por oportunidade
     */
    public function getValorMedioOportunidade(): float
    {
        $total = $this->getTotalOportunidades();

        if ($total == 0) {
            return 0;
        }

        return $this->getValorOportunidades() / $total;
    }

    /**
     * Pipeline de vendas (oportunidades abertas * probabilidade)
     */
    public function getPipelineVendas(): float
    {
        $oportunidades = Oportunidade::whereNotIn('estagio', ['Closed Won', 'Closed Lost', 'Ganha', 'Perdida'])
            ->get();

        $pipeline = 0;

        foreach ($oportunidades as $oportunidade) {
            $probabilidade = $this->getProbabilidadePorEstagio($oportunidade->estagio);
            $pipeline += $oportunidade->valor * ($probabilidade / 100);
        }

        return $pipeline;
    }

    /**
     * Win Rate (oportunidades ganhas / total de oportunidades fechadas)
     */
    public function getWinRate(): float
    {
        $oportunidadesFechadas = Oportunidade::whereIn('estagio', ['Closed Won', 'Closed Lost', 'Ganha', 'Perdida'])
            ->count();

        if ($oportunidadesFechadas == 0) {
            return 0;
        }

        $oportunidadesGanhas = Oportunidade::whereIn('estagio', ['Closed Won', 'Ganha'])
            ->count();

        return ($oportunidadesGanhas / $oportunidadesFechadas) * 100;
    }

    /**
     * Obter probabilidade baseado no estágio
     */
    private function getProbabilidadePorEstagio(string $estagio): int
    {
        $probabilidades = [
            'Prospecting' => 10,
            'Qualification' => 20,
            'Proposal' => 40,
            'Negotiation' => 60,
            'Closed Won' => 100,
            'Closed Lost' => 0,
            'Ganha' => 100,
            'Perdida' => 0,
        ];

        return $probabilidades[$estagio] ?? 50;
    }

    /**
     * Obter top 10 clientes por receita
     */
    public function getTop10Clientes(): array
    {
        return Cliente::orderBy('valor_carteira', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($cliente) {
                return [
                    'id' => $cliente->id,
                    'nome' => $cliente->nome,
                    'tipo' => $cliente->tipo,
                    'valor_carteira' => $cliente->valor_carteira,
                    'receita_mes_atual' => $cliente->receita_mes_atual,
                ];
            })
            ->toArray();
    }

    /**
     * Obter evolução de receita nos últimos 12 meses
     */
    public function getEvolucaoReceita12Meses(): array
    {
        $dados = [];

        for ($i = 11; $i >= 0; $i--) {
            $data = now()->subMonths($i);
            $mes = $data->month;
            $ano = $data->year;

            $receita = $this->getReceitaTotal($mes, $ano);

            $dados[] = [
                'mes' => $data->format('M/Y'),
                'receita' => $receita
            ];
        }

        return $dados;
    }
}
