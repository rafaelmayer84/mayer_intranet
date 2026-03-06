<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Services\Reports\ReportSistemaService;
use App\Exports\ReportExportService;
use Illuminate\Http\Request;

class ReportSistemaController extends Controller
{
    protected ReportSistemaService $service;

    public function __construct(ReportSistemaService $service)
    {
        $this->service = $service;
    }

    // ── REL-S01: Sincronização DataJuri ───────────────────────
    public function sync(Request $request)
    {
        $filters = $request->only(['modulo', 'status', 'periodo_de', 'periodo_ate', 'sort', 'dir']);
        $perPage = (int) $request->get('per_page', 25);
        $data = $this->service->sync($filters, $perPage);
        $stats = $this->service->syncTotals();

        $columns = [
            ['key' => 'started_at', 'label' => 'Início', 'format' => 'text', 'sortable' => true],
            ['key' => 'tipo', 'label' => 'Módulo', 'format' => 'badge', 'sortable' => true,
             'badge_colors' => [
                'modulo_Pessoa' => 'bg-blue-100 text-blue-700',
                'modulo_Processo' => 'bg-indigo-100 text-indigo-700',
                'modulo_Movimento' => 'bg-emerald-100 text-emerald-700',
                'modulo_ContasReceber' => 'bg-amber-100 text-amber-700',
                'modulo_FasesProcesso' => 'bg-violet-100 text-violet-700',
                'modulo_Contrato' => 'bg-cyan-100 text-cyan-700',
                'modulo_Atividade' => 'bg-rose-100 text-rose-700',
                'modulo_HorasTrabalhadas' => 'bg-orange-100 text-orange-700',
                'modulo_AndamentoFase' => 'bg-teal-100 text-teal-700',
                'stale_Movimento' => 'bg-gray-100 text-gray-600',
                'stale_ContasReceber' => 'bg-gray-100 text-gray-600',
             ]],
            ['key' => 'status', 'label' => 'Status', 'format' => 'badge', 'sortable' => true,
             'badge_colors' => [
                'completed' => 'bg-emerald-100 text-emerald-700',
                'failed' => 'bg-red-200 text-red-800',
                'running' => 'bg-blue-100 text-blue-700',
                'started' => 'bg-yellow-100 text-yellow-700',
             ]],
            ['key' => 'registros_processados', 'label' => 'Processados', 'format' => 'text', 'sortable' => true],
            ['key' => 'registros_criados', 'label' => 'Novos', 'format' => 'text', 'sortable' => false],
            ['key' => 'registros_atualizados', 'label' => 'Atualizados', 'format' => 'text', 'sortable' => false],
            ['key' => 'erros', 'label' => 'Erros', 'format' => 'text', 'sortable' => true],
            ['key' => 'duracao_seg', 'label' => 'Duração (s)', 'format' => 'text', 'sortable' => true],
            ['key' => 'mensagem', 'label' => 'Mensagem', 'format' => 'text', 'sortable' => false, 'limit' => 60],
        ];

        $modulos = \DB::table('sync_runs')->distinct()->pluck('tipo')->sort()->toArray();

        $filterDefs = [
            ['name' => 'modulo', 'label' => 'Módulo', 'type' => 'select', 'options' => array_combine($modulos, $modulos)],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['completed' => 'Concluído', 'failed' => 'Falhou', 'running' => 'Em execução']],
            ['name' => 'periodo_de', 'label' => 'Período De', 'type' => 'month'],
            ['name' => 'periodo_ate', 'label' => 'Período Até', 'type' => 'month'],
        ];

        $exportRoute = route('relatorios.export', ['domain' => 'sistema', 'report' => 'sync']) . '?' . http_build_query($request->all());

