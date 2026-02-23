<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\SisrhVinculo;

class SisrhAdvogadoController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $query = DB::table('users')
            ->leftJoin('sisrh_vinculos as v', 'users.id', '=', 'v.user_id')
            ->whereNotIn('users.id', [2, 5, 6, 9])
            ->whereIn('users.role', ['advogado', 'coordenador', 'socio', 'admin'])
            ->select('users.*', 'v.id as vinculo_id', 'v.nivel_senioridade as v_nivel',
                'v.data_inicio_exercicio', 'v.equipe_id', 'v.ativo as v_ativo',
                'v.cpf', 'v.oab', 'v.rg', 'v.observacoes',
                'v.endereco_rua', 'v.endereco_numero', 'v.endereco_complemento',
                'v.endereco_bairro', 'v.endereco_cep', 'v.endereco_cidade', 'v.endereco_estado',
                'v.nome_pai', 'v.nome_mae');

        // Permissões
        if ($user->role === 'coordenador') {
            $equipeId = SisrhVinculo::where('user_id', $user->id)->value('equipe_id');
            if ($equipeId) {
                $query->where(function ($q) use ($equipeId, $user) {
                    $q->where('v.equipe_id', $equipeId)->orWhere('users.id', $user->id);
                });
            } else {
                $query->where('users.id', $user->id);
            }
        } elseif ($user->role === 'socio') {
            $query->where('users.id', $user->id);
        } elseif ($user->role !== 'admin') {
            abort(403);
        }

        // Filtros
        if ($request->filled('nome')) {
            $query->where('users.name', 'like', '%' . $request->nome . '%');
        }
        if ($request->filled('role')) {
            $query->where('users.role', $request->role);
        }
        if ($request->filled('status')) {
            if ($request->status === 'ativo') $query->where('v.ativo', true);
            elseif ($request->status === 'inativo') $query->where('v.ativo', false);
            elseif ($request->status === 'sem_vinculo') $query->whereNull('v.id');
        }

        $advogados = $query->orderBy('users.name')->get();

        return view('sisrh.advogados', compact('advogados'));
    }

    public function ativar(Request $request)
    {
        $this->checkAdminCoord();

        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'nivel_senioridade' => 'required|in:Junior,Pleno,Senior_I,Senior_II,Senior_III',
            'data_inicio_exercicio' => 'nullable|date',
            'equipe_id' => 'nullable|integer',
            'cpf' => 'nullable|string|max:20',
            'oab' => 'nullable|string|max:30',
            'rg' => 'nullable|string|max:30',
            'observacoes' => 'nullable|string|max:500',
        ]);

        $vinculo = SisrhVinculo::updateOrCreate(
            ['user_id' => $request->user_id],
            [
                'nivel_senioridade' => $request->nivel_senioridade,
                'data_inicio_exercicio' => $request->data_inicio_exercicio,
                'equipe_id' => $request->equipe_id,
                'ativo' => true,
                'cpf' => $request->cpf,
                'oab' => $request->oab,
                'rg' => $request->rg,
                'observacoes' => $request->observacoes,
                'created_by' => Auth::id(),
            ]
        );

        // Sync nivel_senioridade na users
        DB::table('users')->where('id', $request->user_id)->update(['nivel_senioridade' => $request->nivel_senioridade]);

        $this->auditLog('sisrh_vinculo_ativar', 'User ID:' . $request->user_id . ' ativado no SISRH');

        return back()->with('success', 'Advogado ativado no SISRH.');
    }

    public function editar(Request $request, int $id)
    {
        $this->checkAdminCoord();

        $vinculo = SisrhVinculo::findOrFail($id);

        $request->validate([
            'nivel_senioridade' => 'required|in:Junior,Pleno,Senior_I,Senior_II,Senior_III',
            'data_inicio_exercicio' => 'nullable|date',
            'equipe_id' => 'nullable|integer',
            'cpf' => 'nullable|string|max:20',
            'oab' => 'nullable|string|max:30',
            'rg' => 'nullable|string|max:30',
            'endereco_rua' => 'nullable|string|max:150',
            'endereco_numero' => 'nullable|string|max:20',
            'endereco_complemento' => 'nullable|string|max:100',
            'endereco_bairro' => 'nullable|string|max:80',
            'endereco_cep' => 'nullable|string|max:15',
            'endereco_cidade' => 'nullable|string|max:80',
            'endereco_estado' => 'nullable|string|max:2',
            'nome_pai' => 'nullable|string|max:150',
            'nome_mae' => 'nullable|string|max:150',
            'observacoes' => 'nullable|string|max:500',
        ]);

        $vinculo->update($request->only([
            'nivel_senioridade', 'data_inicio_exercicio', 'equipe_id',
            'cpf', 'oab', 'rg', 'observacoes',
            'endereco_rua', 'endereco_numero', 'endereco_complemento',
            'endereco_bairro', 'endereco_cep', 'endereco_cidade', 'endereco_estado',
            'nome_pai', 'nome_mae',
        ]));

        DB::table('users')->where('id', $vinculo->user_id)->update(['nivel_senioridade' => $request->nivel_senioridade]);

        $this->auditLog('sisrh_vinculo_editar', 'Vinculo ID:' . $id . ' editado');

        return back()->with('success', 'Vínculo atualizado.');
    }

    public function desativar(int $id)
    {
        $this->checkAdminCoord();

        $vinculo = SisrhVinculo::findOrFail($id);
        $vinculo->update(['ativo' => false]);

        $this->auditLog('sisrh_vinculo_desativar', 'Vinculo ID:' . $id . ' (user ' . $vinculo->user_id . ') desativado');

        return back()->with('success', 'Advogado desativado no SISRH.');
    }

    public function reativar(int $id)
    {
        $this->checkAdminCoord();

        $vinculo = SisrhVinculo::findOrFail($id);
        $vinculo->update(['ativo' => true]);

        $this->auditLog('sisrh_vinculo_reativar', 'Vinculo ID:' . $id . ' (user ' . $vinculo->user_id . ') reativado');

        return back()->with('success', 'Advogado reativado no SISRH.');
    }

    private function checkAdminCoord(): void
    {
        $user = Auth::user();
        if (!$user || !in_array($user->role, ['admin', 'coordenador'])) abort(403);
    }

    private function auditLog(string $acao, string $descricao): void
    {
        DB::table('audit_logs')->insert([
            'user_id' => Auth::id(),
            'action' => $acao,
            'description' => $descricao, 'module' => 'sisrh', 'user_name' => Auth::user()->name, 'user_role' => Auth::user()->role,
            'created_at' => now(),
        ]);
    }
}
