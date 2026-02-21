<?php

namespace App\Http\Controllers;

use App\Models\GdpCiclo;
use App\Models\GdpSnapshot;
use App\Models\GdpResultadoMensal;
use App\Models\GdpIndicador;
use App\Models\GdpEixo;
use App\Models\User;
use App\Services\Gdp\GdpScoreService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\Gdp\GdpPenalizacao;
use App\Models\Gdp\GdpPenalizacaoTipo;
use App\Services\Gdp\GdpPenalizacaoScanner;

class GdpController extends Controller
{
    // =========================================================================
    // MINHA PERFORMANCE (sócio vê só o seu, coordenador/admin vê qualquer um)
    // =========================================================================
    public function minhaPerformance(Request $request)
    {
        $user = Auth::user();
        $mes  = (int) $request->input('month', now()->month);
        $ano  = (int) $request->input('year', now()->year);

        // Coordenador/admin pode ver outro usuário
        $targetUserId = $user->id;
        if (($user->isAdmin() || $user->isCoordenador()) && $request->filled('user_id')) {
            $targetUserId = (int) $request->input('user_id');
        }

        $targetUser = User::find($targetUserId);
        if (!$targetUser) {
            abort(404, 'Usuário não encontrado');
        }

        $ciclo = GdpCiclo::ativo();

        $snapshot   = null;
        $resultados = collect();
        $eixos      = collect();

        if ($ciclo) {
            $snapshot = GdpSnapshot::where('ciclo_id', $ciclo->id)
                ->where('user_id', $targetUserId)
                ->where('mes', $mes)
                ->where('ano', $ano)
                ->first();

            $resultados = GdpResultadoMensal::where('ciclo_id', $ciclo->id)
                ->where('user_id', $targetUserId)
                ->where('mes', $mes)
                ->where('ano', $ano)
                ->with(['indicador.eixo'])
                ->get()
                ->keyBy(fn($r) => $r->indicador->codigo ?? $r->indicador_id);

            $eixos = GdpEixo::where('ciclo_id', $ciclo->id)
                ->with(['indicadores' => fn($q) => $q->where('ativo', true)->orderBy('ordem')])
                ->orderBy('ordem')
                ->get();
        }

        // Histórico de snapshots (últimos 6 meses) para sparkline
        $historico = $ciclo ? GdpSnapshot::where('ciclo_id', $ciclo->id)
            ->where('user_id', $targetUserId)
            ->orderBy('ano')
            ->orderBy('mes')
            ->limit(12)
            ->get() : collect();

        // Metas do usuário
        $metas = $ciclo ? DB::table('gdp_metas_individuais')
            ->where('ciclo_id', $ciclo->id)
            ->where('user_id', $targetUserId)
            ->where('mes', $mes)
            ->where('ano', $ano)
            ->get()
            ->keyBy('indicador_id') : collect();

        // Lista de usuários para seletor (coordenador/admin)
        $usuariosDisponiveis = collect();
        if ($user->isAdmin() || $user->isCoordenador()) {
            $usuariosDisponiveis = User::where('ativo', true)
                ->whereNotNull('datajuri_proprietario_id')
                ->orderBy('name')
                ->get(['id', 'name', 'role']);
        }

        $refDate = Carbon::createFromDate($ano, $mes, 1);

        return view('gdp.minha-performance', compact(
            'ciclo', 'snapshot', 'resultados', 'eixos', 'metas',
            'historico', 'targetUser', 'usuariosDisponiveis',
            'mes', 'ano', 'refDate', 'user'
        ));
    }

