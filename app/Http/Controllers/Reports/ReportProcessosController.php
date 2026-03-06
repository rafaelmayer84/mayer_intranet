<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Services\Reports\ReportProcessosService;
use App\Exports\ReportExportService;
use Illuminate\Http\Request;

class ReportProcessosController extends Controller
{
    protected ReportProcessosService $service;

    public function __construct(ReportProcessosService $service)
    {
        $this->service = $service;
    }

    private function advogadoOptions(): array
    {
        $list = \DB::table('processos')
            ->whereNotNull('advogado_responsavel')->where('advogado_responsavel', '!=', '')
            ->distinct()->pluck('advogado_responsavel')->sort()->values()->toArray();
        return array_combine($list, $list);
    }

    // ── REL-P01: Carteira ────────────────────────────────────
    public function carteira(Request $request)
    {
        $filters = $request->only(['status', 'natureza', 'advogado', 'cliente', 'sort', 'dir']);
        $perPage = (int) $request->get('per_page', 25);
        $data = $this->service->carteira($filters, $perPage);

        $columns = [
            ['key' => 'pasta', 'label' => 'Processo', 'format' => 'text', 'sortable' => true],
            ['key' => 'cliente_nome', 'label' => 'Cliente', 'format' => 'text', 'sortable' => true],
            ['key' => 'natureza', 'label' => 'Natureza', 'format' => 'text', 'sortable' => false, 'limit' => 30],
            ['key' => 'tipo_acao', 'label' => 'Tipo Ação', 'format' => 'text', 'sortable' => false, 'limit' => 25],
            ['key' => 'advogado_responsavel', 'label' => 'Advogado', 'format' => 'text', 'sortable' => true],
            ['key' => 'posicao_cliente', 'label' => 'Posição', 'format' => 'badge', 'sortable' => false,
             'badge_colors' => ['Autor' => 'bg-blue-100 text-blue-700', 'Réu' => 'bg-orange-100 text-orange-700', 'Reclamante' => 'bg-blue-100 text-blue-700', 'Reclamado' => 'bg-orange-100 text-orange-700']],
            ['key' => 'status', 'label' => 'Status', 'format' => 'badge', 'sortable' => true,
             'badge_colors' => ['Ativo' => 'bg-emerald-100 text-emerald-700', 'Encerrado' => 'bg-gray-100 text-gray-600', 'Suspenso' => 'bg-yellow-100 text-yellow-700']],
            ['key' => 'data_abertura', 'label' => 'Abertura', 'format' => 'date', 'sortable' => true],
            ['key' => 'valor_causa', 'label' => 'Valor Causa', 'format' => 'currency', 'sortable' => true],
            ['key' => 'data_ultimo_andamento', 'label' => 'Últ. Andamento', 'format' => 'date', 'sortable' => false],
            ['key' => 'dias_parado', 'label' => 'Dias Parado', 'format' => 'text', 'sortable' => true],
        ];

        $statuses = \DB::table('processos')->distinct()->pluck('status')->filter()->sort()->toArray();

        $filterDefs = [
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => array_combine($statuses, $statuses)],
            ['name' => 'advogado', 'label' => 'Advogado', 'type' => 'select', 'options' => $this->advogadoOptions()],
            ['name' => 'cliente', 'label' => 'Cliente', 'type' => 'text', 'placeholder' => 'Nome...'],
            ['name' => 'natureza', 'label' => 'Natureza', 'type' => 'text', 'placeholder' => 'Cível, Trabalhista...'],
        ];

        $exportRoute = route('relatorios.export', ['domain' => 'processos', 'report' => 'carteira']) . '?' . http_build_query($request->all());

