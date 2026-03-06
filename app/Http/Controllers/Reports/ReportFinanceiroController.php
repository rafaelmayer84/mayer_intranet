<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Services\Reports\ReportFinanceiroService;
use App\Exports\ReportExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ReportFinanceiroController extends Controller
{
    protected ReportFinanceiroService $service;

    public function __construct(ReportFinanceiroService $service)
    {
        $this->service = $service;
    }

    // ── REL-F01: DRE ─────────────────────────────────────────
    public function dre(Request $request)
    {
        $ano = (int) $request->get('ano', now()->year);
        $mesIni = (int) $request->get('mes_ini', 1);
        $mesFim = (int) $request->get('mes_fim', now()->month);

        $data = $this->service->dre($ano, $mesIni, $mesFim);

        $meses = ['', 'Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];

        // Construir colunas dinâmicas
        $columns = [
            ['key' => 'codigo', 'label' => 'Código', 'format' => 'text', 'sortable' => true],
            ['key' => 'rubrica', 'label' => 'Rubrica', 'format' => 'text', 'sortable' => true],
            ['key' => 'classificacao', 'label' => 'Classificação', 'format' => 'badge', 'sortable' => true,
             'badge_colors' => [
                'RECEITA_PF' => 'bg-emerald-100 text-emerald-700',
                'RECEITA_PJ' => 'bg-blue-100 text-blue-700',
                'DESPESA' => 'bg-red-100 text-red-700',
                'RECEITA_FINANCEIRA' => 'bg-cyan-100 text-cyan-700',
                'OUTRAS_RECEITAS' => 'bg-amber-100 text-amber-700',
             ]],
        ];

        for ($m = $mesIni; $m <= $mesFim; $m++) {
            $columns[] = ['key' => 'mes_' . $m, 'label' => $meses[$m], 'format' => 'currency', 'sortable' => false];
        }
        $columns[] = ['key' => 'total', 'label' => 'Total', 'format' => 'currency', 'sortable' => true];

        // Totais
        $totals = ['total' => array_sum(array_column($data, 'total'))];
        for ($m = $mesIni; $m <= $mesFim; $m++) {
            $totals['mes_' . $m] = array_sum(array_column($data, 'mes_' . $m));
        }

        $filters = [
            ['name' => 'ano', 'label' => 'Ano', 'type' => 'select', 'options' => array_combine(range(2024, now()->year), range(2024, now()->year))],
            ['name' => 'mes_ini', 'label' => 'Mês Inicial', 'type' => 'select', 'options' => array_combine(range(1, 12), ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'])],
            ['name' => 'mes_fim', 'label' => 'Mês Final', 'type' => 'select', 'options' => array_combine(range(1, 12), ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'])],
        ];

        $exportRoute = route('relatorios.export', ['domain' => 'financeiro', 'report' => 'dre']) . '?' . http_build_query($request->all());

        return view('reports._report-layout', [
            'reportTitle' => 'DRE — Demonstrativo de Resultado',
            'domainLabel' => 'Financeiro',
            'columns'     => $columns,
            'data'        => collect($data),
            'totals'      => $totals,
            'filters'     => $filters,
            'exportRoute' => $exportRoute,
        ]);
    }

    public function exportDre(Request $request, string $type)
    {
        $ano = (int) $request->get('ano', now()->year);
        $mesIni = (int) $request->get('mes_ini', 1);
        $mesFim = (int) $request->get('mes_fim', now()->month);

        $data = $this->service->dre($ano, $mesIni, $mesFim);
        $meses = ['', 'Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];

        $columns = [
            ['key' => 'codigo', 'label' => 'Código', 'format' => 'text'],
            ['key' => 'rubrica', 'label' => 'Rubrica', 'format' => 'text'],
            ['key' => 'classificacao', 'label' => 'Classificação', 'format' => 'text'],
        ];
        for ($m = $mesIni; $m <= $mesFim; $m++) {
            $columns[] = ['key' => 'mes_' . $m, 'label' => $meses[$m], 'format' => 'currency'];
        }
        $columns[] = ['key' => 'total', 'label' => 'Total', 'format' => 'currency'];

        $totals = ['total' => array_sum(array_column($data, 'total'))];

        return ReportExportService::export($type, "DRE {$ano}", $columns, collect($data), $totals, 'landscape');
    }

    // ── REL-F02: Receitas ────────────────────────────────────
    public function receitas(Request $request)
    {
        $filters = $request->only(['periodo_de', 'periodo_ate', 'tipo', 'cliente', 'advogado', 'sort', 'dir']);
        $perPage = (int) $request->get('per_page', 25);
        $data = $this->service->receitas($filters, $perPage);
        $totals = $this->service->receitasTotals($filters);

        $columns = [
            ['key' => 'data', 'label' => 'Data', 'format' => 'date', 'sortable' => true],
            ['key' => 'cliente', 'label' => 'Cliente', 'format' => 'text', 'sortable' => true],
            ['key' => 'processo_pasta', 'label' => 'Processo', 'format' => 'text', 'sortable' => false],
            ['key' => 'descricao', 'label' => 'Descrição', 'format' => 'text', 'sortable' => false, 'limit' => 60],
            ['key' => 'classificacao', 'label' => 'Tipo', 'format' => 'badge', 'sortable' => true,
             'badge_colors' => ['RECEITA_PF' => 'bg-emerald-100 text-emerald-700', 'RECEITA_PJ' => 'bg-blue-100 text-blue-700']],
            ['key' => 'proprietario_nome', 'label' => 'Advogado', 'format' => 'text', 'sortable' => true],
            ['key' => 'valor', 'label' => 'Valor (R$)', 'format' => 'currency', 'sortable' => true],
        ];

        $advogados = \DB::table('movimentos')
            ->whereIn('classificacao', ['RECEITA_PF', 'RECEITA_PJ'])
            ->whereNotNull('proprietario_nome')->where('proprietario_nome', '!=', '')
            ->distinct()->pluck('proprietario_nome')->sort()->toArray();

        $filterDefs = [
            ['name' => 'periodo_de', 'label' => 'Período De', 'type' => 'month'],
            ['name' => 'periodo_ate', 'label' => 'Período Até', 'type' => 'month'],
            ['name' => 'tipo', 'label' => 'Tipo', 'type' => 'select', 'options' => ['RECEITA_PF' => 'Pessoa Física', 'RECEITA_PJ' => 'Pessoa Jurídica']],
            ['name' => 'cliente', 'label' => 'Cliente', 'type' => 'text', 'placeholder' => 'Nome do cliente...'],
            ['name' => 'advogado', 'label' => 'Advogado', 'type' => 'select', 'options' => array_combine($advogados, $advogados)],
        ];

        $exportRoute = route('relatorios.export', ['domain' => 'financeiro', 'report' => 'receitas']) . '?' . http_build_query($request->all());

        return view('reports._report-layout', [
            'reportTitle' => 'Extrato de Receitas',
            'domainLabel' => 'Financeiro',
            'columns'     => $columns,
            'data'        => $data,
            'totals'      => $totals,
            'filters'     => $filterDefs,
            'exportRoute' => $exportRoute,
        ]);
    }

    public function exportReceitas(Request $request, string $type)
    {
        $filters = $request->only(['periodo_de', 'periodo_ate', 'tipo', 'cliente', 'advogado', 'sort', 'dir']);
        $data = $this->service->receitas($filters, 999999);
        $totals = $this->service->receitasTotals($filters);

        $columns = [
            ['key' => 'data', 'label' => 'Data', 'format' => 'date'],
            ['key' => 'cliente', 'label' => 'Cliente', 'format' => 'text'],
            ['key' => 'processo_pasta', 'label' => 'Processo', 'format' => 'text'],
            ['key' => 'descricao', 'label' => 'Descrição', 'format' => 'text'],
            ['key' => 'classificacao', 'label' => 'Tipo', 'format' => 'text'],
            ['key' => 'proprietario_nome', 'label' => 'Advogado', 'format' => 'text'],
            ['key' => 'valor', 'label' => 'Valor (R$)', 'format' => 'currency'],
        ];

        return ReportExportService::export($type, 'Extrato de Receitas', $columns, collect($data->items()), $totals);
    }

    // ── REL-F03: Despesas ────────────────────────────────────
    public function despesas(Request $request)
    {
        $filters = $request->only(['periodo_de', 'periodo_ate', 'busca', 'categoria', 'sort', 'dir']);
        $perPage = (int) $request->get('per_page', 25);
        $data = $this->service->despesas($filters, $perPage);
        $totals = $this->service->despesasTotals($filters);

        $columns = [
            ['key' => 'data', 'label' => 'Data', 'format' => 'date', 'sortable' => true],
            ['key' => 'descricao', 'label' => 'Descrição', 'format' => 'text', 'sortable' => true, 'limit' => 60],
            ['key' => 'codigo_plano', 'label' => 'Código Plano', 'format' => 'text', 'sortable' => true],
            ['key' => 'pessoa', 'label' => 'Pessoa', 'format' => 'text', 'sortable' => true],
            ['key' => 'proprietario_nome', 'label' => 'Responsável', 'format' => 'text', 'sortable' => true],
            ['key' => 'valor', 'label' => 'Valor (R$)', 'format' => 'currency', 'sortable' => true],
        ];

        $filterDefs = [
            ['name' => 'periodo_de', 'label' => 'Período De', 'type' => 'month'],
            ['name' => 'periodo_ate', 'label' => 'Período Até', 'type' => 'month'],
            ['name' => 'busca', 'label' => 'Busca Descrição', 'type' => 'text', 'placeholder' => 'Buscar na descrição...'],
            ['name' => 'categoria', 'label' => 'Código Plano', 'type' => 'text', 'placeholder' => 'Ex: 3.02.01'],
        ];

        $exportRoute = route('relatorios.export', ['domain' => 'financeiro', 'report' => 'despesas']) . '?' . http_build_query($request->all());

        return view('reports._report-layout', [
            'reportTitle' => 'Extrato de Despesas',
            'domainLabel' => 'Financeiro',
            'columns'     => $columns,
            'data'        => $data,
            'totals'      => $totals,
            'filters'     => $filterDefs,
            'exportRoute' => $exportRoute,
        ]);
    }

    public function exportDespesas(Request $request, string $type)
    {
        $filters = $request->only(['periodo_de', 'periodo_ate', 'busca', 'categoria', 'sort', 'dir']);
        $data = $this->service->despesas($filters, 999999);
        $totals = $this->service->despesasTotals($filters);

        $columns = [
            ['key' => 'data', 'label' => 'Data', 'format' => 'date'],
            ['key' => 'descricao', 'label' => 'Descrição', 'format' => 'text'],
            ['key' => 'codigo_plano', 'label' => 'Código Plano', 'format' => 'text'],
            ['key' => 'pessoa', 'label' => 'Pessoa', 'format' => 'text'],
            ['key' => 'valor', 'label' => 'Valor (R$)', 'format' => 'currency'],
        ];

        return ReportExportService::export($type, 'Extrato de Despesas', $columns, collect($data->items()), $totals);
    }

    // ── REL-F04: Contas a Receber ────────────────────────────
    public function contasReceber(Request $request)
    {
        $filters = $request->only(['status_filtro', 'cliente', 'venc_de', 'venc_ate', 'sort', 'dir']);
        $perPage = (int) $request->get('per_page', 25);
        $data = $this->service->contasReceber($filters, $perPage);
        $totals = $this->service->contasReceberTotals($filters);

        $columns = [
            ['key' => 'cliente', 'label' => 'Cliente', 'format' => 'text', 'sortable' => true],
            ['key' => 'descricao', 'label' => 'Descrição', 'format' => 'text', 'sortable' => false, 'limit' => 50],
            ['key' => 'valor', 'label' => 'Valor (R$)', 'format' => 'currency', 'sortable' => true],
            ['key' => 'data_vencimento', 'label' => 'Vencimento', 'format' => 'date', 'sortable' => true],
            ['key' => 'data_pagamento', 'label' => 'Pagamento', 'format' => 'date', 'sortable' => true],
            ['key' => 'dias_atraso', 'label' => 'Dias Atraso', 'format' => 'text', 'sortable' => true],
            ['key' => 'faixa_aging', 'label' => 'Aging', 'format' => 'badge', 'sortable' => false,
             'badge_colors' => [
                '0-30' => 'bg-yellow-100 text-yellow-700',
                '31-60' => 'bg-orange-100 text-orange-700',
                '61-90' => 'bg-red-100 text-red-700',
                '91-180' => 'bg-red-200 text-red-800',
                '180+' => 'bg-red-300 text-red-900',
                '-' => 'bg-green-100 text-green-700',
             ]],
            ['key' => 'status_calc', 'label' => 'Status', 'format' => 'badge', 'sortable' => false,
             'badge_colors' => [
                'Pago' => 'bg-green-100 text-green-700',
                'Em aberto' => 'bg-blue-100 text-blue-700',
                'Vencido' => 'bg-red-100 text-red-700',
             ]],
        ];

        $filterDefs = [
            ['name' => 'status_filtro', 'label' => 'Status', 'type' => 'select', 'options' => ['vencido' => 'Vencidos', 'aberto' => 'Em Aberto', 'pago' => 'Pagos']],
            ['name' => 'cliente', 'label' => 'Cliente', 'type' => 'text', 'placeholder' => 'Nome...'],
            ['name' => 'venc_de', 'label' => 'Vencimento De', 'type' => 'text', 'placeholder' => 'AAAA-MM-DD'],
            ['name' => 'venc_ate', 'label' => 'Vencimento Até', 'type' => 'text', 'placeholder' => 'AAAA-MM-DD'],
        ];

        $exportRoute = route('relatorios.export', ['domain' => 'financeiro', 'report' => 'contas-receber']) . '?' . http_build_query($request->all());

        return view('reports._report-layout', [
            'reportTitle' => 'Contas a Receber & Inadimplência',
            'domainLabel' => 'Financeiro',
            'columns'     => $columns,
            'data'        => $data,
            'totals'      => $totals,
            'filters'     => $filterDefs,
            'exportRoute' => $exportRoute,
        ]);
    }

    public function exportContasreceber(Request $request, string $type)
    {
        $filters = $request->only(['status_filtro', 'cliente', 'venc_de', 'venc_ate', 'sort', 'dir']);
        $data = $this->service->contasReceber($filters, 999999);
        $totals = $this->service->contasReceberTotals($filters);

        $columns = [
            ['key' => 'cliente', 'label' => 'Cliente', 'format' => 'text'],
            ['key' => 'descricao', 'label' => 'Descrição', 'format' => 'text'],
            ['key' => 'valor', 'label' => 'Valor (R$)', 'format' => 'currency'],
            ['key' => 'data_vencimento', 'label' => 'Vencimento', 'format' => 'date'],
            ['key' => 'data_pagamento', 'label' => 'Pagamento', 'format' => 'date'],
            ['key' => 'dias_atraso', 'label' => 'Dias Atraso', 'format' => 'text'],
            ['key' => 'faixa_aging', 'label' => 'Aging', 'format' => 'text'],
            ['key' => 'status_calc', 'label' => 'Status', 'format' => 'text'],
        ];

        return ReportExportService::export($type, 'Contas a Receber', $columns, collect($data->items()), $totals, 'landscape');
    }

    // ── REL-F05: Fluxo de Caixa ─────────────────────────────
    public function fluxoCaixa(Request $request)
    {
        $ano = (int) $request->get('ano', now()->year);
        $mesIni = (int) $request->get('mes_ini', 1);
        $mesFim = (int) $request->get('mes_fim', now()->month);

        $data = $this->service->fluxoCaixa($ano, $mesIni, $mesFim);

        $columns = [
            ['key' => 'periodo', 'label' => 'Período', 'format' => 'text', 'sortable' => false],
            ['key' => 'entradas', 'label' => 'Entradas (R$)', 'format' => 'currency', 'sortable' => false],
            ['key' => 'saidas', 'label' => 'Saídas (R$)', 'format' => 'currency', 'sortable' => false],
            ['key' => 'saldo', 'label' => 'Saldo (R$)', 'format' => 'currency', 'sortable' => false],
            ['key' => 'acumulado', 'label' => 'Acumulado (R$)', 'format' => 'currency', 'sortable' => false],
        ];

        $totals = [
            'entradas'  => array_sum(array_column($data, 'entradas')),
            'saidas'    => array_sum(array_column($data, 'saidas')),
            'saldo'     => array_sum(array_column($data, 'saldo')),
        ];

        $filters = [
            ['name' => 'ano', 'label' => 'Ano', 'type' => 'select', 'options' => array_combine(range(2024, now()->year), range(2024, now()->year))],
            ['name' => 'mes_ini', 'label' => 'Mês Inicial', 'type' => 'select', 'options' => array_combine(range(1, 12), ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'])],
            ['name' => 'mes_fim', 'label' => 'Mês Final', 'type' => 'select', 'options' => array_combine(range(1, 12), ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'])],
        ];

        $exportRoute = route('relatorios.export', ['domain' => 'financeiro', 'report' => 'fluxo-caixa']) . '?' . http_build_query($request->all());

        return view('reports._report-layout', [
            'reportTitle' => 'Fluxo de Caixa Mensal',
            'domainLabel' => 'Financeiro',
            'columns'     => $columns,
            'data'        => collect($data),
            'totals'      => $totals,
            'filters'     => $filters,
            'exportRoute' => $exportRoute,
        ]);
    }

    public function exportFluxocaixa(Request $request, string $type)
    {
        $ano = (int) $request->get('ano', now()->year);
        $mesIni = (int) $request->get('mes_ini', 1);
        $mesFim = (int) $request->get('mes_fim', now()->month);

        $data = $this->service->fluxoCaixa($ano, $mesIni, $mesFim);

        $columns = [
            ['key' => 'periodo', 'label' => 'Período', 'format' => 'text'],
            ['key' => 'entradas', 'label' => 'Entradas (R$)', 'format' => 'currency'],
            ['key' => 'saidas', 'label' => 'Saídas (R$)', 'format' => 'currency'],
            ['key' => 'saldo', 'label' => 'Saldo (R$)', 'format' => 'currency'],
            ['key' => 'acumulado', 'label' => 'Acumulado (R$)', 'format' => 'currency'],
        ];

        $totals = [
            'entradas' => array_sum(array_column($data, 'entradas')),
            'saidas' => array_sum(array_column($data, 'saidas')),
            'saldo' => array_sum(array_column($data, 'saldo')),
        ];

        return ReportExportService::export($type, "Fluxo de Caixa {$ano}", $columns, collect($data), $totals);
    }

    // ── REL-F06: Receita por Advogado ────────────────────────
    public function receitaAdvogado(Request $request)
    {
        $filters = $request->only(['periodo_de', 'periodo_ate', 'advogado']);
        $data = $this->service->receitaAdvogado($filters);

        $columns = [
            ['key' => 'advogado', 'label' => 'Advogado', 'format' => 'text', 'sortable' => true],
            ['key' => 'receita_pf', 'label' => 'Receita PF (R$)', 'format' => 'currency', 'sortable' => true],
            ['key' => 'receita_pj', 'label' => 'Receita PJ (R$)', 'format' => 'currency', 'sortable' => true],
            ['key' => 'receita_total', 'label' => 'Total (R$)', 'format' => 'currency', 'sortable' => true],
            ['key' => 'num_movimentos', 'label' => 'Movimentos', 'format' => 'text', 'sortable' => true],
            ['key' => 'ticket_medio', 'label' => 'Ticket Médio (R$)', 'format' => 'currency', 'sortable' => true],
        ];

        $totals = [
            'receita_pf' => array_sum(array_column($data, 'receita_pf')),
            'receita_pj' => array_sum(array_column($data, 'receita_pj')),
            'receita_total' => array_sum(array_column($data, 'receita_total')),
            'num_movimentos' => array_sum(array_column($data, 'num_movimentos')),
        ];

        $filterDefs = [
            ['name' => 'periodo_de', 'label' => 'Período De', 'type' => 'month'],
            ['name' => 'periodo_ate', 'label' => 'Período Até', 'type' => 'month'],
            ['name' => 'advogado', 'label' => 'Advogado', 'type' => 'text', 'placeholder' => 'Nome...'],
        ];

        $exportRoute = route('relatorios.export', ['domain' => 'financeiro', 'report' => 'receita-advogado']) . '?' . http_build_query($request->all());

        return view('reports._report-layout', [
            'reportTitle' => 'Receita por Advogado',
            'domainLabel' => 'Financeiro',
            'columns'     => $columns,
            'data'        => collect($data),
            'totals'      => $totals,
            'filters'     => $filterDefs,
            'exportRoute' => $exportRoute,
        ]);
    }

    public function exportReceitaadvogado(Request $request, string $type)
    {
        $filters = $request->only(['periodo_de', 'periodo_ate', 'advogado']);
        $data = $this->service->receitaAdvogado($filters);

        $columns = [
            ['key' => 'advogado', 'label' => 'Advogado', 'format' => 'text'],
            ['key' => 'receita_pf', 'label' => 'Receita PF (R$)', 'format' => 'currency'],
            ['key' => 'receita_pj', 'label' => 'Receita PJ (R$)', 'format' => 'currency'],
            ['key' => 'receita_total', 'label' => 'Total (R$)', 'format' => 'currency'],
            ['key' => 'num_movimentos', 'label' => 'Movimentos', 'format' => 'text'],
            ['key' => 'ticket_medio', 'label' => 'Ticket Médio (R$)', 'format' => 'currency'],
        ];

        $totals = [
            'receita_total' => array_sum(array_column($data, 'receita_total')),
        ];

        return ReportExportService::export($type, 'Receita por Advogado', $columns, collect($data), $totals);
    }
}