    // =========================================================================
    // EQUIPE (coordenador/admin vê ranking de todos)
    // =========================================================================
    public function equipe(Request $request)
    {
        $user = Auth::user();
        $mes  = (int) $request->input('month', now()->month);
        $ano  = (int) $request->input('year', now()->year);

        $ciclo = GdpCiclo::ativo();

        $ranking   = collect();
        $eixos     = collect();
        $mediaEixo = [];

        if ($ciclo) {
            $ranking = GdpSnapshot::where('ciclo_id', $ciclo->id)
                ->where('mes', $mes)
                ->where('ano', $ano)
                ->with('user')
                ->orderBy('ranking')
                ->get();

            $eixos = GdpEixo::where('ciclo_id', $ciclo->id)->orderBy('ordem')->get();

            // Médias por eixo para gráfico comparativo
            if ($ranking->isNotEmpty()) {
                $mediaEixo = [
                    'juridico'        => round($ranking->avg('score_juridico'), 2),
                    'financeiro'      => round($ranking->avg('score_financeiro'), 2),
                    'desenvolvimento' => round($ranking->avg('score_desenvolvimento'), 2),
                    'atendimento'     => round($ranking->avg('score_atendimento'), 2),
                ];
            }
        }

        $refDate = Carbon::createFromDate($ano, $mes, 1);

        return view('gdp.equipe', compact(
            'ciclo', 'ranking', 'eixos', 'mediaEixo',
            'mes', 'ano', 'refDate', 'user'
        ));
    }

    // =========================================================================
    // APURAR MÊS (admin only - via AJAX)
    // =========================================================================
    public function apurar(Request $request)
    {
        if (!Auth::user()->isAdmin()) {
            return response()->json(['erro' => 'Acesso negado'], 403);
        }

        $mes = (int) $request->input('mes', now()->month);
        $ano = (int) $request->input('ano', now()->year);

        $service = app(GdpScoreService::class);
        $stats   = $service->apurarMes($mes, $ano);

        return response()->json($stats);
    }

    // =========================================================================
    // DADOS JSON para gráficos (drilldown de um usuário)
    // =========================================================================
    public function dadosUsuario(Request $request, int $userId)
    {
        $user = Auth::user();

        // Sócio só pode ver os próprios dados
        if (!$user->isAdmin() && !$user->isCoordenador() && $user->id !== $userId) {
            return response()->json(['erro' => 'Acesso negado'], 403);
        }

        $ciclo = GdpCiclo::ativo();
        if (!$ciclo) {
            return response()->json(['historico' => [], 'eixos' => []]);
        }

        $historico = GdpSnapshot::where('ciclo_id', $ciclo->id)
            ->where('user_id', $userId)
            ->orderBy('ano')
            ->orderBy('mes')
            ->get()
            ->map(fn($s) => [
                'label'            => str_pad($s->mes, 2, '0', STR_PAD_LEFT) . '/' . $s->ano,
                'score_total'      => (float) $s->score_total,
                'juridico'         => (float) $s->score_juridico,
                'financeiro'       => (float) $s->score_financeiro,
                'desenvolvimento'  => (float) $s->score_desenvolvimento,
                'atendimento'      => (float) $s->score_atendimento,
                'ranking'          => $s->ranking,
            ]);

        return response()->json(['historico' => $historico]);
    }

    // =========================================================================
    // ACORDO DE DESEMPENHO
    // =========================================================================