        return view('reports._report-layout', [
            'reportTitle' => 'Carteira de Processos',
            'domainLabel' => 'Processos',
            'columns' => $columns, 'data' => $data, 'totals' => [],
            'filters' => $filterDefs, 'exportRoute' => $exportRoute,
        ]);
    }

    public function exportCarteira(Request $request, string $type)
    {
        $filters = $request->only(['status', 'natureza', 'advogado', 'cliente', 'sort', 'dir']);
        $data = $this->service->carteira($filters, 999999);
        $columns = [
            ['key' => 'pasta', 'label' => 'Processo', 'format' => 'text'],
            ['key' => 'cliente_nome', 'label' => 'Cliente', 'format' => 'text'],
            ['key' => 'natureza', 'label' => 'Natureza', 'format' => 'text'],
            ['key' => 'advogado_responsavel', 'label' => 'Advogado', 'format' => 'text'],
            ['key' => 'status', 'label' => 'Status', 'format' => 'text'],
            ['key' => 'data_abertura', 'label' => 'Abertura', 'format' => 'date'],
            ['key' => 'valor_causa', 'label' => 'Valor Causa', 'format' => 'currency'],
            ['key' => 'dias_parado', 'label' => 'Dias Parado', 'format' => 'text'],
        ];
        return ReportExportService::export($type, 'Carteira de Processos', $columns, collect($data->items()), [], 'landscape');
    }

    // ── REL-P02: Movimentações ───────────────────────────────
    public function movimentacoes(Request $request)
    {
        $filters = $request->only(['periodo_de', 'periodo_ate', 'advogado', 'busca', 'sort', 'dir']);
        $perPage = (int) $request->get('per_page', 25);
        $data = $this->service->movimentacoes($filters, $perPage);

        $columns = [
            ['key' => 'data_andamento', 'label' => 'Data', 'format' => 'date', 'sortable' => true],
            ['key' => 'processo_pasta', 'label' => 'Processo', 'format' => 'text', 'sortable' => false],
            ['key' => 'advogado', 'label' => 'Advogado', 'format' => 'text', 'sortable' => false],
            ['key' => 'tipo', 'label' => 'Tipo', 'format' => 'text', 'sortable' => false],
            ['key' => 'descricao', 'label' => 'Descrição', 'format' => 'text', 'sortable' => false, 'limit' => 100],
        ];

        $filterDefs = [
            ['name' => 'periodo_de', 'label' => 'Período De', 'type' => 'month'],
            ['name' => 'periodo_ate', 'label' => 'Período Até', 'type' => 'month'],
            ['name' => 'advogado', 'label' => 'Advogado', 'type' => 'select', 'options' => $this->advogadoOptions()],
            ['name' => 'busca', 'label' => 'Busca', 'type' => 'text', 'placeholder' => 'Texto no andamento...'],
        ];

        $exportRoute = route('relatorios.export', ['domain' => 'processos', 'report' => 'movimentacoes']) . '?' . http_build_query($request->all());

        return view('reports._report-layout', [
            'reportTitle' => 'Movimentações Processuais',
            'domainLabel' => 'Processos',
            'columns' => $columns, 'data' => $data, 'totals' => [],
            'filters' => $filterDefs, 'exportRoute' => $exportRoute,
        ]);
    }

    public function exportMovimentacoes(Request $request, string $type)
    {
        $filters = $request->only(['periodo_de', 'periodo_ate', 'advogado', 'busca', 'sort', 'dir']);
        $data = $this->service->movimentacoes($filters, 999999);
        $columns = [
            ['key' => 'data_andamento', 'label' => 'Data', 'format' => 'date'],
            ['key' => 'processo_pasta', 'label' => 'Processo', 'format' => 'text'],
            ['key' => 'advogado', 'label' => 'Advogado', 'format' => 'text'],
            ['key' => 'descricao', 'label' => 'Descrição', 'format' => 'text'],
        ];
        return ReportExportService::export($type, 'Movimentações Processuais', $columns, collect($data->items()), [], 'landscape');
    }

    // ── REL-P03: Parados ─────────────────────────────────────
    public function parados(Request $request)
    {
        $filters = $request->only(['dias_minimo', 'advogado', 'natureza', 'sort', 'dir']);
        $perPage = (int) $request->get('per_page', 25);
        $data = $this->service->parados($filters, $perPage);

        $columns = [
            ['key' => 'pasta', 'label' => 'Processo', 'format' => 'text', 'sortable' => false],
            ['key' => 'cliente_nome', 'label' => 'Cliente', 'format' => 'text', 'sortable' => false],
            ['key' => 'advogado_responsavel', 'label' => 'Advogado', 'format' => 'text', 'sortable' => false],
            ['key' => 'natureza', 'label' => 'Natureza', 'format' => 'text', 'sortable' => false, 'limit' => 25],
            ['key' => 'data_ultimo_andamento', 'label' => 'Últ. Andamento', 'format' => 'date', 'sortable' => false],
            ['key' => 'dias_parado', 'label' => 'Dias Parado', 'format' => 'text', 'sortable' => false],
            ['key' => 'nivel', 'label' => 'Nível', 'format' => 'badge', 'sortable' => false,
             'badge_colors' => ['CRITICO' => 'bg-red-200 text-red-800', 'ALERTA' => 'bg-orange-100 text-orange-700', 'ATENCAO' => 'bg-yellow-100 text-yellow-700']],
        ];

        $filterDefs = [
            ['name' => 'dias_minimo', 'label' => 'Dias Mínimo', 'type' => 'select', 'options' => ['30' => '30 dias', '60' => '60 dias', '90' => '90 dias', '180' => '180 dias']],
            ['name' => 'advogado', 'label' => 'Advogado', 'type' => 'select', 'options' => $this->advogadoOptions()],
            ['name' => 'natureza', 'label' => 'Natureza', 'type' => 'text', 'placeholder' => 'Cível, Trabalhista...'],
        ];

        $exportRoute = route('relatorios.export', ['domain' => 'processos', 'report' => 'parados']) . '?' . http_build_query($request->all());

        return view('reports._report-layout', [
            'reportTitle' => 'Processos Parados',
            'domainLabel' => 'Processos',
            'columns' => $columns, 'data' => $data, 'totals' => [],
            'filters' => $filterDefs, 'exportRoute' => $exportRoute,
        ]);
    }

    public function exportParados(Request $request, string $type)
    {
        $filters = $request->only(['dias_minimo', 'advogado', 'natureza', 'sort', 'dir']);
        $data = $this->service->parados($filters, 999999);
        $columns = [
            ['key' => 'pasta', 'label' => 'Processo', 'format' => 'text'],
            ['key' => 'cliente_nome', 'label' => 'Cliente', 'format' => 'text'],
            ['key' => 'advogado_responsavel', 'label' => 'Advogado', 'format' => 'text'],
            ['key' => 'natureza', 'label' => 'Natureza', 'format' => 'text'],
            ['key' => 'data_ultimo_andamento', 'label' => 'Últ. Andamento', 'format' => 'date'],
            ['key' => 'dias_parado', 'label' => 'Dias Parado', 'format' => 'text'],
            ['key' => 'nivel', 'label' => 'Nível', 'format' => 'text'],
        ];
        return ReportExportService::export($type, 'Processos Parados', $columns, collect($data->items()), [], 'landscape');
    }

    // ── REL-P04: Prazos e SLA ────────────────────────────────
    public function prazosSla(Request $request)
    {
        $filters = $request->only(['status_sla', 'advogado', 'instancia', 'sort', 'dir']);
        $perPage = (int) $request->get('per_page', 25);
        $data = $this->service->prazosSla($filters, $perPage);

        $columns = [
            ['key' => 'processo_pasta', 'label' => 'Processo', 'format' => 'text', 'sortable' => false],
            ['key' => 'cliente_nome', 'label' => 'Cliente', 'format' => 'text', 'sortable' => false],
            ['key' => 'tipo_fase', 'label' => 'Fase', 'format' => 'text', 'sortable' => false],
            ['key' => 'instancia', 'label' => 'Instância', 'format' => 'text', 'sortable' => false],
            ['key' => 'data_fase', 'label' => 'Início Fase', 'format' => 'date', 'sortable' => false],
            ['key' => 'data_ultimo_andamento', 'label' => 'Últ. Andamento', 'format' => 'date', 'sortable' => false],
            ['key' => 'dias_fase_ativa', 'label' => 'Dias na Fase', 'format' => 'text', 'sortable' => true],
            ['key' => 'proprietario_nome', 'label' => 'Advogado', 'format' => 'text', 'sortable' => false],
            ['key' => 'status_sla', 'label' => 'SLA', 'format' => 'badge', 'sortable' => false,
             'badge_colors' => ['CRITICO' => 'bg-red-200 text-red-800', 'ALERTA' => 'bg-orange-100 text-orange-700', 'OK' => 'bg-emerald-100 text-emerald-700', 'ENCERRADA' => 'bg-gray-100 text-gray-500']],
        ];

        $filterDefs = [
            ['name' => 'status_sla', 'label' => 'Status SLA', 'type' => 'select', 'options' => ['critico' => 'Crítico (>90d)', 'alerta' => 'Alerta (61-90d)', 'ok' => 'OK (≤60d)']],
            ['name' => 'advogado', 'label' => 'Advogado', 'type' => 'select', 'options' => $this->advogadoOptions()],
            ['name' => 'instancia', 'label' => 'Instância', 'type' => 'select', 'options' => ['Primeira Instância' => '1ª Instância', 'Segunda Instância' => '2ª Instância']],
        ];

        $exportRoute = route('relatorios.export', ['domain' => 'processos', 'report' => 'prazos-sla']) . '?' . http_build_query($request->all());

        return view('reports._report-layout', [
            'reportTitle' => 'Prazos e SLA',
            'domainLabel' => 'Processos',
            'columns' => $columns, 'data' => $data, 'totals' => [],
            'filters' => $filterDefs, 'exportRoute' => $exportRoute,
        ]);
    }

    public function exportPrazossla(Request $request, string $type)
    {
        $filters = $request->only(['status_sla', 'advogado', 'instancia', 'sort', 'dir']);
        $data = $this->service->prazosSla($filters, 999999);
        $columns = [
            ['key' => 'processo_pasta', 'label' => 'Processo', 'format' => 'text'],
            ['key' => 'cliente_nome', 'label' => 'Cliente', 'format' => 'text'],
            ['key' => 'tipo_fase', 'label' => 'Fase', 'format' => 'text'],
            ['key' => 'instancia', 'label' => 'Instância', 'format' => 'text'],
            ['key' => 'dias_fase_ativa', 'label' => 'Dias na Fase', 'format' => 'text'],
            ['key' => 'proprietario_nome', 'label' => 'Advogado', 'format' => 'text'],
            ['key' => 'status_sla', 'label' => 'SLA', 'format' => 'text'],
        ];
        return ReportExportService::export($type, 'Prazos e SLA', $columns, collect($data->items()), [], 'landscape');
    }

    // ── REL-P05: Contratos ───────────────────────────────────
    public function contratos(Request $request)
    {
        $filters = $request->only(['cliente', 'advogado', 'periodo_de', 'periodo_ate', 'sort', 'dir']);
        $perPage = (int) $request->get('per_page', 25);
        $data = $this->service->contratos($filters, $perPage);

        $columns = [
            ['key' => 'numero', 'label' => 'Nº Contrato', 'format' => 'text', 'sortable' => true],
            ['key' => 'contratante_nome', 'label' => 'Contratante', 'format' => 'text', 'sortable' => true],
            ['key' => 'valor', 'label' => 'Valor (R$)', 'format' => 'currency', 'sortable' => true],
            ['key' => 'data_assinatura', 'label' => 'Assinatura', 'format' => 'date', 'sortable' => true],
            ['key' => 'proprietario_nome', 'label' => 'Responsável', 'format' => 'text', 'sortable' => true],
        ];

        $filterDefs = [
            ['name' => 'cliente', 'label' => 'Contratante', 'type' => 'text', 'placeholder' => 'Nome...'],
            ['name' => 'advogado', 'label' => 'Responsável', 'type' => 'select', 'options' => $this->advogadoOptions()],
            ['name' => 'periodo_de', 'label' => 'Assinatura De', 'type' => 'month'],
            ['name' => 'periodo_ate', 'label' => 'Assinatura Até', 'type' => 'month'],
        ];

        $exportRoute = route('relatorios.export', ['domain' => 'processos', 'report' => 'contratos']) . '?' . http_build_query($request->all());

        return view('reports._report-layout', [
            'reportTitle' => 'Contratos',
            'domainLabel' => 'Processos',
            'columns' => $columns, 'data' => $data, 'totals' => [],
            'filters' => $filterDefs, 'exportRoute' => $exportRoute,
        ]);
    }

    public function exportContratos(Request $request, string $type)
    {
        $filters = $request->only(['cliente', 'advogado', 'periodo_de', 'periodo_ate', 'sort', 'dir']);
        $data = $this->service->contratos($filters, 999999);
        $columns = [
            ['key' => 'numero', 'label' => 'Nº Contrato', 'format' => 'text'],
            ['key' => 'contratante_nome', 'label' => 'Contratante', 'format' => 'text'],
            ['key' => 'valor', 'label' => 'Valor (R$)', 'format' => 'currency'],
            ['key' => 'data_assinatura', 'label' => 'Assinatura', 'format' => 'date'],
            ['key' => 'proprietario_nome', 'label' => 'Responsável', 'format' => 'text'],
        ];
        return ReportExportService::export($type, 'Contratos', $columns, collect($data->items()), []);
    }
}
