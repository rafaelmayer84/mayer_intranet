<?php

namespace App\Http\Controllers;

use App\Services\Vigilia\VigiliaService;
use App\Services\Vigilia\VigiliaExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class VigiliaController extends Controller
{
    protected VigiliaService $service;
    protected VigiliaExportService $export;

    public function __construct(VigiliaService $service, VigiliaExportService $export)
    {
        $this->middleware(function ($request, $next) {
            if (!auth()->user()->isAdmin()) {
                abort(403, 'Acesso restrito.');
            }
            return $next($request);
        });
        $this->service = $service;
        $this->export = $export;
    }

    // ─── VIEW PRINCIPAL ──────────────────────────────────────────────

    public function index()
    {
        $responsaveis = $this->service->getResponsaveis();
        $tiposAtividade = $this->service->getTiposAtividade();

        return view('vigilia.index', compact('responsaveis', 'tiposAtividade'));
    }

    // ─── API ENDPOINTS (AJAX) ────────────────────────────────────────

    public function apiResumo(Request $request)
    {
        [$inicio, $fim] = $this->parsePeriodo($request);
        $resumo = $this->service->getResumoGeral($inicio, $fim);
        $ranking = $this->service->getPerformancePorResponsavel($inicio, $fim);

        return response()->json([
            'resumo' => $resumo,
            'ranking' => $ranking,
        ]);
    }

    public function apiAlertas(Request $request)
    {
        $responsavel = $request->input('responsavel');
        $alertas = $this->service->getAlertasAtivos($responsavel);

        return response()->json(['alertas' => $alertas]);
    }

    public function apiCompromissos(Request $request)
    {
        $filtros = $request->only(['responsavel', 'status', 'tipo_atividade', 'somente_alertas', 'page', 'per_page']);
        [$inicio, $fim] = $this->parsePeriodo($request);
        if ($inicio && $fim) {
            $filtros['periodo_inicio'] = $inicio;
            $filtros['periodo_fim'] = $fim;
        }

        $compromissos = $this->service->getCompromissos($filtros);

        return response()->json($compromissos);
    }

    public function apiCruzar()
    {
        $stats = $this->service->executarCruzamento();

        return response()->json([
            'success' => true,
            'stats' => $stats,
            'executado_em' => now()->format('d/m/Y H:i:s'),
        ]);
    }

    // ─── RELATÓRIOS (VIEW) ───────────────────────────────────────────

    public function relatorioIndividual(Request $request)
    {
        $responsavel = $request->input('responsavel', '');
        [$inicio, $fim] = $this->parsePeriodo($request);
        $responsaveis = $this->service->getResponsaveis();

        $dados = null;
        if ($responsavel) {
            $dados = $this->service->getRelatorioIndividual($responsavel, $inicio, $fim);
        }

        return view('vigilia.relatorio-individual', compact('responsaveis', 'responsavel', 'dados', 'inicio', 'fim'));
    }

    public function relatorioPrazos()
    {
        $dados = $this->service->getRelatorioPrazos();

        return view('vigilia.relatorio-prazos', compact('dados'));
    }

    public function relatorioConsolidado(Request $request)
    {
        [$inicio, $fim] = $this->parsePeriodo($request);
        if (!$inicio || !$fim) {
            $inicio = Carbon::now()->startOfMonth()->toDateTimeString();
            $fim = Carbon::now()->endOfMonth()->toDateTimeString();
        }

        $dados = $this->service->getRelatorioConsolidado($inicio, $fim);

        return view('vigilia.relatorio-consolidado', compact('dados', 'inicio', 'fim'));
    }

    public function relatorioCruzamento(Request $request)
    {
        [$inicio, $fim] = $this->parsePeriodo($request);
        $dados = $this->service->getRelatorioCruzamento($inicio, $fim);

        return view('vigilia.relatorio-cruzamento', compact('dados', 'inicio', 'fim'));
    }

    // ─── EXPORTAÇÃO ──────────────────────────────────────────────────

    public function exportExcel(Request $request)
    {
        $filtros = $request->only(['responsavel', 'status', 'tipo_atividade']);
        [$inicio, $fim] = $this->parsePeriodo($request);
        if ($inicio && $fim) {
            $filtros['periodo_inicio'] = $inicio;
            $filtros['periodo_fim'] = $fim;
        }

        $data = $this->export->exportCompromissosExcel($filtros);

        return view('vigilia.export-excel', ['rows' => $data['data']]);
    }

    public function exportPdf(Request $request)
    {
        $tipo = $request->input('tipo', 'prazos');

        switch ($tipo) {
            case 'individual':
                return redirect()->route('vigilia.relatorio.individual', $request->only(['responsavel', 'periodo']));

            case 'consolidado':
                return redirect()->route('vigilia.relatorio.consolidado', $request->only(['periodo']));

            case 'cruzamento':
                return redirect()->route('vigilia.relatorio.cruzamento', $request->only(['periodo']));

            case 'prazos':
            default:
                return redirect()->route('vigilia.relatorio.prazos');
        }
    }

    // ─── HELPERS ─────────────────────────────────────────────────────

    private function parsePeriodo(Request $request): array
    {
        $periodo = $request->input('periodo', 'mes-atual');
        $now = Carbon::now();

        switch ($periodo) {
            case 'mes-anterior':
                return [
                    $now->copy()->subMonth()->startOfMonth()->toDateTimeString(),
                    $now->copy()->subMonth()->endOfMonth()->toDateTimeString(),
                ];
            case 'trimestre':
                return [
                    $now->copy()->subMonths(3)->startOfMonth()->toDateTimeString(),
                    $now->copy()->endOfMonth()->toDateTimeString(),
                ];
            case 'semestre':
                return [
                    $now->copy()->subMonths(6)->startOfMonth()->toDateTimeString(),
                    $now->copy()->endOfMonth()->toDateTimeString(),
                ];
            case 'ano':
                return [
                    $now->copy()->startOfYear()->toDateTimeString(),
                    $now->copy()->endOfYear()->toDateTimeString(),
                ];
            case 'custom':
                return [
                    $request->input('data_inicio'),
                    $request->input('data_fim'),
                ];
            case 'mes-atual':
            default:
                return [
                    $now->copy()->startOfMonth()->toDateTimeString(),
                    $now->copy()->endOfMonth()->toDateTimeString(),
                ];
        }
    }
}
