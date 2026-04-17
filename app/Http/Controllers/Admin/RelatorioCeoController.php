<?php
// ESTÁVEL desde 17/04/2026

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\GerarRelatorioCeoJob;
use App\Models\RelatorioCeo;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class RelatorioCeoController extends Controller
{
    public function index()
    {
        $relatorios = RelatorioCeo::orderByDesc('created_at')->paginate(20);
        return view('admin.relatorios-ceo.index', compact('relatorios'));
    }

    public function gerar(Request $request)
    {
        $request->validate([
            'periodo_inicio' => 'required|date',
            'periodo_fim'    => 'required|date|after_or_equal:periodo_inicio',
        ]);

        $inicio = Carbon::parse($request->periodo_inicio)->startOfDay();
        $fim    = Carbon::parse($request->periodo_fim)->endOfDay();

        // Evita duplicata de período já em andamento
        $emAndamento = RelatorioCeo::whereIn('status', ['queued', 'running'])
            ->where('periodo_inicio', $inicio->toDateString())
            ->where('periodo_fim', $fim->toDateString())
            ->exists();

        if ($emAndamento) {
            return back()->with('error', 'Já existe uma geração em andamento para esse período.');
        }

        $relatorio = RelatorioCeo::create([
            'periodo_inicio' => $inicio->toDateString(),
            'periodo_fim'    => $fim->toDateString(),
            'status'         => 'queued',
        ]);

        GerarRelatorioCeoJob::dispatch($relatorio->id);

        return back()->with('success', "Relatório #{$relatorio->id} enfileirado para o período {$inicio->format('d/m/Y')} a {$fim->format('d/m/Y')}. Aguarde o processamento.");
    }

    public function download(RelatorioCeo $relatorioCeo)
    {
        if ($relatorioCeo->status !== 'success' || !$relatorioCeo->pdf_path) {
            abort(404, 'PDF não disponível.');
        }

        $path = Storage::disk('local')->path($relatorioCeo->pdf_path);

        if (!file_exists($path)) {
            abort(404, 'Arquivo PDF não encontrado.');
        }

        $filename = "relatorio-ceo-{$relatorioCeo->periodo_inicio->format('Y-m-d')}.pdf";

        return response()->download($path, $filename, ['Content-Type' => 'application/pdf']);
    }
}
