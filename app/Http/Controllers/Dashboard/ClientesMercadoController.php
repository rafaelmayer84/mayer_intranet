<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Services\ClientesMercado\ClientesMercadoService;

class ClientesMercadoController extends Controller
{
    public function index(Request $request, ClientesMercadoService $service)
    {
        $year  = (int)($request->query('ano', date('Y')));
        $month = (int)($request->query('mes', (int)date('m')));
        $inactiveMonths = (int)($request->query('inativos_meses', 12));

        $data = $service->buildDashboard($year, $month, $inactiveMonths);

        return view('dashboard.clientes-mercado.index', [
            'data' => $data,
            'filters' => [
                'ano' => $year,
                'mes' => $month,
                'inativos_meses' => $inactiveMonths,
            ],
        ]);
    }

    public function exportCsv(Request $request, ClientesMercadoService $service): StreamedResponse
    {
        $year  = (int)($request->query('ano', date('Y')));
        $month = (int)($request->query('mes', (int)date('m')));
        $inactiveMonths = (int)($request->query('inativos_meses', 12));

        $data = $service->buildDashboard($year, $month, $inactiveMonths);

        $filename = sprintf("clientes_mercado_%04d-%02d.csv", $year, $month);

        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');

            fputcsv($out, ['KPI', 'Valor']);
            foreach (($data['kpis'] ?? []) as $k) {
                fputcsv($out, [$k['label'], $k['value']]);
            }

            fputcsv($out, []);
            fputcsv($out, ['Processos por Status (Top 50)']);
            fputcsv($out, ['Status', 'Total']);
            foreach (($data['charts']['processos_por_status_full'] ?? []) as $row) {
                fputcsv($out, [$row['status'], $row['total']]);
            }

            fputcsv($out, []);
            fputcsv($out, ['Top Clientes por Processos Ativos']);
            fputcsv($out, ['Cliente', 'Cliente ID', 'Processos Ativos']);
            foreach (($data['tables']['top_clientes'] ?? []) as $row) {
                fputcsv($out, [$row['cliente'], $row['cliente_id'], $row['processos_ativos']]);
            }

            fputcsv($out, []);
            fputcsv($out, ['Clientes para Reativação']);
            fputcsv($out, ['Cliente', 'Cliente ID', 'Última Atividade', 'Meses Sem Atividade']);
            foreach (($data['tables']['reativacao'] ?? []) as $row) {
                fputcsv($out, [$row['cliente'], $row['cliente_id'], $row['ultima_atividade'], $row['meses_sem_atividade']]);
            }

            if (!empty($data['charts']['pipeline_por_estagio'])) {
                fputcsv($out, []);
                fputcsv($out, ['Pipeline (Espo) por Estágio']);
                fputcsv($out, ['Estágio', 'Total (R$)']);
                foreach ($data['charts']['pipeline_por_estagio'] as $row) {
                    fputcsv($out, [$row['estagio'], $row['total']]);
                }
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