    public function acordo(Request $request)
    {
        if (!in_array(Auth::user()->role, ['admin', 'socio'])) {
            abort(403, 'Acesso restrito');
        }

        $ciclo = GdpCiclo::ativo();
        if (!$ciclo) {
            return redirect()->route('gdp.minha-performance')
                ->with('error', 'Nenhum ciclo GDP ativo.');
        }

        $userId = $request->input('user_id');

        $usuarios = \App\Models\User::where('ativo', true)
            ->whereIn('role', ['admin', 'coordenador', 'advogado', 'socio'])
            ->orderBy('name')
            ->get(['id', 'name', 'role', 'cargo']);

        $targetUser = $userId ? \App\Models\User::find($userId) : null;

        $eixos = GdpEixo::where('ciclo_id', $ciclo->id)
            ->with(['indicadores' => fn($q) => $q->where('ativo', true)->where('status_v1', 'score')->orderBy('ordem')])
            ->orderBy('ordem')
            ->get();

        $mesInicio = (int) $ciclo->data_inicio->format('n');
        $mesFim    = (int) $ciclo->data_fim->format('n');
        $ano       = (int) $ciclo->data_inicio->format('Y');

        $metas = collect();
        $acordoAceito = false;
        if ($targetUser) {
            // Auto-inclusao: se advogado nao tem metas, criar malha automaticamente
            $temMetas = DB::table('gdp_metas_individuais')
                ->where('ciclo_id', $ciclo->id)
                ->where('user_id', $targetUser->id)
                ->exists();

            if (!$temMetas) {
                $indicadores = \App\Models\GdpIndicador::where('ativo', true)
                    ->where('status_v1', 'score')->get();
                $agora = now();
                foreach ($indicadores as $ind) {
                    for ($m = $mesInicio; $m <= $mesFim; $m++) {
                        DB::table('gdp_metas_individuais')->insert([
                            'ciclo_id'     => $ciclo->id,
                            'indicador_id' => $ind->id,
                            'user_id'      => $targetUser->id,
                            'mes'          => $m,
                            'ano'          => $ano,
                            'valor_meta'   => 0,
                            'created_at'   => $agora,
                            'updated_at'   => $agora,
                        ]);
                    }
                }
            }

            $metas = DB::table('gdp_metas_individuais')
                ->where('ciclo_id', $ciclo->id)
                ->where('user_id', $targetUser->id)
                ->get()
                ->keyBy(fn($m) => $m->indicador_id . '_' . $m->mes);

            $acordoAceito = DB::table('gdp_snapshots')
                ->where('ciclo_id', $ciclo->id)
                ->where('user_id', $targetUser->id)
                ->where('congelado', true)
                ->exists();
        }

        return view('gdp.acordo', compact(
            'ciclo', 'usuarios', 'targetUser', 'eixos',
            'mesInicio', 'mesFim', 'ano', 'metas', 'acordoAceito'
        ));
    }

    public function salvarAcordo(Request $request)
    {
        if (!in_array(Auth::user()->role, ['admin', 'socio'])) {
            return response()->json(['erro' => 'Acesso negado'], 403);
        }

        $ciclo = GdpCiclo::ativo();
        if (!$ciclo) {
            return response()->json(['erro' => 'Nenhum ciclo ativo'], 400);
        }

        $userId = (int) $request->input('user_id');
        if (!$userId) {
            return response()->json(['erro' => 'Usuario nao informado'], 400);
        }

        $metasInput = $request->input('metas', []);
        $adminId    = Auth::id();
        $saved      = 0;
        $now        = now();
        $mesInicio  = (int) $ciclo->data_inicio->format('n');
        $mesFim     = (int) $ciclo->data_fim->format('n');
        $ano        = (int) $ciclo->data_inicio->format('Y');

        DB::beginTransaction();
        try {
            foreach ($metasInput as $indicadorId => $meses) {
                foreach ($meses as $mes => $valor) {
                    $mes = (int) $mes;
                    if ($mes < $mesInicio || $mes > $mesFim) continue;

                    $valorMeta = str_replace(['.', ','], ['', '.'], $valor);
                    $valorMeta = (float) $valorMeta;

                    DB::table('gdp_metas_individuais')->updateOrInsert(
                        [
                            'ciclo_id'     => $ciclo->id,
                            'indicador_id' => (int) $indicadorId,
                            'user_id'      => $userId,
                            'mes'          => $mes,
                            'ano'          => $ano,
                        ],
                        [
                            'valor_meta'   => $valorMeta,
                            'definido_por' => $adminId,
                            'updated_at'   => $now,
                        ]
                    );
                    $saved++;
                }
            }
            DB::commit();

            DB::table('gdp_audit_log')->insert([
                'user_id'         => $adminId,
                'entidade'        => 'gdp_metas_individuais',
                'entidade_id'     => $userId,
                'campo'           => 'acordo_salvo',
                'valor_anterior'  => null,
                'valor_novo'      => json_encode(['metas_salvas' => $saved, 'ciclo' => $ciclo->nome]),
                'justificativa'   => 'Acordo de desempenho salvo via interface',
                'ip'              => $request->ip(),
                'created_at'      => $now,
            ]);

            return response()->json(['sucesso' => true, 'metas_salvas' => $saved]);
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('GDP Acordo salvar erro: ' . $e->getMessage());
            return response()->json(['erro' => 'Erro interno: ' . $e->getMessage()], 500);
        }
    }

