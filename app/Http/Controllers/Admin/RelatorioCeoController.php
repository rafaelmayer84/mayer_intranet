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

        $this->dispararQueueWorker();

        return back()->with('success', "Relatório #{$relatorio->id} enfileirado. A geração leva entre 3 e 8 minutos.");
    }

    private function dispararQueueWorker(): void
    {
        if (!function_exists('pcntl_fork')) {
            return;
        }

        $pid = pcntl_fork();

        if ($pid === 0) {
            // Filho: desacopla completamente do processo FPM pai
            posix_setsid();
            pcntl_exec(PHP_BINARY, [
                base_path('artisan'),
                'queue:work',
                'database',
                '--stop-when-empty',
                '--timeout=900',
                '--tries=1',
            ]);
            exit(0);
        } elseif ($pid > 0) {
            // Pai: não espera o filho (WNOHANG = não bloqueia)
            pcntl_wait($status, WNOHANG);
        }
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
