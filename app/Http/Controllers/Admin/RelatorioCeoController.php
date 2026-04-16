<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RelatorioCeo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class RelatorioCeoController extends Controller
{
    public function index()
    {
        $relatorios = RelatorioCeo::orderByDesc('created_at')->paginate(20);
        return view('admin.relatorios-ceo.index', compact('relatorios'));
    }

    public function download(RelatorioCeo $relatorioCeo)
    {
        if ($relatorioCeo->status !== 'success' || !$relatorioCeo->pdf_path) {
            abort(404, 'PDF não disponível.');
        }

        $path = \Illuminate\Support\Facades\Storage::disk('local')->path($relatorioCeo->pdf_path);

        if (!file_exists($path)) {
            abort(404, 'Arquivo PDF não encontrado.');
        }

        $filename = "relatorio-ceo-{$relatorioCeo->periodo_inicio->format('Y-m-d')}.pdf";

        return response()->download($path, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
