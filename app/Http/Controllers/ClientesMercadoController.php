<?php

namespace App\Http\Controllers;

use App\Services\ClientesMercadoService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClientesMercadoController extends Controller
{
    public function __construct(
        protected ClientesMercadoService $service
    ) {}

    public function index(Request $request)
    {
        $ano = (int) $request->get('ano', now()->year);
        $mes = (int) $request->get('mes', now()->month);

        if ($ano < 2020 || $ano > 2030) { $ano = now()->year; }
        if ($mes < 1 || $mes > 12) { $mes = now()->month; }

        $dashboardData = $this->service->getDashboardData($ano, $mes);
        $totaisAcumulados = $this->service->getTotaisAcumulados();
        $anosDisponiveis = range(2024, now()->year + 1);

        return view('dashboard.clientes-mercado.index', [
            'dashboardData' => $dashboardData,
            'totaisAcumulados' => $totaisAcumulados,
            'anoSelecionado' => $ano,
            'mesSelecionado' => $mes,
            'anosDisponiveis' => $anosDisponiveis,
            'meses' => [1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril', 5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto', 9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro']
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $ano = (int) $request->get('ano', now()->year);
        $mes = (int) $request->get('mes', now()->month);
        
        $data = $this->service->getDashboardData($ano, $mes);
        $totais = $this->service->getTotaisAcumulados();
        
        $filename = "clientes_mercado_{$ano}_{$mes}.csv";
        
        return response()->streamDownload(function() use ($data, $totais) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
            
            fputcsv($handle, ['DASHBOARD CLIENTES & MERCADO'], ';');
            fputcsv($handle, ['Competência: ' . $data['competencia']['label']], ';');
            fputcsv($handle, ['Gerado em: ' . $data['gerado_em']], ';');
            fputcsv($handle, [], ';');
            
            fputcsv($handle, ['=== KPIs PRINCIPAIS ==='], ';');
            fputcsv($handle, ['Indicador', 'Valor'], ';');
            foreach ($data['kpis_principais'] as $kpi) {
                $valor = $kpi['formato'] === 'moeda' ? 'R$ ' . number_format($kpi['valor'], 2, ',', '.') : number_format($kpi['valor'], 0, ',', '.');
                fputcsv($handle, [$kpi['label'], $valor], ';');
            }
            fputcsv($handle, [], ';');
            
            fputcsv($handle, ['=== KPIs SECUNDÁRIOS ==='], ';');
            fputcsv($handle, ['Indicador', 'Valor', 'Detalhe'], ';');
            foreach ($data['kpis_secundarios'] as $kpi) {
                $valor = $kpi['formato'] === 'moeda' ? 'R$ ' . number_format($kpi['valor'], 2, ',', '.') : ($kpi['formato'] === 'percentual' ? number_format($kpi['valor'], 1, ',', '.') . '%' : number_format($kpi['valor'], 0, ',', '.'));
                fputcsv($handle, [$kpi['label'], $valor, $kpi['detalhe'] ?? ''], ';');
            }
            fputcsv($handle, [], ';');
            
            fputcsv($handle, ['=== OPORTUNIDADES POR ESTÁGIO ==='], ';');
            fputcsv($handle, ['Estágio', 'Quantidade', 'Valor'], ';');
            fputcsv($handle, ['Ganhas', $data['oportunidades_por_estagio']['ganhas']['qtd'], 'R$ ' . number_format($data['oportunidades_por_estagio']['ganhas']['valor'], 2, ',', '.')], ';');
            fputcsv($handle, ['Perdidas', $data['oportunidades_por_estagio']['perdidas']['qtd'], 'R$ ' . number_format($data['oportunidades_por_estagio']['perdidas']['valor'], 2, ',', '.')], ';');
            fputcsv($handle, ['Pipeline', $data['oportunidades_por_estagio']['pipeline']['qtd'], 'R$ ' . number_format($data['oportunidades_por_estagio']['pipeline']['valor'], 2, ',', '.')], ';');
            fputcsv($handle, [], ';');
            
            fputcsv($handle, ['=== SÉRIE HISTÓRICA 12 MESES ==='], ';');
            fputcsv($handle, ['Mês', 'Leads Novos', 'Convertidos', 'Ops Ganhas', 'Valor Ganho'], ';');
            foreach ($data['serie_12_meses'] as $m) {
                fputcsv($handle, [$m['label'], $m['leads_novos'], $m['leads_convertidos'], $m['oportunidades_ganhas'], 'R$ ' . number_format($m['valor_ganho'], 2, ',', '.')], ';');
            }
            fputcsv($handle, [], ';');
            
            fputcsv($handle, ['=== TOP 10 CLIENTES ==='], ';');
            fputcsv($handle, ['#', 'Cliente', 'Tipo', 'Processos Ativos'], ';');
            foreach ($data['top_10_clientes'] as $i => $cliente) {
                fputcsv($handle, [$i + 1, $cliente->nome, $cliente->tipo ?? 'PF', $cliente->qtd_processos_ativos], ';');
            }
            fputcsv($handle, [], ';');
            
            fputcsv($handle, ['=== TOTAIS ACUMULADOS ==='], ';');
            fputcsv($handle, ['Total Clientes', number_format($totais['total_clientes'], 0, ',', '.')], ';');
            fputcsv($handle, ['Total Leads', number_format($totais['total_leads'], 0, ',', '.')], ';');
            fputcsv($handle, ['Total Oportunidades', number_format($totais['total_oportunidades'], 0, ',', '.')], ';');
            fputcsv($handle, ['Processos Ativos', number_format($totais['processos_ativos'], 0, ',', '.')], ';');
            
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function exportPdf(Request $request)
    {
        $ano = (int) $request->get('ano', now()->year);
        $mes = (int) $request->get('mes', now()->month);
        
        $data = $this->service->getDashboardData($ano, $mes);
        $totais = $this->service->getTotaisAcumulados();
        
        return view('dashboard.clientes-mercado.pdf', [
            'data' => $data,
            'totais' => $totais,
            'ano' => $ano,
            'mes' => $mes
        ]);
    }
}