        return view('reports.sistema.sync', [
            'reportTitle' => 'Sincronização DataJuri',
            'domainLabel' => 'Saúde do Sistema',
            'columns' => $columns,
            'data' => $data,
            'totals' => [],
            'filters' => $filterDefs,
            'exportRoute' => $exportRoute,
            'stats' => $stats,
        ]);
    }

    public function exportSync(Request $request, string $type)
    {
        $filters = $request->only(['modulo', 'status', 'periodo_de', 'periodo_ate', 'sort', 'dir']);
        $data = $this->service->sync($filters, 999999);
        $columns = [
            ['key' => 'started_at', 'label' => 'Início', 'format' => 'text'],
            ['key' => 'tipo', 'label' => 'Módulo', 'format' => 'text'],
            ['key' => 'status', 'label' => 'Status', 'format' => 'text'],
            ['key' => 'registros_processados', 'label' => 'Processados', 'format' => 'text'],
            ['key' => 'registros_criados', 'label' => 'Novos', 'format' => 'text'],
            ['key' => 'registros_atualizados', 'label' => 'Atualizados', 'format' => 'text'],
            ['key' => 'erros', 'label' => 'Erros', 'format' => 'text'],
            ['key' => 'duracao_seg', 'label' => 'Duração (s)', 'format' => 'text'],
            ['key' => 'mensagem', 'label' => 'Mensagem', 'format' => 'text'],
        ];
        return ReportExportService::export($type, 'Sincronização DataJuri', $columns, collect($data->items()), [], 'landscape');
    }

    // ── REL-S02: Eventos do Sistema ──────────────────────────
    public function eventos(Request $request)
    {
        $filters = $request->only(['category', 'severity', 'busca', 'periodo_de', 'periodo_ate', 'sort', 'dir']);
        $perPage = (int) $request->get('per_page', 25);
        $data = $this->service->eventos($filters, $perPage);
        $stats = $this->service->eventosTotals();

        $columns = [
            ['key' => 'created_at', 'label' => 'Data/Hora', 'format' => 'text', 'sortable' => true],
            ['key' => 'category', 'label' => 'Categoria', 'format' => 'badge', 'sortable' => true,
             'badge_colors' => [
                'sistema' => 'bg-slate-100 text-slate-700',
                'financeiro' => 'bg-emerald-100 text-emerald-700',
                'crm' => 'bg-violet-100 text-violet-700',
                'gdp' => 'bg-rose-100 text-rose-700',
                'nexo' => 'bg-green-100 text-green-700',
                'seguranca' => 'bg-red-100 text-red-700',
             ]],
            ['key' => 'severity', 'label' => 'Severidade', 'format' => 'badge', 'sortable' => true,
             'badge_colors' => [
                'info' => 'bg-blue-100 text-blue-700',
                'warning' => 'bg-yellow-100 text-yellow-700',
                'error' => 'bg-red-100 text-red-700',
                'critical' => 'bg-red-300 text-red-900',
             ]],
            ['key' => 'event_type', 'label' => 'Tipo', 'format' => 'text', 'sortable' => true],
            ['key' => 'title', 'label' => 'Título', 'format' => 'text', 'sortable' => false],
            ['key' => 'description', 'label' => 'Descrição', 'format' => 'text', 'sortable' => false, 'limit' => 80],
            ['key' => 'user_name', 'label' => 'Usuário', 'format' => 'text', 'sortable' => false],
        ];

        $categories = \DB::table('system_events')->distinct()->pluck('category')->filter()->sort()->toArray();

        $filterDefs = [
            ['name' => 'category', 'label' => 'Categoria', 'type' => 'select', 'options' => array_combine($categories, $categories)],
            ['name' => 'severity', 'label' => 'Severidade', 'type' => 'select', 'options' => ['info' => 'Info', 'warning' => 'Warning', 'error' => 'Error', 'critical' => 'Critical']],
            ['name' => 'busca', 'label' => 'Busca', 'type' => 'text', 'placeholder' => 'Buscar no título/descrição...'],
            ['name' => 'periodo_de', 'label' => 'Período De', 'type' => 'month'],
            ['name' => 'periodo_ate', 'label' => 'Período Até', 'type' => 'month'],
        ];

        $exportRoute = route('relatorios.export', ['domain' => 'sistema', 'report' => 'eventos']) . '?' . http_build_query($request->all());

        return view('reports.sistema.eventos', [
            'reportTitle' => 'Eventos do Sistema',
            'domainLabel' => 'Saúde do Sistema',
            'columns' => $columns,
            'data' => $data,
            'totals' => [],
            'filters' => $filterDefs,
            'exportRoute' => $exportRoute,
            'stats' => $stats,
        ]);
    }

    public function exportEventos(Request $request, string $type)
    {
        $filters = $request->only(['category', 'severity', 'busca', 'periodo_de', 'periodo_ate', 'sort', 'dir']);
        $data = $this->service->eventos($filters, 999999);
        $columns = [
            ['key' => 'created_at', 'label' => 'Data/Hora', 'format' => 'text'],
            ['key' => 'category', 'label' => 'Categoria', 'format' => 'text'],
            ['key' => 'severity', 'label' => 'Severidade', 'format' => 'text'],
            ['key' => 'event_type', 'label' => 'Tipo', 'format' => 'text'],
            ['key' => 'title', 'label' => 'Título', 'format' => 'text'],
            ['key' => 'description', 'label' => 'Descrição', 'format' => 'text'],
            ['key' => 'user_name', 'label' => 'Usuário', 'format' => 'text'],
        ];
        return ReportExportService::export($type, 'Eventos do Sistema', $columns, collect($data->items()), [], 'landscape');
    }

    // ── REL-S03: Auditoria ───────────────────────────────────
    public function auditoria(Request $request)
    {
        $filters = $request->only(['usuario', 'action', 'module', 'periodo_de', 'periodo_ate', 'sort', 'dir']);
        $perPage = (int) $request->get('per_page', 25);
        $data = $this->service->auditoria($filters, $perPage);

        $columns = [
            ['key' => 'created_at', 'label' => 'Data/Hora', 'format' => 'text', 'sortable' => true],
            ['key' => 'user_name', 'label' => 'Usuário', 'format' => 'text', 'sortable' => true],
            ['key' => 'user_role', 'label' => 'Perfil', 'format' => 'badge', 'sortable' => true,
             'badge_colors' => [
                'admin' => 'bg-red-100 text-red-700',
                'socio' => 'bg-violet-100 text-violet-700',
                'advogado' => 'bg-blue-100 text-blue-700',
                'coordenador' => 'bg-amber-100 text-amber-700',
             ]],
            ['key' => 'action', 'label' => 'Ação', 'format' => 'badge', 'sortable' => true,
             'badge_colors' => [
                'login' => 'bg-emerald-100 text-emerald-700',
                'logout' => 'bg-gray-100 text-gray-600',
                'access_denied' => 'bg-red-200 text-red-800',
                'create' => 'bg-blue-100 text-blue-700',
                'update' => 'bg-amber-100 text-amber-700',
                'delete' => 'bg-red-100 text-red-700',
             ]],
            ['key' => 'module', 'label' => 'Módulo', 'format' => 'text', 'sortable' => true],
            ['key' => 'description', 'label' => 'Descrição', 'format' => 'text', 'sortable' => false, 'limit' => 60],
            ['key' => 'ip_address', 'label' => 'IP', 'format' => 'text', 'sortable' => false, 'limit' => 25],
            ['key' => 'method', 'label' => 'Método', 'format' => 'badge', 'sortable' => false,
             'badge_colors' => [
                'GET' => 'bg-blue-50 text-blue-600',
                'POST' => 'bg-emerald-50 text-emerald-600',
                'PUT' => 'bg-amber-50 text-amber-600',
                'DELETE' => 'bg-red-50 text-red-600',
                'PATCH' => 'bg-violet-50 text-violet-600',
             ]],
            ['key' => 'route', 'label' => 'Rota', 'format' => 'text', 'sortable' => false, 'limit' => 40],
        ];

        $actions = \DB::table('audit_logs')->distinct()->pluck('action')->filter()->sort()->toArray();
        $modules = \DB::table('audit_logs')->distinct()->pluck('module')->filter()->sort()->toArray();
        $users = \DB::table('audit_logs')->distinct()->pluck('user_name')->filter()->sort()->toArray();

        $filterDefs = [
            ['name' => 'usuario', 'label' => 'Usuário', 'type' => 'select', 'options' => array_combine($users, $users)],
            ['name' => 'action', 'label' => 'Ação', 'type' => 'select', 'options' => array_combine($actions, $actions)],
            ['name' => 'module', 'label' => 'Módulo', 'type' => 'select', 'options' => array_combine($modules, $modules)],
            ['name' => 'periodo_de', 'label' => 'Período De', 'type' => 'month'],
            ['name' => 'periodo_ate', 'label' => 'Período Até', 'type' => 'month'],
        ];

        $exportRoute = route('relatorios.export', ['domain' => 'sistema', 'report' => 'auditoria']) . '?' . http_build_query($request->all());

        return view('reports._report-layout', [
            'reportTitle' => 'Auditoria',
            'domainLabel' => 'Saúde do Sistema',
            'columns' => $columns,
            'data' => $data,
            'totals' => [],
            'filters' => $filterDefs,
            'exportRoute' => $exportRoute,
        ]);
    }

    public function exportAuditoria(Request $request, string $type)
    {
        $filters = $request->only(['usuario', 'action', 'module', 'periodo_de', 'periodo_ate', 'sort', 'dir']);
        $data = $this->service->auditoria($filters, 999999);
        $columns = [
            ['key' => 'created_at', 'label' => 'Data/Hora', 'format' => 'text'],
            ['key' => 'user_name', 'label' => 'Usuário', 'format' => 'text'],
            ['key' => 'user_role', 'label' => 'Perfil', 'format' => 'text'],
            ['key' => 'action', 'label' => 'Ação', 'format' => 'text'],
            ['key' => 'module', 'label' => 'Módulo', 'format' => 'text'],
            ['key' => 'description', 'label' => 'Descrição', 'format' => 'text'],
            ['key' => 'ip_address', 'label' => 'IP', 'format' => 'text'],
            ['key' => 'route', 'label' => 'Rota', 'format' => 'text'],
        ];
        return ReportExportService::export($type, 'Auditoria', $columns, collect($data->items()), [], 'landscape');
    }
}
