#!/usr/bin/env python3
"""
ATIVACAO DO SISTEMA DE SEGURANCA - Intranet Mayer Advogados
Compativel com Python 3.6+
"""

import subprocess
import sys
import os

BASE = os.path.expanduser("~/domains/mayeradvogados.adv.br/public_html/Intranet")
os.chdir(BASE)

erros = []

def run(cmd, desc=""):
    print("  -> %s" % (desc if desc else cmd[:80]))
    result = subprocess.run(cmd, shell=True, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
    stdout = result.stdout.decode('utf-8', errors='replace').strip()
    stderr = result.stderr.decode('utf-8', errors='replace').strip()
    if result.returncode != 0 and stderr:
        print("    ! STDERR: %s" % stderr[:200])
    return stdout

def patch_file(filepath, old_str, new_str, desc=""):
    fullpath = os.path.join(BASE, filepath)
    if not os.path.exists(fullpath):
        msg = "ARQUIVO NAO ENCONTRADO: %s" % filepath
        print("    x %s" % msg)
        erros.append(msg)
        return False

    with open(fullpath, 'r') as f:
        content = f.read()

    if old_str not in content:
        if new_str in content:
            print("    v Patch ja aplicado: %s" % desc)
            return True
        msg = "STRING NAO ENCONTRADA em %s: %s" % (filepath, desc)
        print("    x %s" % msg)
        erros.append(msg)
        return False

    content = content.replace(old_str, new_str, 1)
    with open(fullpath, 'w') as f:
        f.write(content)
    print("    v %s" % desc)
    return True


# ============================================================================
# FASE 1 - LIMPEZA DO BANCO DE DADOS
# ============================================================================
print("\n" + "="*70)
print("FASE 1 - LIMPEZA DO BANCO DE DADOS")
print("="*70)

print("\n[1.1] Removendo modulos duplicados (sem prefixo de grupo)...")
run(
    'php artisan tinker --execute="'
    "\\DB::table('modulos')->whereIn('slug', ["
    "'configuracoes','integracoes','sincronizacao','usuarios',"
    "'visao-gerencial','clientes-mercado','metas-kpi',"
    "'minha-performance','equipe'"
    "])->delete(); echo 'OK';"
    '"',
    "Removendo modulos sem prefixo de grupo"
)

print("\n[1.2] Corrigindo roles invalidos...")
run(
    'php artisan tinker --execute="'
    "\\App\\Models\\User::where('role','advogado')->update(['role'=>'socio']); echo 'OK';"
    '"',
    "Role advogado -> socio"
)

print("\n[1.3] Corrigindo usuarios sem flag ativo...")
run(
    'php artisan tinker --execute="'
    "\\App\\Models\\User::whereNull('ativo')->update(['ativo'=>true]); echo 'OK';"
    '"',
    "ativo=null -> ativo=true"
)

print("\n[1.4] Verificando estado apos limpeza...")
count = run('php artisan tinker --execute="echo \\App\\Models\\Modulo::count();"')
print("    Modulos restantes: %s" % count)


# ============================================================================
# FASE 2 - CADASTRAR MODULOS FALTANTES
# ============================================================================
print("\n" + "="*70)
print("FASE 2 - CADASTRAR MODULOS FALTANTES")
print("="*70)

modulos = [
    ("resultados.visao-gerencial", "Visao Gerencial", "RESULTADOS", "Dashboard Financeiro Executivo", "visao-gerencial", "chart-bar", 1),
    ("resultados.clientes-mercado", "Clientes e Mercado", "RESULTADOS", "Dashboard BSC Clientes e Mercado", "clientes-mercado", "users", 2),
    ("resultados.processos-internos", "Processos Internos", "RESULTADOS", "Dashboard BSC Processos Internos", "resultados.bsc.processos-internos.index", "cog", 3),
    ("resultados.metas-kpi", "Metas KPI", "RESULTADOS", "Metas KPI mensais", "admin.metas-kpi-mensais", "target", 4),
    ("operacional.leads", "Central de Leads", "OPERACIONAL", "Central de Leads Marketing", "leads.index", "megaphone", 1),
    ("operacional.nexo", "NEXO Atendimento", "OPERACIONAL", "Atendimento WhatsApp", "nexo.atendimento", "chat-bubble-left-right", 2),
    ("operacional.nexo-gerencial", "NEXO Gerencial", "OPERACIONAL", "Dashboard Gerencial NEXO", "nexo.gerencial", "presentation-chart-bar", 3),
    ("operacional.siric", "SIRIC", "OPERACIONAL", "Analise de Credito IA", "siric.index", "shield-check", 4),
    ("operacional.precificacao", "SIPEX Precificacao", "OPERACIONAL", "Precificacao Inteligente", "precificacao.index", "currency-dollar", 5),
    ("operacional.manuais", "Manuais Normativos", "OPERACIONAL", "Documentos normativos", "manuais-normativos.index", "book-open", 6),
    ("avisos.listar", "Quadro de Avisos", "COMUNICACAO", "Visualizar avisos internos", "avisos.index", "bell", 1),
    ("avisos.gerenciar", "Gerenciar Avisos", "COMUNICACAO", "Criar e editar avisos", "admin.avisos.index", "bell-alert", 2),
    ("gdp.minha-performance", "Minha Performance", "GDP", "Performance individual", "minha-performance", "user", 1),
    ("gdp.equipe", "Performance da Equipe", "GDP", "Performance da equipe", "equipe", "user-group", 2),
    ("gdp.metas", "Metas GDP", "GDP", "Editar metas de performance", "configurar-metas", "adjustments-horizontal", 3),
    ("admin.usuarios", "Usuarios", "ADMINISTRACAO", "Gestao de usuarios", "admin.usuarios.index", "users", 1),
    ("admin.sincronizacao", "Sincronizacao", "ADMINISTRACAO", "Sincronizacao DataJuri", "admin.sincronizacao-unificada.index", "arrow-path", 2),
    ("admin.integracoes", "Integracoes", "ADMINISTRACAO", "Status das integracoes", "integration.index", "puzzle-piece", 3),
    ("admin.configuracoes", "Configuracoes", "ADMINISTRACAO", "Configuracoes do sistema", "configuracoes", "cog-6-tooth", 4),
    ("admin.classificacao", "Classificacao", "ADMINISTRACAO", "Regras de classificacao financeira", "admin.classificacao.index", "tag", 5),
]

print("\n[2.1] Cadastrando/atualizando %d modulos..." % len(modulos))

for slug, nome, grupo, descricao, rota, icone, ordem in modulos:
    php = (
        "\\App\\Models\\Modulo::updateOrCreate("
        "['slug'=>'%s'],"
        "['nome'=>'%s','grupo'=>'%s','descricao'=>'%s',"
        "'rota'=>'%s','icone'=>'%s','ordem'=>%d,'ativo'=>true]"
        "); echo 'OK';"
    ) % (slug, nome, grupo, descricao, rota, icone, ordem)
    run("php artisan tinker --execute=\"%s\"" % php, "  %s > %s" % (grupo, nome))

count = run('php artisan tinker --execute="echo \\App\\Models\\Modulo::count();"')
print("\n    Total de modulos apos cadastro: %s" % count)


# ============================================================================
# FASE 3 - APLICAR MIDDLEWARES NAS ROTAS
# ============================================================================
print("\n" + "="*70)
print("FASE 3 - APLICAR MIDDLEWARES NAS ROTAS")
print("="*70)

# 3.1 _leads_routes.php
print("\n[3.1] Patch: _leads_routes.php...")
patch_file(
    "routes/_leads_routes.php",
    "// Central de Leads - Dashboard Marketing Jur\xc3\xaddico\nRoute::get('/leads', [LeadController::class, 'index'])->name('leads.index');",
    "// Central de Leads - Dashboard Marketing Jur\xc3\xaddico\nRoute::middleware(['auth', 'user.active', 'modulo:operacional.leads,visualizar'])->group(function () {\nRoute::get('/leads', [LeadController::class, 'index'])->name('leads.index');",
    "Abrindo grupo com middleware operacional.leads"
)

patch_file(
    "routes/_leads_routes.php",
    'Route::get("/leads/export", [\\App\\Http\\Controllers\\LeadController::class, "export"])->name("leads.export");',
    'Route::get("/leads/export", [\\App\\Http\\Controllers\\LeadController::class, "export"])->name("leads.export");\n}); // fim grupo operacional.leads',
    "Fechando grupo operacional.leads"
)

# 3.2 _nexo_routes.php
print("\n[3.2] Patch: _nexo_routes.php...")
patch_file(
    "routes/_nexo_routes.php",
    "Route::middleware(['auth'])->group(function () {\n    Route::prefix('nexo/atendimento')->group(function () {",
    "Route::middleware(['auth', 'user.active'])->group(function () {\n    Route::prefix('nexo/atendimento')->middleware('modulo:operacional.nexo,visualizar')->group(function () {",
    "Adicionando middleware operacional.nexo"
)

patch_file(
    "routes/_nexo_routes.php",
    "    Route::prefix('nexo/gerencial')->group(function () {",
    "    Route::prefix('nexo/gerencial')->middleware('modulo:operacional.nexo-gerencial,visualizar')->group(function () {",
    "Adicionando middleware operacional.nexo-gerencial"
)

# 3.3 _siric_routes.php
print("\n[3.3] Patch: _siric_routes.php...")
patch_file(
    "routes/_siric_routes.php",
    "Route::middleware('auth')->prefix('siric')->name('siric.')->group(function () {",
    "Route::middleware(['auth', 'user.active', 'modulo:operacional.siric,visualizar'])->prefix('siric')->name('siric.')->group(function () {",
    "Adicionando middleware operacional.siric"
)

# 3.4 _precificacao_routes.php
print("\n[3.4] Patch: _precificacao_routes.php...")
patch_file(
    "routes/_precificacao_routes.php",
    "Route::prefix('precificacao')->group(function () {",
    "Route::middleware(['auth', 'user.active', 'modulo:operacional.precificacao,visualizar'])->prefix('precificacao')->group(function () {",
    "Adicionando middleware operacional.precificacao"
)

# 3.5 _manuais_routes.php
print("\n[3.5] Patch: _manuais_routes.php...")
patch_file(
    "routes/_manuais_routes.php",
    "Route::middleware(['auth'])->group(function () {\n    Route::get('/manuais-normativos',",
    "Route::middleware(['auth', 'user.active', 'modulo:operacional.manuais,visualizar'])->group(function () {\n    Route::get('/manuais-normativos',",
    "Adicionando middleware operacional.manuais"
)

patch_file(
    "routes/_manuais_routes.php",
    "Route::middleware(['auth'])->prefix('admin/manuais-normativos')->group(function () {",
    "Route::middleware(['auth', 'user.active', 'admin'])->prefix('admin/manuais-normativos')->group(function () {",
    "Adicionando middleware admin no CRUD de manuais"
)

# 3.6 _processos_internos_routes.php
print("\n[3.6] Patch: _processos_internos_routes.php...")
patch_file(
    "routes/_processos_internos_routes.php",
    "Route::prefix('resultados/bsc/processos-internos')\n    ->name('resultados.bsc.processos-internos.')\n    ->group(function () {",
    "Route::prefix('resultados/bsc/processos-internos')\n    ->name('resultados.bsc.processos-internos.')\n    ->middleware(['auth', 'user.active', 'modulo:resultados.processos-internos,visualizar'])\n    ->group(function () {",
    "Adicionando middleware resultados.processos-internos"
)

# 3.7 web.php - blocos principais
print("\n[3.7] Patch: web.php...")

# Visao Gerencial
patch_file(
    "routes/web.php",
    'Route::get("/visao-gerencial", [DashboardController::class, "visaoGerencial"])->name("visao-gerencial");',
    'Route::get("/visao-gerencial", [DashboardController::class, "visaoGerencial"])->name("visao-gerencial")->middleware("modulo:resultados.visao-gerencial,visualizar");',
    "Middleware na Visao Gerencial"
)

# Configurar Metas
patch_file(
    "routes/web.php",
    'Route::get("/configurar-metas", [DashboardController::class, "configurarMetas"])->name("configurar-metas");',
    'Route::get("/configurar-metas", [DashboardController::class, "configurarMetas"])->name("configurar-metas")->middleware("admin");',
    "Middleware admin em Configurar Metas"
)

# Clientes & Mercado
patch_file(
    "routes/web.php",
    'Route::get("/clientes-mercado", [App\\Http\\Controllers\\ClientesMercadoController::class, "index"])->name("clientes-mercado");',
    'Route::get("/clientes-mercado", [App\\Http\\Controllers\\ClientesMercadoController::class, "index"])->name("clientes-mercado")->middleware("modulo:resultados.clientes-mercado,visualizar");',
    "Middleware em Clientes & Mercado"
)

# Integracoes (admin only)
patch_file(
    "routes/web.php",
    '// Integra\xc3\xa7\xc3\xb5es (Admin only)\nRoute::middleware(["auth"])->group(function () {',
    '// Integra\xc3\xa7\xc3\xb5es (Admin only)\nRoute::middleware(["auth", "admin"])->group(function () {',
    "Middleware admin em Integracoes"
)

# Classificacao de Regras (admin only)
patch_file(
    "routes/web.php",
    "Route::prefix('admin')->name('admin.')->middleware('auth')->group(function () {\n\n    // Rotas de CRUD\n    Route::resource('classificacao-regras',",
    "Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin'])->group(function () {\n\n    // Rotas de CRUD\n    Route::resource('classificacao-regras',",
    "Middleware admin em Classificacao de Regras"
)

# 3.8 sincronizacao-unificada.php
print("\n[3.8] Patch: sincronizacao-unificada.php...")
sync_path = os.path.join(BASE, "routes/sincronizacao-unificada.php")
if os.path.exists(sync_path):
    with open(sync_path, 'r') as f:
        sc = f.read()
    if "middleware('auth')" in sc and "'admin'" not in sc:
        patch_file(
            "routes/sincronizacao-unificada.php",
            "middleware('auth')",
            "middleware(['auth', 'admin'])",
            "Adicionando middleware admin na sincronizacao unificada"
        )
    elif "middleware(['auth'])" in sc and "'admin'" not in sc:
        patch_file(
            "routes/sincronizacao-unificada.php",
            "middleware(['auth'])",
            "middleware(['auth', 'admin'])",
            "Adicionando middleware admin na sincronizacao unificada (v2)"
        )
    else:
        print("    v Sincronizacao unificada ja tem protecao ou formato diferente")
else:
    print("    ! Arquivo sincronizacao-unificada.php nao encontrado")


# ============================================================================
# FASE 4 - PERMISSOES PADRAO
# ============================================================================
print("\n" + "="*70)
print("FASE 4 - PERMISSOES PADRAO POR ROLE")
print("="*70)

print("\n[4.1] Aplicando permissoes padrao para todos os usuarios...")
php_perm = (
    "$s = app(\\App\\Services\\PermissaoService::class); "
    "$us = \\App\\Models\\User::all(); "
    "foreach($us as $u){ "
    "$s->aplicarPermissoesPadrao($u); "
    "echo $u->name.' ('.$u->role.') => OK'.PHP_EOL; "
    "}"
)
result = run('php artisan tinker --execute="%s"' % php_perm, "Executando aplicarPermissoesPadrao()")
if result:
    for line in result.split("\n"):
        print("    %s" % line)


# ============================================================================
# FASE 5 - LIMPEZA DE CACHE
# ============================================================================
print("\n" + "="*70)
print("FASE 5 - LIMPEZA DE CACHE")
print("="*70)

run("php artisan config:clear", "config:clear")
run("php artisan route:clear", "route:clear")
run("php artisan view:clear", "view:clear")
run("php artisan cache:clear", "cache:clear")


# ============================================================================
# RELATORIO FINAL
# ============================================================================
print("\n" + "="*70)
print("RELATORIO FINAL")
print("="*70)

print("\n[v] Modulos cadastrados:")
r = run('php artisan tinker --execute="\\App\\Models\\Modulo::select(\'grupo\',\'slug\',\'nome\')->orderBy(\'grupo\')->orderBy(\'ordem\')->get()->each(function($m){echo $m->grupo.\' | \'.$m->slug.\' | \'.$m->nome.PHP_EOL;});"')
if r:
    for line in r.split("\n"):
        if "|" in line:
            print("    %s" % line)

print("\n[v] Usuarios e roles:")
r = run('php artisan tinker --execute="\\App\\Models\\User::all()->each(function($u){echo $u->id.\' | \'.$u->name.\' | \'.$u->role.\' | ativo=\'.$u->ativo.PHP_EOL;});"')
if r:
    for line in r.split("\n"):
        if "|" in line:
            print("    %s" % line)

print("\n[v] Permissoes atribuidas:")
r = run('php artisan tinker --execute="echo \\App\\Models\\UserPermission::count().\' permissoes totais\';"')
print("    %s" % r)

# Verificar rotas
print("\n[v] Verificando rotas (middleware modulo):")
r = run("php artisan route:list 2>&1 | head -5")
if "ERROR" in r or "Error" in r:
    print("    !!! ERRO NAS ROTAS: %s" % r[:300])
    erros.append("Erro ao listar rotas: %s" % r[:200])
else:
    print("    Rotas compilam sem erros")

if erros:
    print("\n!!! ERROS ENCONTRADOS (%d):" % len(erros))
    for e in erros:
        print("    x %s" % e)
else:
    print("\n==> SISTEMA DE SEGURANCA ATIVADO COM SUCESSO!")

print("\n" + "="*70)
print("Proximos passos:")
print("  1. Testar acesso como admin (Rafael) - deve acessar tudo")
print("  2. Testar acesso como coordenador (Patricia) - conforme permissoes")
print("  3. git add -A && git commit -m 'Ativar sistema seguranca' && git push")
print("="*70 + "\n")
