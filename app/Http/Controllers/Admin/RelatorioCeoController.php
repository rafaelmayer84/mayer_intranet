<?php

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

        $relatorio = RelatorioCeo::create([
            'periodo_inicio' => $inicio->toDateString(),
            'periodo_fim'    => $fim->toDateString(),
            'status'         => 'queued',
        ]);

        GerarRelatorioCeoJob::dispatch($relatorio->id);

        return back()->with('success', "Relatório #{$relatorio->id} enfileirado. A geração leva entre 3 e 8 minutos.");
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
