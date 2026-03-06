<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Services\Reports\ReportJustusService;
use App\Exports\ReportExportService;
use Illuminate\Http\Request;

class ReportJustusController extends Controller
{
    protected ReportJustusService $service;

    public function __construct(ReportJustusService $service)
    {
        $this->service = $service;
    }

    // ── REL-J01: Acervo ──────────────────────────────────────
    public function acervo(Request $request)
    {
        $filters = $request->only(['tribunal', 'orgao', 'area', 'classe', 'busca', 'periodo_de', 'periodo_ate', 'sort', 'dir']);
        $perPage = (int) $request->get('per_page', 25);
        $data = $this->service->acervo($filters, $perPage);

        $columns = [
            ['key' => 'tribunal', 'label' => 'Tribunal', 'format' => 'badge', 'sortable' => true,
             'badge_colors' => [
                'TJSC' => 'bg-blue-100 text-blue-700',
                'STJ' => 'bg-emerald-100 text-emerald-700',
                'TRF4' => 'bg-violet-100 text-violet-700',
                'TRT12' => 'bg-amber-100 text-amber-700',
             ]],
            ['key' => 'numero_processo', 'label' => 'Processo', 'format' => 'text', 'sortable' => true],
            ['key' => 'sigla_classe', 'label' => 'Classe', 'format' => 'text', 'sortable' => false],
            ['key' => 'orgao_julgador', 'label' => 'Órgão Julgador', 'format' => 'text', 'sortable' => true, 'limit' => 35],
            ['key' => 'relator', 'label' => 'Relator', 'format' => 'text', 'sortable' => true, 'limit' => 30],
            ['key' => 'data_decisao', 'label' => 'Decisão', 'format' => 'date', 'sortable' => true],
            ['key' => 'area_direito', 'label' => 'Área', 'format' => 'badge', 'sortable' => false,
             'badge_colors' => [
                'civil' => 'bg-blue-50 text-blue-600',
                'comercial' => 'bg-emerald-50 text-emerald-600',
                'penal' => 'bg-red-50 text-red-600',
                'publico' => 'bg-violet-50 text-violet-600',
                'trabalhista' => 'bg-amber-50 text-amber-600',
                '(não classificado)' => 'bg-gray-50 text-gray-500',
             ]],
            ['key' => 'ementa', 'label' => 'Ementa', 'format' => 'text', 'sortable' => false, 'limit' => 120],
        ];

        $filterDefs = [
            ['name' => 'tribunal', 'label' => 'Tribunal', 'type' => 'select', 'options' => ['TJSC' => 'TJSC', 'STJ' => 'STJ', 'TRF4' => 'TRF4', 'TRT12' => 'TRT12']],
            ['name' => 'area', 'label' => 'Área', 'type' => 'select', 'options' => ['civil' => 'Cível', 'comercial' => 'Comercial', 'penal' => 'Penal', 'publico' => 'Público', 'trabalhista' => 'Trabalhista']],
            ['name' => 'busca', 'label' => 'Busca na Ementa', 'type' => 'text', 'placeholder' => 'Termos jurídicos (fulltext)...'],
            ['name' => 'classe', 'label' => 'Classe', 'type' => 'text', 'placeholder' => 'APL, REsp, AI...'],
            ['name' => 'periodo_de', 'label' => 'Decisão De', 'type' => 'month'],
            ['name' => 'periodo_ate', 'label' => 'Decisão Até', 'type' => 'month'],
        ];

        $exportRoute = route('relatorios.export', ['domain' => 'justus', 'report' => 'acervo']) . '?' . http_build_query($request->all());

        return view('reports._report-layout', [
            'reportTitle' => 'Acervo de Jurisprudência',
            'domainLabel' => 'Jurisprudência',
            'columns' => $columns,
            'data' => $data,
            'totals' => [],
            'filters' => $filterDefs,
            'exportRoute' => $exportRoute,
        ]);
    }

