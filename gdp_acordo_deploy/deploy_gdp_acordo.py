#!/usr/bin/env python3
"""
GDP Fase 5 — Acordo de Desempenho
Deploy cirúrgico: rotas, controller, 3 views, seeder
Executar: cd ~/domains/mayeradvogados.adv.br/public_html/Intranet && python3 deploy_gdp_acordo.py
"""

import os, sys, shutil

BASE = os.path.dirname(os.path.abspath(__file__))
if not os.path.exists(os.path.join(BASE, "artisan")):
    BASE = os.path.expanduser("~/domains/mayeradvogados.adv.br/public_html/Intranet")
if not os.path.exists(os.path.join(BASE, "artisan")):
    print("ERRO FATAL: nao encontrou diretorio Laravel"); sys.exit(1)

def read(rel):
    with open(os.path.join(BASE, rel), 'r', encoding='utf-8') as f: return f.read()

def write(rel, content):
    with open(os.path.join(BASE, rel), 'w', encoding='utf-8') as f: f.write(content)

def backup(rel):
    full = os.path.join(BASE, rel)
    bak = full + '.bak_acordo'
    if not os.path.exists(bak):
        shutil.copy2(full, bak)
        print(f"  BACKUP: {rel}.bak_acordo")

errors = []

# ============================================================================
# PASSO 1 — ROTAS (patch _gdp_routes.php)
# ============================================================================
print("\n[1/3] Patch rotas GDP...")
rf = "routes/_gdp_routes.php"
backup(rf)
routes = read(rf)

if "acordo" in routes:
    print("  SKIP: rotas de acordo ja existem")
else:
    # Marker: a última linha "});" fecha o grupo Route::prefix('gdp')
    marker = "    Route::get('/dados/{userId}', [GdpController::class, 'dadosUsuario'])->name('dados-usuario');\n});"
    new_block = """    Route::get('/dados/{userId}', [GdpController::class, 'dadosUsuario'])->name('dados-usuario');

    // ── Acordo de Desempenho ──
    Route::get('/acordo', [GdpController::class, 'acordo'])->name('acordo');
    Route::post('/acordo', [GdpController::class, 'salvarAcordo'])->name('acordo.salvar');
    Route::get('/acordo/{userId}/visualizar', [GdpController::class, 'visualizarAcordo'])->name('acordo.visualizar');
    Route::post('/acordo/{userId}/aceitar', [GdpController::class, 'aceitarAcordo'])->name('acordo.aceitar');
    Route::get('/acordo/{userId}/print', [GdpController::class, 'acordoPrint'])->name('acordo.print');
});"""
    if marker in routes:
        routes = routes.replace(marker, new_block)
        write(rf, routes)
        print("  OK: 5 rotas adicionadas")
    else:
        errors.append("Marker de rotas nao encontrado — verificar _gdp_routes.php")
        print("  ERRO: marker nao encontrado")

# ============================================================================
# PASSO 2 — CONTROLLER (patch GdpController.php)
# ============================================================================
print("\n[2/3] Patch GdpController...")
cf = "app/Http/Controllers/GdpController.php"
backup(cf)
ctrl = read(cf)

if "function acordo(" in ctrl:
    print("  SKIP: metodos de acordo ja existem")
else:
    # Adicionar imports se necessários
    if "use Illuminate\\Support\\Facades\\DB;" not in ctrl:
        ctrl = ctrl.replace(
            "use Illuminate\\Http\\Request;",
            "use Illuminate\\Http\\Request;\nuse Illuminate\\Support\\Facades\\DB;"
        )

    if "use App\\Models\\User;" not in ctrl:
        ctrl = ctrl.replace(
            "use Illuminate\\Http\\Request;",
            "use Illuminate\\Http\\Request;\nuse App\\Models\\User;"
        )

    # Marker: último } do arquivo (fechamento da classe)
    # Inserir métodos antes dele
    marker = """        return response()->json(['historico' => $historico]);
    }
}"""
    replacement = r"""        return response()->json(['historico' => $historico]);
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

        $usuarios = \App\Models\User::whereNotIn('id', [2, 5, 6])
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
}"""

    if marker in ctrl:
        ctrl = ctrl.replace(marker, replacement)
        write(cf, ctrl)
        print("  OK: 5 metodos adicionados ao GdpController")
    else:
        errors.append("Marker de controller nao encontrado")
        print("  ERRO: marker nao encontrado")

# ============================================================================
# PASSO 3 — VIEWS
# ============================================================================
print("\n[3/3] Criando views...")

# Garantir diretório
views_dir = os.path.join(BASE, "resources/views/gdp")
os.makedirs(views_dir, exist_ok=True)

# --- acordo.blade.php ---
# --- acordo-visualizar.blade.php ---
# --- acordo-print.blade.php ---
# (criados pelos arquivos separados no tar)

print("  Views serao criadas pelos arquivos .blade.php do pacote")

# ============================================================================
print("\n" + "=" * 60)
if errors:
    print("ERROS ENCONTRADOS:")
    for e in errors: print(f"  X {e}")
    sys.exit(1)
else:
    print("DEPLOY GDP ACORDO — PATCHES APLICADOS COM SUCESSO")
print("=" * 60)
print("\nProximos passos:")
print("  php artisan config:clear && php artisan route:clear && php artisan view:clear && php artisan cache:clear")
print("  Testar: /gdp/acordo")
