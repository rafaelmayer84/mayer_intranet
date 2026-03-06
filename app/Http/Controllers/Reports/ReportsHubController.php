<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Exports\ReportExportService;

class ReportsHubController extends Controller
{
    public function index()
    {
        $domains = [
            [
                'key'   => 'financeiro',
                'title' => 'Financeiro',
                'icon'  => '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
                'color' => 'emerald',
                'reports' => [
                    ['route' => 'relatorios.financeiro.dre', 'label' => 'DRE'],
                    ['route' => 'relatorios.financeiro.receitas', 'label' => 'Extrato de Receitas'],
                    ['route' => 'relatorios.financeiro.despesas', 'label' => 'Extrato de Despesas'],
                    ['route' => 'relatorios.financeiro.contas-receber', 'label' => 'Contas a Receber'],
                    ['route' => 'relatorios.financeiro.fluxo-caixa', 'label' => 'Fluxo de Caixa'],
                    ['route' => 'relatorios.financeiro.receita-advogado', 'label' => 'Receita por Advogado'],
                ],
            ],
            [
                'key'   => 'processos',
                'title' => 'Processos',
                'icon'  => '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/></svg>',
                'color' => 'blue',
                'reports' => [
                    ['route' => 'relatorios.processos.carteira', 'label' => 'Carteira de Processos'],
                    ['route' => 'relatorios.processos.movimentacoes', 'label' => 'Movimentações'],
                    ['route' => 'relatorios.processos.parados', 'label' => 'Processos Parados'],
                    ['route' => 'relatorios.processos.prazos-sla', 'label' => 'Prazos e SLA'],
                    ['route' => 'relatorios.processos.contratos', 'label' => 'Contratos'],
                ],
            ],
            [
                'key'   => 'crm',
                'title' => 'CRM / Clientes',
                'icon'  => '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>',
                'color' => 'violet',
                'reports' => [
                    ['route' => 'relatorios.crm.base-clientes', 'label' => 'Base de Clientes'],
                    ['route' => 'relatorios.crm.pipeline', 'label' => 'Pipeline'],
                    ['route' => 'relatorios.crm.health-segmentacao', 'label' => 'Health Score'],
                    ['route' => 'relatorios.crm.atividades', 'label' => 'Atividades CRM'],
                ],
            ],
            [
                'key'   => 'produtividade',
                'title' => 'Produtividade',
                'icon'  => '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
                'color' => 'amber',
                'reports' => [
                    ['route' => 'relatorios.produtividade.horas', 'label' => 'Horas Trabalhadas'],
                    ['route' => 'relatorios.produtividade.atividades', 'label' => 'Atividades'],
                    ['route' => 'relatorios.produtividade.receita-hora', 'label' => 'R$/Hora'],
                ],
            ],
            [
                'key'   => 'justus',
                'title' => 'Jurisprudência',
                'icon'  => '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>',
                'color' => 'indigo',
                'reports' => [
                    ['route' => 'relatorios.justus.acervo', 'label' => 'Acervo'],
                    ['route' => 'relatorios.justus.captura', 'label' => 'Estatísticas de Captura'],
                    ['route' => 'relatorios.justus.distribuicao', 'label' => 'Distribuição por Área'],
                ],
            ],
            [
                'key'   => 'nexo',
                'title' => 'Atendimento (NEXO)',
                'icon'  => '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>',
                'color' => 'green',
                'reports' => [
                    ['route' => 'relatorios.nexo.conversas', 'label' => 'Conversas'],
                    ['route' => 'relatorios.nexo.tickets', 'label' => 'Tickets'],
                    ['route' => 'relatorios.nexo.qa', 'label' => 'Satisfação (QA)'],
                    ['route' => 'relatorios.nexo.performance-atendentes', 'label' => 'Performance Atendentes'],
                ],
            ],
            [
                'key'   => 'gdp',
                'title' => 'Performance (GDP)',
                'icon'  => '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>',
                'color' => 'rose',
                'reports' => [
                    ['route' => 'relatorios.gdp.performance', 'label' => 'Scorecard'],
                    ['route' => 'relatorios.gdp.penalizacoes', 'label' => 'Penalizações'],
                    ['route' => 'relatorios.gdp.avaliacoes-180', 'label' => 'Avaliações 180°'],
                ],
            ],
            [
                'key'   => 'sistema',
                'title' => 'Saúde do Sistema',
                'icon'  => '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>',
                'color' => 'gray',
                'reports' => [
                    ['route' => 'relatorios.sistema.sync', 'label' => 'Sincronização DataJuri'],
                    ['route' => 'relatorios.sistema.eventos', 'label' => 'Eventos do Sistema'],
                    ['route' => 'relatorios.sistema.auditoria', 'label' => 'Auditoria'],
                ],
            ],
            [
                'key'   => 'sisrh',
                'title' => 'RH (SISRH)',
                'icon'  => '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>',
                'color' => 'cyan',
                'reports' => [
                    ['route' => 'relatorios.sisrh.folha', 'label' => 'Folha de Pagamento'],
                    ['route' => 'relatorios.sisrh.custos', 'label' => 'Custos RH'],
                ],
            ],
            [
                'key'   => 'leads',
                'title' => 'Leads & Marketing',
                'icon'  => '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/></svg>',
                'color' => 'orange',
                'reports' => [
                    ['route' => 'relatorios.leads.funil', 'label' => 'Funil de Leads'],
                    ['route' => 'relatorios.leads.marketing', 'label' => 'Performance Marketing'],
                    ['route' => 'relatorios.leads.bsc-insights', 'label' => 'BSC Insights (IA)'],
                ],
            ],
        ];

        return view('reports.index', compact('domains'));
    }

    public function export(Request $request, string $domain, string $report)
    {
        $type = $request->get('type', 'xlsx');

        // Delegar para o controller do domínio
        $controllerMap = [
            'financeiro'     => ReportFinanceiroController::class,
            'processos'      => ReportProcessosController::class,
            'crm'            => ReportCrmController::class,
            'produtividade'  => ReportProdutividadeController::class,
            'justus'         => ReportJustusController::class,
            'nexo'           => ReportNexoController::class,
            'gdp'            => ReportGdpController::class,
            'sistema'        => ReportSistemaController::class,
            'sisrh'          => ReportSisrhController::class,
            'leads'          => ReportLeadsController::class,
        ];

        if (!isset($controllerMap[$domain])) {
            abort(404, 'Domínio não encontrado');
        }

        $controller = app($controllerMap[$domain]);
        $method = 'export' . str_replace('-', '', ucwords($report, '-'));

        if (!method_exists($controller, $method)) {
            abort(404, 'Relatório não encontrado');
        }

        return $controller->$method($request, $type);
    }
}
