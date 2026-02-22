<?php

namespace App\Http\Controllers;

use App\Models\GdpRemuneracaoFaixa;
use App\Models\SisrhApuracao;
use App\Models\SisrhAjuste;
use App\Models\SisrhBancoCreditoMov;
use App\Models\SisrhRbNivel;
use App\Models\SisrhRbOverride;
use App\Services\Sisrh\SisrhApuracaoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SisrhController extends Controller
{
    private SisrhApuracaoService $apuracaoService;

    public function __construct(SisrhApuracaoService $apuracaoService)
    {
        $this->apuracaoService = $apuracaoService;
    }

    // ──────────────────────────────────────────
    // VISÃO GERAL /sisrh
    // ──────────────────────────────────────────
    public function index()
    {
        $user = Auth::user();
        $ciclo = DB::table('gdp_ciclos')->where('status', 'Aberto')->first();

        // Admin vê todos; coordenador vê equipe; sócio vê só ele
        if (in_array($user->role, ['admin'])) {
            $userIds = DB::table('users')
                ->whereNotIn('id', [2, 5, 6])
                
                ->pluck('id');
        } elseif ($user->role === 'coordenador') {
            $userIds = DB::table('users')
                ->where('role', 'advogado')
                
                ->pluck('id');
        } else {
            $userIds = collect([$user->id]);
        }

        $mesAtual = (int) now()->format('m');
        $anoAtual = (int) now()->format('Y');

        $apuracoes = SisrhApuracao::whereIn('user_id', $userIds)
            ->where('ano', $anoAtual)
            ->orderByDesc('mes')
            ->with('user')
            ->get();

        $saldos = [];
        foreach ($userIds as $uid) {
            $saldos[$uid] = SisrhBancoCreditoMov::saldo($uid);
        }

        return view('sisrh.index', compact('apuracoes', 'saldos', 'ciclo', 'mesAtual', 'anoAtual', 'user'));
    }

    // ──────────────────────────────────────────
    // REGRAS RB /sisrh/regras-rb
    // ──────────────────────────────────────────
    public function regrasRb()
    {
        $this->checkAdmin();

        $ciclo = DB::table('gdp_ciclos')->where('status', 'Aberto')->first();
        $niveis = SisrhRbNivel::where('ciclo_id', $ciclo->id ?? 0)->get();
        $overrides = SisrhRbOverride::where('ciclo_id', $ciclo->id ?? 0)->with('user')->get();
        $faixas = GdpRemuneracaoFaixa::where('ciclo_id', $ciclo->id ?? 0)->orderBy('score_min')->get();
        $users = DB::table('users')->whereNotIn('id', [2, 5, 6])->get(['id', 'name', 'nivel_senioridade']);

        return view('sisrh.regras-rb', compact('ciclo', 'niveis', 'overrides', 'faixas', 'users'));
    }

    public function salvarRbNivel(Request $request)
    {
        $this->checkAdmin();

        $request->validate([
            'nivel' => 'required|in:' . implode(',', SisrhRbNivel::NIVEIS),
            'ciclo_id' => 'required|exists:gdp_ciclos,id',
            'valor_rb' => 'required|numeric|min:0',
        ]);

        SisrhRbNivel::updateOrCreate(
            ['nivel' => $request->nivel, 'ciclo_id' => $request->ciclo_id],
            [
                'valor_rb' => $request->valor_rb,
                'vigencia_inicio' => now()->toDateString(),
                'created_by' => Auth::id(),
            ]
        );

        $this->auditLog('sisrh_rb_nivel', "RB nível {$request->nivel} = R$ {$request->valor_rb}");

        return back()->with('success', 'RB por nível salva com sucesso.');
    }

    public function salvarRbOverride(Request $request)
    {
        $this->checkAdmin();

        $request->validate([
            'user_id' => 'required|exists:users,id',
            'ciclo_id' => 'required|exists:gdp_ciclos,id',
            'valor_rb' => 'required|numeric|min:0',
            'motivo' => 'required|string|max:500',
        ]);

        SisrhRbOverride::updateOrCreate(
            ['user_id' => $request->user_id, 'ciclo_id' => $request->ciclo_id],
            [
                'valor_rb' => $request->valor_rb,
                'motivo' => $request->motivo,
                'created_by' => Auth::id(),
            ]
        );

        $this->auditLog('sisrh_rb_override', "Override RB user:{$request->user_id} = R$ {$request->valor_rb}");

        return back()->with('success', 'Override de RB salvo com sucesso.');
    }

    public function salvarFaixa(Request $request)
    {
        $this->checkAdmin();

        $request->validate([
            'ciclo_id' => 'required|exists:gdp_ciclos,id',
            'score_min' => 'required|numeric|min:0',
            'score_max' => 'required|numeric|gt:score_min',
            'percentual_remuneracao' => 'required|numeric|min:0|max:100',
            'label' => 'nullable|string|max:50',
        ]);

        GdpRemuneracaoFaixa::create($request->only(['ciclo_id', 'score_min', 'score_max', 'percentual_remuneracao', 'label']));

        $this->auditLog('sisrh_faixa', "Faixa GDP {$request->score_min}-{$request->score_max} = {$request->percentual_remuneracao}%");

        return back()->with('success', 'Faixa de remuneração criada.');
    }

    public function excluirFaixa(int $id)
    {
        $this->checkAdmin();
        GdpRemuneracaoFaixa::findOrFail($id)->delete();
        return back()->with('success', 'Faixa removida.');
    }

    // ──────────────────────────────────────────
    // APURAÇÃO /sisrh/apuracao
    // ──────────────────────────────────────────
    public function apuracao()
    {
        $this->checkAdmin();

        $ciclo = DB::table('gdp_ciclos')->where('status', 'Aberto')->first();
        $users = DB::table('users')
            ->whereNotIn('id', [2, 5, 6])
            
            ->whereIn('role', ['advogado', 'coordenador', 'socio', 'admin'])
            ->get(['id', 'name', 'nivel_senioridade', 'role']);

        return view('sisrh.apuracao', compact('ciclo', 'users'));
    }

    public function simular(Request $request)
    {
        $this->checkAdmin();

        if ($request->isJson()) {
            $request->merge(json_decode($request->getContent(), true) ?? []);
        }

        $request->validate([
            'ano' => 'required|integer|min:2024|max:2030',
            'mes' => 'required|integer|min:1|max:12',
            'ignorar_bloqueio' => 'nullable|boolean',
        ]);

        $ciclo = DB::table('gdp_ciclos')->where('status', 'Aberto')->first();
        if (!$ciclo) {
            return response()->json(['erro' => 'Nenhum ciclo GDP aberto.'], 422);
        }

        $users = DB::table('users')
            ->whereNotIn('id', [2, 5, 6])
            
            ->whereIn('role', ['advogado', 'coordenador', 'socio', 'admin'])
            ->pluck('id');

        $resultados = [];
        foreach ($users as $userId) {
            $resultados[] = $this->apuracaoService->apurar(
                $userId, $request->ano, $request->mes, $ciclo->id, false, (bool) $request->ignorar_bloqueio
            );
        }

        return response()->json(['resultados' => $resultados]);
    }

    public function fecharCompetencia(Request $request)
    {
        $this->checkAdmin();

        if ($request->isJson()) {
            $request->merge(json_decode($request->getContent(), true) ?? []);
        }

        $request->validate([
            'ano' => 'required|integer',
            'mes' => 'required|integer|min:1|max:12',
        ]);

        $ciclo = DB::table('gdp_ciclos')->where('status', 'Aberto')->first();
        if (!$ciclo) {
            return response()->json(['erro' => 'Nenhum ciclo GDP aberto.'], 422);
        }

        $users = DB::table('users')
            ->whereNotIn('id', [2, 5, 6])
            
            ->whereIn('role', ['advogado', 'coordenador', 'socio', 'admin'])
            ->pluck('id');

        $resultados = [];
        DB::transaction(function () use ($users, $request, $ciclo, &$resultados) {
            foreach ($users as $userId) {
                // Persistir apuração
                $dados = $this->apuracaoService->apurar(
                    $userId, $request->ano, $request->mes, $ciclo->id, true, (bool) $request->ignorar_bloqueio
                );

                if (isset($dados['erro'])) {
                    $resultados[] = $dados;
                    continue;
                }

                // Fechar
                $apuracao = SisrhApuracao::where('user_id', $userId)
                    ->where('ano', $request->ano)
                    ->where('mes', $request->mes)
                    ->first();

                if ($apuracao && !$apuracao->isClosed()) {
                    $this->apuracaoService->fechar($apuracao->id, Auth::id());
                }

                $resultados[] = $dados;
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Competência fechada com sucesso.',
            'resultados' => $resultados,
        ]);
    }

    // ──────────────────────────────────────────
    // ESPELHO /sisrh/espelho/{ano}/{mes}/{user}
    // ──────────────────────────────────────────
    public function espelho(int $ano, int $mes, int $userId)
    {
        $user = Auth::user();

        // Verificar permissão de acesso
        if ($user->role === 'socio' && $user->id != $userId) {
            abort(403, 'Sócios podem visualizar apenas seus próprios dados.');
        }
        if ($user->role === 'advogado' && $user->id != $userId) {
            abort(403);
        }

        $apuracao = SisrhApuracao::where('user_id', $userId)
            ->where('ano', $ano)
            ->where('mes', $mes)
            ->first();

        $advogado = DB::table('users')->where('id', $userId)->first(['id', 'name', 'role', 'nivel_senioridade']);
        $ajustes = $apuracao ? SisrhAjuste::where('apuracao_id', $apuracao->id)->get() : collect();
        $saldo = SisrhBancoCreditoMov::saldo($userId);

        return view('sisrh.espelho', compact('apuracao', 'advogado', 'ajustes', 'saldo', 'ano', 'mes'));
    }

    // ──────────────────────────────────────────
    // BANCO DE CRÉDITOS /sisrh/banco-creditos
    // ──────────────────────────────────────────
    public function bancoCreditos()
    {
        $user = Auth::user();

        if (in_array($user->role, ['admin'])) {
            $userIds = DB::table('users')->whereNotIn('id', [2, 5, 6])->pluck('id');
        } elseif ($user->role === 'coordenador') {
            $userIds = DB::table('users')->where('role', 'advogado')->pluck('id');
        } else {
            $userIds = collect([$user->id]);
        }

        $movimentacoes = SisrhBancoCreditoMov::whereIn('user_id', $userIds)
            ->orderByDesc('created_at')
            ->with('user')
            ->paginate(30);

        $saldos = [];
        foreach ($userIds as $uid) {
            $userName = DB::table('users')->where('id', $uid)->value('name');
            $saldos[$uid] = [
                'nome' => $userName,
                'saldo' => SisrhBancoCreditoMov::saldo($uid),
            ];
        }

        return view('sisrh.banco-creditos', compact('movimentacoes', 'saldos'));
    }

    // ──────────────────────────────────────────
    // AJUSTES
    // ──────────────────────────────────────────
    public function lancarAjuste(Request $request)
    {
        $this->checkAdmin();

        $request->validate([
            'apuracao_id' => 'required|exists:sisrh_apuracoes,id',
            'tipo' => 'required|in:bonus,desconto,correcao,estorno',
            'valor' => 'required|numeric',
            'motivo' => 'required|string|max:1000',
        ]);

        $apuracao = SisrhApuracao::findOrFail($request->apuracao_id);

        if (!$apuracao->isClosed()) {
            return back()->with('error', 'Ajustes só podem ser lançados em apurações fechadas.');
        }

        SisrhAjuste::create([
            'apuracao_id' => $request->apuracao_id,
            'tipo' => $request->tipo,
            'valor' => $request->valor,
            'motivo' => $request->motivo,
            'created_by' => Auth::id(),
        ]);

        $this->auditLog('sisrh_ajuste', "Ajuste {$request->tipo} R$ {$request->valor} na apuração #{$request->apuracao_id}: {$request->motivo}");

        return back()->with('success', 'Ajuste lançado com sucesso.');
    }

    // ──────────────────────────────────────────
    // HELPERS
    // ──────────────────────────────────────────
    public function salvarSenioridade(Request $request)
    {
        $this->checkAdmin();
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'nivel_senioridade' => 'required|in:Junior,Pleno,Senior_I,Senior_II,Senior_III',
        ]);
        DB::table('users')->where('id', $request->user_id)->update([
            'nivel_senioridade' => $request->nivel_senioridade,
        ]);
        $this->auditLog('sisrh_senioridade', 'Nivel user:' . $request->user_id . ' alterado para ' . $request->nivel_senioridade);
        return back()->with('success', 'Nivel de senioridade atualizado.');
    }

    private function checkAdmin(): void
    {
        if (!in_array(Auth::user()->role, ['admin', 'socio'])) {
            abort(403);
        }
    }

    private function auditLog(string $action, string $description): void
    {
        DB::table('audit_logs')->insert([
            'user_id' => Auth::id(),
            'user_name' => Auth::user()->name,
            'user_role' => Auth::user()->role,
            'action' => $action,
            'module' => 'sisrh',
            'description' => $description,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'route' => request()->path(),
            'method' => request()->method(),
            'created_at' => now(),
        ]);
    }
}