    public function visualizarAcordo(Request $request, int $userId)
    {
        $user = Auth::user();
        if (!in_array($user->role, ['admin', 'socio', 'coordenador']) && $user->id !== $userId) {
            abort(403, 'Acesso negado');
        }

        $ciclo = GdpCiclo::ativo();
        if (!$ciclo) {
            return redirect()->route('gdp.minha-performance')->with('error', 'Nenhum ciclo ativo.');
        }

        $targetUser = \App\Models\User::findOrFail($userId);

        $eixos = GdpEixo::where('ciclo_id', $ciclo->id)
            ->with(['indicadores' => fn($q) => $q->where('ativo', true)->where('status_v1', 'score')->orderBy('ordem')])
            ->orderBy('ordem')
            ->get();

        $mesInicio = (int) $ciclo->data_inicio->format('n');
        $mesFim    = (int) $ciclo->data_fim->format('n');
        $ano       = (int) $ciclo->data_inicio->format('Y');

        $metas = DB::table('gdp_metas_individuais')
            ->where('ciclo_id', $ciclo->id)
            ->where('user_id', $userId)
            ->get()
            ->keyBy(fn($m) => $m->indicador_id . '_' . $m->mes);

        $acordoAceito = DB::table('gdp_snapshots')
            ->where('ciclo_id', $ciclo->id)
            ->where('user_id', $userId)
            ->where('congelado', true)
            ->exists();

        return view('gdp.acordo-visualizar', compact(
            'ciclo', 'targetUser', 'eixos', 'mesInicio', 'mesFim', 'ano', 'metas', 'acordoAceito', 'user'
        ));
    }

    public function aceitarAcordo(Request $request, int $userId)
    {
        $user = Auth::user();
        if ($user->id !== $userId) {
            return response()->json(['erro' => 'Apenas o proprio advogado pode aceitar'], 403);
        }

        $ciclo = GdpCiclo::ativo();
        if (!$ciclo) {
            return response()->json(['erro' => 'Nenhum ciclo ativo'], 400);
        }

        $totalMetas = DB::table('gdp_metas_individuais')
            ->where('ciclo_id', $ciclo->id)
            ->where('user_id', $userId)
            ->where('valor_meta', '>', 0)
            ->count();

        if ($totalMetas === 0) {
            return response()->json(['erro' => 'Nenhuma meta definida'], 400);
        }

        $metasSnapshot = DB::table('gdp_metas_individuais')
            ->where('ciclo_id', $ciclo->id)
            ->where('user_id', $userId)
            ->orderBy('indicador_id')
            ->orderBy('mes')
            ->get(['indicador_id', 'mes', 'ano', 'valor_meta'])
            ->toJson();

        $hash = hash('sha256', $metasSnapshot);
        $now  = now();

        $mesInicio = (int) $ciclo->data_inicio->format('n');
        $ano       = (int) $ciclo->data_inicio->format('Y');

        DB::table('gdp_snapshots')->updateOrInsert(
            [
                'ciclo_id' => $ciclo->id,
                'user_id'  => $userId,
                'mes'      => $mesInicio,
                'ano'      => $ano,
            ],
            [
                'congelado'     => true,
                'congelado_por' => $userId,
                'congelado_em'  => $now,
                'updated_at'    => $now,
            ]
        );

        DB::table('gdp_audit_log')->insert([
            'user_id'        => $userId,
            'entidade'       => 'gdp_acordo',
            'entidade_id'    => $ciclo->id,
            'campo'          => 'aceite',
            'valor_anterior' => null,
            'valor_novo'     => json_encode(['hash' => $hash, 'metas' => $totalMetas]),
            'justificativa'  => 'Acordo aceito pelo advogado',
            'ip'             => $request->ip(),
            'created_at'     => $now,
        ]);

        return response()->json(['sucesso' => true, 'hash' => $hash]);
    }