    public function exportAcervo(Request $request, string $type)
    {
        $filters = $request->only(['tribunal', 'orgao', 'area', 'classe', 'busca', 'periodo_de', 'periodo_ate', 'sort', 'dir']);
        $data = $this->service->acervo($filters, 5000); // limite seguro
        $columns = [
            ['key' => 'tribunal', 'label' => 'Tribunal', 'format' => 'text'],
            ['key' => 'numero_processo', 'label' => 'Processo', 'format' => 'text'],
            ['key' => 'sigla_classe', 'label' => 'Classe', 'format' => 'text'],
            ['key' => 'orgao_julgador', 'label' => 'Órgão Julgador', 'format' => 'text'],
            ['key' => 'relator', 'label' => 'Relator', 'format' => 'text'],
            ['key' => 'data_decisao', 'label' => 'Decisão', 'format' => 'date'],
            ['key' => 'area_direito', 'label' => 'Área', 'format' => 'text'],
        ];
        return ReportExportService::export($type, 'Acervo Jurisprudência', $columns, collect($data->items()), [], 'landscape');
    }

    // ── REL-J02: Estatísticas de Captura ─────────────────────
    public function captura(Request $request)
    {
        $data = $this->service->captura();

        $totalGeral = array_sum(array_column($data, 'total'));

        $columns = [
            ['key' => 'tribunal', 'label' => 'Tribunal', 'format' => 'badge', 'sortable' => false,
             'badge_colors' => ['TJSC' => 'bg-blue-100 text-blue-700', 'STJ' => 'bg-emerald-100 text-emerald-700', 'TRT12' => 'bg-amber-100 text-amber-700', 'TRF4/Outros' => 'bg-violet-100 text-violet-700']],
            ['key' => 'total', 'label' => 'Total Registros', 'format' => 'text', 'sortable' => false],
            ['key' => 'novos_mes', 'label' => 'Novos Este Mês', 'format' => 'text', 'sortable' => false],
            ['key' => 'periodo_de', 'label' => 'Período De', 'format' => 'date', 'sortable' => false],
            ['key' => 'periodo_ate', 'label' => 'Período Até', 'format' => 'date', 'sortable' => false],
            ['key' => 'ultima_importacao', 'label' => 'Última Import.', 'format' => 'text', 'sortable' => false],
            ['key' => 'fonte', 'label' => 'Fonte', 'format' => 'text', 'sortable' => false],
        ];

        $totals = [
            'total' => $totalGeral,
            'novos_mes' => array_sum(array_column($data, 'novos_mes')),
        ];

        return view('reports.justus.captura', [
            'reportTitle' => 'Estatísticas de Captura',
            'domainLabel' => 'Jurisprudência',
            'columns' => $columns,
            'data' => collect($data),
            'totals' => $totals,
            'filters' => [],
            'exportRoute' => null,
            'totalGeral' => $totalGeral,
        ]);
    }

    // ── REL-J03: Distribuição por Área ───────────────────────
    public function distribuicao(Request $request)
    {
        $result = $this->service->distribuicao();
        $data = $result['data'];
        $tribunais = $result['tribunais'];

        $columns = [
            ['key' => 'area', 'label' => 'Área do Direito', 'format' => 'text', 'sortable' => false],
        ];
        foreach ($tribunais as $t) {
            $colors = ['TJSC' => 'bg-blue-100 text-blue-700', 'STJ' => 'bg-emerald-100 text-emerald-700', 'TRT12' => 'bg-amber-100 text-amber-700'];
            $columns[] = ['key' => $t, 'label' => $t, 'format' => 'text', 'sortable' => false];
        }
        $columns[] = ['key' => 'total', 'label' => 'Total', 'format' => 'text', 'sortable' => false];

        $totals = ['total' => array_sum(array_column($data, 'total'))];
        foreach ($tribunais as $t) {
            $totals[$t] = array_sum(array_column($data, $t));
        }

        return view('reports._report-layout', [
            'reportTitle' => 'Distribuição por Área e Tribunal',
            'domainLabel' => 'Jurisprudência',
            'columns' => $columns,
            'data' => collect($data),
            'totals' => $totals,
            'filters' => [],
            'exportRoute' => null,
        ]);
    }
}
