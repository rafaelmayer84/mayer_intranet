<?php

namespace App\Http\Controllers;

use App\Models\GdpAcompanhamento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class GdpAcompanhamentoController extends Controller
{
    /**
     * Advogado: exibe/preenche acompanhamento bimestral.
     */
    public function index()
    {
        $user = Auth::user();
        $ciclo = DB::table('gdp_ciclos')->where('status', 'Aberto')->first();

        if (!$ciclo) {
            return view('gdp.acompanhamento', ['ciclo' => null, 'acompanhamentos' => collect()]);
        }

        $acompanhamentos = GdpAcompanhamento::where('user_id', $user->id)
            ->where('ciclo_id', $ciclo->id)
            ->orderBy('bimestre')
            ->get();

        $bimestreAtual = GdpAcompanhamento::mesBimestre((int) now()->format('m'));

        return view('gdp.acompanhamento', compact('ciclo', 'acompanhamentos', 'bimestreAtual'));
    }

    /**
     * Advogado: submete respostas do acompanhamento bimestral.
     */
    public function submeter(Request $request)
    {
        $request->validate([
            'ciclo_id' => 'required|exists:gdp_ciclos,id',
            'bimestre' => 'required|integer|min:1|max:6',
            'respostas' => 'required|array',
        ]);

        $user = Auth::user();
        $ano = (int) now()->format('Y');

        $acomp = GdpAcompanhamento::updateOrCreate(
            [
                'user_id' => $user->id,
                'ciclo_id' => $request->ciclo_id,
                'ano' => $ano,
                'bimestre' => $request->bimestre,
            ],
            [
                'respostas_json' => $request->respostas,
                'status' => 'submitted',
                'submitted_at' => now(),
            ]
        );

        // Audit log
        DB::table('audit_logs')->insert([
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_role' => $user->role,
            'action' => 'gdp_acompanhamento_submit',
            'module' => 'gdp',
            'description' => "Acompanhamento bimestral B{$request->bimestre} submetido | Ciclo: {$request->ciclo_id}",
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'route' => request()->path(),
            'method' => 'POST',
            'created_at' => now(),
        ]);

        return back()->with('success', "Acompanhamento do {$request->bimestre}º bimestre submetido com sucesso.");
    }

    /**
     * Admin: lista todos os acompanhamentos para validação.
     */
    public function admin()
    {
        if (!in_array(Auth::user()->role, ['admin', 'socio'])) {
            abort(403);
        }

        $ciclo = DB::table('gdp_ciclos')->where('status', 'Aberto')->first();

        $acompanhamentos = $ciclo
            ? GdpAcompanhamento::where('ciclo_id', $ciclo->id)
                ->with('user')
                ->orderBy('bimestre')
                ->orderBy('user_id')
                ->get()
            : collect();

        return view('gdp.acompanhamento-admin', compact('ciclo', 'acompanhamentos'));
    }

    /**
     * Admin: valida ou rejeita um acompanhamento.
     */
    public function validar(Request $request, int $id)
    {
        if (!in_array(Auth::user()->role, ['admin', 'socio'])) {
            abort(403);
        }

        $request->validate([
            'status' => 'required|in:validated,rejected',
            'observacoes' => 'nullable|string|max:1000',
        ]);

        $acomp = GdpAcompanhamento::findOrFail($id);
        $acomp->update([
            'status' => $request->status,
            'validated_by' => Auth::id(),
            'validated_at' => now(),
            'observacoes_validador' => $request->observacoes,
        ]);

        DB::table('audit_logs')->insert([
            'user_id' => Auth::id(),
            'user_name' => Auth::user()->name,
            'user_role' => Auth::user()->role,
            'action' => 'gdp_acompanhamento_' . $request->status,
            'module' => 'gdp',
            'description' => "Acompanhamento #{$id} {$request->status} | User: {$acomp->user_id}",
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'route' => request()->path(),
            'method' => 'POST',
            'created_at' => now(),
        ]);

        return back()->with('success', 'Acompanhamento ' . ($request->status === 'validated' ? 'validado' : 'rejeitado') . '.');
    }
}
