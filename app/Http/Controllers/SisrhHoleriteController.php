<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\SisrhRubrica;
use App\Models\SisrhHoleriteLancamento;
use App\Services\Sisrh\SisrhHoleriteService;

class SisrhHoleriteController extends Controller
{
    private SisrhHoleriteService $holeriteService;

    public function __construct(SisrhHoleriteService $holeriteService)
    {
        $this->holeriteService = $holeriteService;
    }

    public function meuContracheque(Request $request)
    {
        $ano = $request->get('ano', now()->year);
        $mes = $request->get('mes', now()->month);
        $holerite = $this->holeriteService->gerarContracheque(Auth::id(), $ano, $mes);
        return view('sisrh.contracheque', compact('holerite', 'ano', 'mes'));
    }

    public function folha(Request $request)
    {
        $this->checkAdmin();
        $ano = $request->get('ano', now()->year);
        $mes = $request->get('mes', now()->month);
        $folha = $this->holeriteService->gerarFolha($ano, $mes);
        return view('sisrh.folha', compact('folha', 'ano', 'mes'));
    }

    public function lancamentos(Request $request)
    {
        $this->checkAdmin();
        $ano = $request->get('ano', now()->year);
        $mes = $request->get('mes', now()->month);
        $users = DB::table('users')->whereNotIn('id', [2,5,6,9])
            ->whereIn('role', ['advogado','coordenador','socio','admin'])->orderBy('name')->get();
        $rubricas = SisrhRubrica::where('ativo', true)->where('automatica', false)->orderBy('ordem')->get();
        $lancamentos = SisrhHoleriteLancamento::where('ano', $ano)->where('mes', $mes)
            ->with('rubrica', 'user')->orderBy('user_id')->get();
        return view('sisrh.lancamentos', compact('users', 'rubricas', 'lancamentos', 'ano', 'mes'));
    }

    public function salvarLancamento(Request $request)
    {
        $this->checkAdmin();
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'rubrica_id' => 'required|integer|exists:sisrh_rubricas,id',
            'ano' => 'required|integer', 'mes' => 'required|integer|min:1|max:12',
            'valor' => 'required|numeric|min:0',
            'referencia' => 'nullable|string|max:20',
            'observacao' => 'nullable|string|max:255',
        ]);
        SisrhHoleriteLancamento::updateOrCreate(
            ['user_id' => $request->user_id, 'rubrica_id' => $request->rubrica_id, 'ano' => $request->ano, 'mes' => $request->mes],
            ['valor' => $request->valor, 'referencia' => $request->referencia, 'observacao' => $request->observacao, 'origem' => 'manual', 'created_by' => Auth::id()]
        );
        return back()->with('success', 'Lançamento salvo.');
    }

    public function excluirLancamento(int $id)
    {
        $this->checkAdmin();
        SisrhHoleriteLancamento::findOrFail($id)->delete();
        return back()->with('success', 'Lançamento excluído.');
    }

    public function rubricas()
    {
        $this->checkAdmin();
        $rubricas = SisrhRubrica::orderBy('ordem')->get();
        return view('sisrh.rubricas', compact('rubricas'));
    }

    public function salvarRubrica(Request $request)
    {
        $this->checkAdmin();
        $request->validate(['codigo' => 'required|string|max:10|unique:sisrh_rubricas,codigo', 'nome' => 'required|string|max:100', 'tipo' => 'required|in:provento,desconto']);
        SisrhRubrica::create(['codigo' => $request->codigo, 'nome' => $request->nome, 'tipo' => $request->tipo, 'automatica' => false, 'ativo' => true, 'ordem' => $request->ordem ?? 50]);
        return back()->with('success', 'Rubrica criada.');
    }

    public function atualizarRubrica(Request $request, int $id)
    {
        $this->checkAdmin();
        $rubrica = SisrhRubrica::findOrFail($id);
        $request->validate(['nome' => 'required|string|max:100', 'tipo' => 'required|in:provento,desconto']);
        $rubrica->update(['nome' => $request->nome, 'tipo' => $request->tipo, 'ordem' => $request->ordem ?? $rubrica->ordem, 'ativo' => $request->has('ativo')]);
        return back()->with('success', 'Rubrica atualizada.');
    }

    public function contrachequePrint(Request $request)
    {
        $ano = $request->get('ano', now()->year);
        $mes = $request->get('mes', now()->month);
        $userId = ($request->has('user_id') && $this->isAdmin()) ? (int) $request->user_id : Auth::id();
        $holerite = $this->holeriteService->gerarContracheque($userId, $ano, $mes);
        return view('sisrh.contracheque', compact('holerite', 'ano', 'mes'));
    }

    private function checkAdmin(): void
    {
        $user = Auth::user();
        if (!$user || !in_array($user->role, ['admin', 'coordenador'])) abort(403, 'Acesso restrito.');
    }

    private function isAdmin(): bool
    {
        $user = Auth::user();
        return $user && in_array($user->role, ['admin', 'coordenador']);
    }
}