    public function acordoPrint(Request $request, int $userId)
    {
        $user = Auth::user();
        if (!in_array($user->role, ['admin', 'socio', 'coordenador']) && $user->id !== $userId) {
            abort(403);
        }

        $ciclo = GdpCiclo::ativo();
        if (!$ciclo) { abort(404, 'Nenhum ciclo ativo'); }

        $targetUser = \App\Models\User::findOrFail($userId);

        $eixos = GdpEixo::where('ciclo_id', $ciclo->id)
            ->with(['indicadores' => fn($q) => $q->where('ativo', true)->where('status_v1', 'score')->orderBy('ordem')])
            ->orderBy('ordem')
            ->get();

        $mesInicio = (int) $ciclo->data_inicio->format('n');
        $mesFim    = (int) $ciclo->data_fim->format('n');
        $ano       = (int) $ciclo->data_inicio->format('Y');

        $metas = DB::table('gdp_metas_individuais')
            ->where('ciclo_id', $ciclo->id)
            ->where('user_id', $userId)
            ->get()
            ->keyBy(fn($m) => $m->indicador_id . '_' . $m->mes);

        $metasJson = DB::table('gdp_metas_individuais')
            ->where('ciclo_id', $ciclo->id)
            ->where('user_id', $userId)
            ->orderBy('indicador_id')->orderBy('mes')
            ->get(['indicador_id', 'mes', 'ano', 'valor_meta'])
            ->toJson();
        $hash = hash('sha256', $metasJson);

        $acordoAceito = DB::table('gdp_snapshots')
            ->where('ciclo_id', $ciclo->id)
            ->where('user_id', $userId)
            ->where('congelado', true)
            ->first();

        $mesesNomes = [1=>'Janeiro',2=>'Fevereiro',3=>'Março',4=>'Abril',5=>'Maio',6=>'Junho',
                       7=>'Julho',8=>'Agosto',9=>'Setembro',10=>'Outubro',11=>'Novembro',12=>'Dezembro'];

        return view('gdp.acordo-print', compact(
            'ciclo','targetUser','eixos','mesInicio','mesFim','ano',
            'metas','hash','acordoAceito','mesesNomes'
        ));
    }


    // =========================================================================
    // PENALIZACOES
    // =========================================================================

    public function penalizacoes(Request $request)
    {
        $user = Auth::user();
        $mes  = (int) $request->input('month', now()->month);
        $ano  = (int) $request->input('year', now()->year);
        $ciclo = GdpCiclo::where('status', 'aberto')->first();

        $query = GdpPenalizacao::with(['tipo', 'usuario'])
            ->where('mes', $mes)->where('ano', $ano);

        if ($user->role === 'socio') {
            $query->where('user_id', $user->id);
        }
        if (in_array($user->role, ['admin','coordenador']) && $request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }
        if ($request->filled('eixo_id')) {
            $eixoId = $request->input('eixo_id');
            $query->whereHas('tipo', fn($q) => $q->where('eixo_id', $eixoId));
        }
        if ($request->filled('gravidade')) {
            $grav = $request->input('gravidade');
            $query->whereHas('tipo', fn($q) => $q->where('gravidade', $grav));
        }
        if ($request->filled('contestacao')) {
            $cont = $request->input('contestacao');
            if ($cont === 'nenhuma') { $query->where('contestada', false); }
            else { $query->where('contestacao_status', $cont); }
        }

        $penalizacoes = $query->orderByDesc('created_at')->get();
        $usuarios = in_array($user->role, ['admin','coordenador'])
            ? User::whereIn('id', [1,3,7,8])->orderBy('name')->get() : collect();
        $eixos = GdpEixo::orderBy('id')->get();
        $tipos = GdpPenalizacaoTipo::where('ativo', true)->orderBy('codigo')->get();

        return view('gdp.penalizacoes', compact('user','mes','ano','ciclo','penalizacoes','usuarios','eixos','tipos'));
    }

    public function criarManual(Request $request)
    {
        $user = Auth::user();
        if (!in_array($user->role, ['admin','coordenador'])) {
            return response()->json(['erro' => 'Sem permissao'], 403);
        }
        $tipo = GdpPenalizacaoTipo::find($request->input('tipo_id'));
        $ciclo = GdpCiclo::where('status', 'aberto')->first();

        GdpPenalizacao::create([
            'ciclo_id' => $ciclo->id, 'user_id' => $request->input('user_id'),
            'tipo_id' => $tipo->id, 'mes' => $request->input('mes', now()->month),
            'ano' => $request->input('ano', now()->year), 'pontos_desconto' => $tipo->pontos_desconto,
            'descricao_automatica' => $request->input('descricao'), 'automatica' => false,
        ]);
        return response()->json(['ok' => true]);
    }

    public function detalhePenalizacao(Request $request, int $id)
    {
        $pen = GdpPenalizacao::with(['tipo', 'usuario'])->findOrFail($id);
        $user = Auth::user();
        if ($user->role === 'socio' && $pen->user_id !== $user->id) {
            return response()->json(['erro' => 'Sem permissao'], 403);
        }
        return response()->json([
            'codigo' => $pen->tipo->codigo ?? null, 'tipo' => $pen->tipo->nome ?? 'Manual',
            'usuario' => $pen->usuario->name ?? null, 'gravidade' => $pen->tipo->gravidade ?? null,
            'pontos' => $pen->pontos_desconto, 'origem' => $pen->automatica ? 'Automatica' : 'Manual',
            'descricao' => $pen->descricao_automatica,
            'contestacao_texto' => $pen->contestacao_texto, 'contestacao_status' => $pen->contestacao_status,
        ]);
    }

    public function avaliarContestacao(Request $request, int $id)
    {
        $user = Auth::user();
        if (!in_array($user->role, ['admin','coordenador'])) {
            return response()->json(['erro' => 'Sem permissao'], 403);
        }
        $pen = GdpPenalizacao::findOrFail($id);
        if ($pen->contestacao_status !== 'pendente') {
            return response()->json(['erro' => 'Ja avaliada'], 422);
        }
        $decisao = $request->input('decisao');
        if (!in_array($decisao, ['aceita','rejeitada'])) {
            return response()->json(['erro' => 'Decisao invalida'], 422);
        }
        $pen->update(['contestacao_status' => $decisao, 'contestacao_por' => $user->id, 'contestacao_em' => now()]);
        return response()->json(['ok' => true, 'status' => $decisao]);
    }

    public function contestarPenalizacao(Request $request, int $id)
    {
        $user = Auth::user();
        $pen = GdpPenalizacao::findOrFail($id);
        if ($pen->user_id !== $user->id) return response()->json(['erro' => 'Sem permissao'], 403);
        if ($pen->contestada) return response()->json(['erro' => 'Ja contestada'], 422);
        $texto = $request->input('texto');
        $pen->update(['contestada' => true, 'contestacao_texto' => $texto, 'contestacao_status' => 'pendente']);
        return response()->json(['ok' => true]);
    }

    public function executarScanner(Request $request)
    {
        $user = Auth::user();
        if (!in_array($user->role, ['admin','coordenador'])) {
            return response()->json(['erro' => 'Sem permissao'], 403);
        }
        $mes = (int) $request->input('mes', now()->month);
        $ano = (int) $request->input('ano', now()->year);
        $ciclo = GdpCiclo::where('status', 'aberto')->first();

        $scanner = new GdpPenalizacaoScanner($ciclo, $mes, $ano);
        $novas = 0;
        foreach ([1,3,7,8] as $uid) {
            $result = $scanner->scanUsuario($uid);
            $novas += $result['novas'] ?? 0;
        }
        return response()->json(['ok' => true, 'novas' => $novas]);
    }

}
