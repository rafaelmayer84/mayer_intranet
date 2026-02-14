#!/usr/bin/env python3
"""
FASE 3 - Limpar referências ESPO em controllers, services e views existentes
Patches cirúrgicos - não reescreve arquivos inteiros
"""
import os, re, shutil

BASE = os.path.expanduser('~/domains/mayeradvogados.adv.br/public_html/Intranet')
os.chdir(BASE)
patches_ok = 0
patches_fail = 0

def backup(fp):
    bak = fp + '.bak_pre_espo_removal'
    if not os.path.exists(bak):
        shutil.copy2(fp, bak)

def patch(fp, old, new, label):
    global patches_ok, patches_fail
    if not os.path.exists(fp):
        print(f'  [SKIP] {fp} não existe')
        return False
    with open(fp, 'r') as f:
        content = f.read()
    if old not in content:
        print(f'  [SKIP] {label} - pattern não encontrado')
        patches_fail += 1
        return False
    backup(fp)
    content = content.replace(old, new)
    with open(fp, 'w') as f:
        f.write(content)
    print(f'  [OK] {label}')
    patches_ok += 1
    return True

def remove_lines_containing(fp, search_terms, label):
    """Remove linhas que contêm qualquer dos termos de busca"""
    global patches_ok
    if not os.path.exists(fp):
        print(f'  [SKIP] {fp} não existe')
        return
    backup(fp)
    with open(fp, 'r') as f:
        lines = f.readlines()
    
    original_count = len(lines)
    new_lines = []
    removed = 0
    for line in lines:
        if any(term in line for term in search_terms):
            removed += 1
        else:
            new_lines.append(line)
    
    if removed > 0:
        with open(fp, 'w') as f:
            f.writelines(new_lines)
        print(f'  [OK] {label} - {removed} linhas removidas')
        patches_ok += 1
    else:
        print(f'  [SKIP] {label} - nenhuma linha encontrada')

print('='*60)
print('FASE 3 - LIMPAR REFERÊNCIAS ESPO EM ARQUIVOS EXISTENTES')
print('='*60)

# ═══════════════════════════════════════════════════════════
# 3.1 IntegrationOrchestrator - remover import e métodos ESPO
# ═══════════════════════════════════════════════════════════
print('\n[3.1] IntegrationOrchestrator.php...')
fp = 'app/Services/Orchestration/IntegrationOrchestrator.php'
if os.path.exists(fp):
    backup(fp)
    with open(fp, 'r') as f:
        content = f.read()
    
    # Remover import do EspoCrmService
    content = content.replace(
        'use App\\Services\\Integration\\{DataJuriService, EspoCrmService};',
        'use App\\Services\\Integration\\DataJuriService;'
    )
    
    # Remover parâmetro do construtor
    content = content.replace(
        'protected EspoCrmService $espo,',
        '// EspoCrmService removido em 13/02/2026'
    )
    
    # Neutralizar chamadas $this->espo → retornar vazio
    # Substituir chamadas a $this->espo por array vazio
    content = re.sub(
        r'\$this->espo->getAllEntities\([^)]*\)',
        '[] /* ESPO removido */',
        content
    )
    content = re.sub(
        r'\$this->espo->',
        '/* espo removido */ null && false && ',
        content
    )
    
    with open(fp, 'w') as f:
        f.write(content)
    print('  [OK] IntegrationOrchestrator - ESPO neutralizado')
    patches_ok += 1

# ═══════════════════════════════════════════════════════════
# 3.2 SincronizacaoUnificadaController - remover métodos e import ESPO
# ═══════════════════════════════════════════════════════════
print('\n[3.2] SincronizacaoUnificadaController.php...')
fp = 'app/Http/Controllers/Admin/SincronizacaoUnificadaController.php'
if os.path.exists(fp):
    backup(fp)
    with open(fp, 'r') as f:
        content = f.read()
    
    # Remover use statement
    content = content.replace(
        'use App\\Services\\EspoCrmService;\n',
        '// EspoCrmService removido em 13/02/2026\n'
    )
    
    # Neutralizar variáveis ESPO na view (dentro do método index)
    content = content.replace(
        "        // Estatisticas ESPOCRM",
        "        // Estatisticas ESPOCRM (removido 13/02/2026)"
    )
    content = content.replace(
        "        $lastSyncEspocrm = 'N/A';",
        "        $lastSyncEspocrm = 'Desativado - CRM Nativo';"
    )
    
    # Neutralizar métodos espocrmTest e espocrmSync
    # Substituir o corpo dos métodos para retornar mensagem de desativação
    
    # espocrmTest
    old_test = """    public function espocrmTest()
    {
        try {
            $service = app(EspoCrmService::class);
            $result = $service->testConnection();

            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'] ?? 'Sem resposta',
            ]);
        } catch (\\Exception $e) {
            return response()->json(["""
    
    new_test = """    public function espocrmTest()
    {
        return response()->json([
            'success' => false,
            'message' => 'ESPO CRM desativado em 13/02/2026 - substituído por CRM Nativo',
        ]);
        /* ESPO REMOVIDO - código original abaixo
        try {
            $service = app(EspoCrmService::class);
            $result = $service->testConnection();

            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'] ?? 'Sem resposta',
            ]);
        } catch (\\Exception $e) {
            return response()->json(["""
    
    if old_test in content:
        content = content.replace(old_test, new_test)
        print('  [OK] espocrmTest neutralizado')
    else:
        print('  [WARN] espocrmTest - pattern não encontrado, tentando alternativa...')
        # Tentar substituição mais simples
        if 'public function espocrmTest()' in content:
            content = re.sub(
                r'(public function espocrmTest\(\)\s*\{)',
                r'''\1
        return response()->json(['success' => false, 'message' => 'ESPO CRM desativado - CRM Nativo']);
        if(false){''',
                content,
                count=1
            )
            print('  [OK] espocrmTest neutralizado (via regex)')
    
    # espocrmSync - mesma abordagem
    if 'public function espocrmSync()' in content:
        content = re.sub(
            r'(public function espocrmSync\(\)\s*\{)',
            r'''\1
        return response()->json(['success' => false, 'message' => 'ESPO CRM desativado - CRM Nativo']);
        if(false){''',
            content,
            count=1
        )
        print('  [OK] espocrmSync neutralizado')
    
    with open(fp, 'w') as f:
        f.write(content)
    patches_ok += 1

# ═══════════════════════════════════════════════════════════
# 3.3 SincronizacaoController - remover import e referências
# ═══════════════════════════════════════════════════════════
print('\n[3.3] SincronizacaoController.php...')
fp = 'app/Http/Controllers/Admin/SincronizacaoController.php'
if os.path.exists(fp):
    backup(fp)
    with open(fp, 'r') as f:
        content = f.read()
    
    content = content.replace(
        'use App\\Services\\EspoCrmSyncService;\n',
        '// EspoCrmSyncService removido em 13/02/2026\n'
    )
    
    # Neutralizar o bloco espocrm no array de stats
    content = content.replace(
        """            'espocrm' => [
                'total' => IntegrationLog::where('sistema', 'ESPO CRM')->count(),
                'sucesso' => IntegrationLog::where('sistema', 'ESPO CRM')->where('status', 'sucesso')->count(),
                'erro' => IntegrationLog::where('sistema', 'ESPO CRM')->where('status', 'erro')->count(),
                'ultima_sync' => IntegrationLog::where('sistema', 'ESPO CRM')->where('status', 'sucesso')->latest()->first()?->created_at,
            ],""",
        """            'espocrm' => [
                'total' => 0,
                'sucesso' => 0,
                'erro' => 0,
                'ultima_sync' => null,
            ], // ESPO CRM desativado 13/02/2026"""
    )
    
    with open(fp, 'w') as f:
        f.write(content)
    print('  [OK] SincronizacaoController limpo')
    patches_ok += 1

# ═══════════════════════════════════════════════════════════
# 3.4 IntegracoesController - neutralizar checkEspoCrmStatus
# ═══════════════════════════════════════════════════════════
print('\n[3.4] IntegracoesController.php...')
fp = 'app/Http/Controllers/Admin/IntegracoesController.php'
if os.path.exists(fp):
    backup(fp)
    with open(fp, 'r') as f:
        content = f.read()
    
    # Substituir a chamada no array de status
    content = content.replace(
        "'espocrm' => $this->checkEspoCrmStatus(),",
        "'espocrm' => ['status' => 'disabled', 'message' => 'ESPO CRM desativado - CRM Nativo', 'last_check' => now()->toDateTimeString()], // desativado 13/02/2026"
    )
    
    with open(fp, 'w') as f:
        f.write(content)
    print('  [OK] IntegracoesController ESPO status desativado')
    patches_ok += 1

# ═══════════════════════════════════════════════════════════
# 3.5 LeadProcessingService - remover sendToEspoCRM e chamadas
# ═══════════════════════════════════════════════════════════
print('\n[3.5] LeadProcessingService.php...')
fp = 'app/Services/LeadProcessingService.php'
if os.path.exists(fp):
    backup(fp)
    with open(fp, 'r') as f:
        content = f.read()
    
    # Neutralizar todas as chamadas a sendToEspoCRM
    content = content.replace(
        "$crmLeadId = $this->sendToEspoCRM($lead);",
        "$crmLeadId = null; // ESPO removido 13/02/2026"
    )
    content = content.replace(
        "if (!$lead->espocrm_id) {",
        "if (false) { // ESPO removido 13/02/2026"
    )
    
    # Neutralizar o método sendToEspoCRM
    if 'public function sendToEspoCRM' in content:
        content = re.sub(
            r'(public function sendToEspoCRM\(Lead \$lead\): \?string\s*\{)',
            r'''\1
        return null; // ESPO CRM desativado 13/02/2026
        if(false){''',
            content,
            count=1
        )
        print('  [OK] sendToEspoCRM neutralizado')
    
    with open(fp, 'w') as f:
        f.write(content)
    patches_ok += 1

# ═══════════════════════════════════════════════════════════
# 3.6 IntegracaoController - neutralizar sincronizarEspoCRM
# ═══════════════════════════════════════════════════════════
print('\n[3.6] IntegracaoController.php...')
fp = 'app/Http/Controllers/IntegracaoController.php'
if os.path.exists(fp):
    backup(fp)
    with open(fp, 'r') as f:
        content = f.read()
    
    # Neutralizar chamada no array
    content = content.replace(
        "'espocrm' => $this->sincronizarEspoCRM($log),",
        "'espocrm' => ['sucesso' => false, 'registros' => 0, 'mensagem' => 'Desativado'], // ESPO removido"
    )
    content = content.replace(
        "$resultadoEspoCRM = $this->sincronizarEspoCRM($log);",
        "$resultadoEspoCRM = ['sucesso' => false, 'registros' => 0]; // ESPO removido"
    )
    
    with open(fp, 'w') as f:
        f.write(content)
    print('  [OK] IntegracaoController ESPO neutralizado')
    patches_ok += 1

# ═══════════════════════════════════════════════════════════
# 3.7 config/services.php - remover bloco espocrm
# ═══════════════════════════════════════════════════════════
print('\n[3.7] config/services.php...')
fp = 'config/services.php'
if os.path.exists(fp):
    patch(fp,
        """    'espocrm' => [
        'base_url' => env('ESPO_CRM_BASE_URL', 'https://www.mayeradvogados.adv.br/CRM'),
        'api_key' => env('ESPO_CRM_API_KEY', 'c2d399133dc730549073361f39dd4a11'),
        'url' => env('ESPOCRM_URL', 'https://mayeradvogados.adv.br/CRM/api/v1'),
    ],""",
        """    // espocrm removido em 13/02/2026 - substituído por CRM Nativo""",
        'config/services.php ESPO removido'
    )

# ═══════════════════════════════════════════════════════════
# 3.8 config/database.php - remover conexão mysql_espo
# ═══════════════════════════════════════════════════════════
print('\n[3.8] config/database.php...')
fp = 'config/database.php'
if os.path.exists(fp):
    backup(fp)
    with open(fp, 'r') as f:
        content = f.read()
    
    # Remover bloco mysql_espo
    old_db = """        'mysql_espo' => [
            'driver'    => 'mysql',
            'host'      => env('ESPO_DB_HOST', '127.0.0.1'),
            'port'      => env('ESPO_DB_PORT', '3306'),
            'database'  => env('ESPO_DB_DATABASE', ''),
            'username'  => env('ESPO_DB_USERNAME', ''),
            'password'  => env('ESPO_DB_PASSWORD', ''),"""
    
    if old_db in content:
        # Encontrar o bloco completo (até o próximo '],')
        start = content.find(old_db)
        # Achar o '], do fechamento
        end = content.find('],', start + len(old_db))
        if end != -1:
            end += 2  # incluir o ],
            block = content[start:end]
            content = content.replace(block, "        // mysql_espo removido em 13/02/2026")
            with open(fp, 'w') as f:
                f.write(content)
            print('  [OK] config/database.php mysql_espo removido')
            patches_ok += 1
    else:
        print('  [SKIP] mysql_espo não encontrado em database.php')

# ═══════════════════════════════════════════════════════════
# 3.9 SyncClientesCommand - remover menção ESPO na description
# ═══════════════════════════════════════════════════════════
print('\n[3.9] SyncClientesCommand.php...')
patch('app/Console/Commands/SyncClientesCommand.php',
    "protected $description = 'Sincroniza clientes do DataJuri e ESPO CRM';",
    "protected $description = 'Sincroniza clientes do DataJuri';",
    'SyncClientesCommand description'
)

# ═══════════════════════════════════════════════════════════
# 3.10 SyncLeadsCommand - remover menção ESPO
# ═══════════════════════════════════════════════════════════
print('\n[3.10] SyncLeadsCommand.php...')
patch('app/Console/Commands/SyncLeadsCommand.php',
    "protected $description = 'Sincroniza leads do ESPO CRM';",
    "protected $description = 'Sincroniza leads do sistema';",
    'SyncLeadsCommand description'
)

# ═══════════════════════════════════════════════════════════
# 3.11 ClientesMercadoService - remover referência espocrm_id
# ═══════════════════════════════════════════════════════════
print('\n[3.11] ClientesMercadoService.php...')
fp = 'app/Services/ClientesMercadoService.php'
if os.path.exists(fp):
    backup(fp)
    with open(fp, 'r') as f:
        content = f.read()
    # Apenas um comentário, não precisa mudar
    if 'espocrm_id' in content:
        content = content.replace('espocrm_id', 'espocrm_id /* legado */')
        with open(fp, 'w') as f:
            f.write(content)
        print('  [OK] ClientesMercadoService comentário adicionado')
        patches_ok += 1

# ═══════════════════════════════════════════════════════════
# 3.12 Views - Sidebar menu text
# ═══════════════════════════════════════════════════════════
print('\n[3.12] Sidebar app.blade.php - remover menção ESPO...')
patch('resources/views/layouts/app.blade.php',
    '<!-- Sincronização Unificada (DataJuri + ESPO) -->',
    '<!-- Sincronização Unificada (DataJuri) -->',
    'Sidebar menu ESPO text'
)

# ═══════════════════════════════════════════════════════════
# 3.13 View sincronizacao-unificada - remover seção ESPO inteira
# ═══════════════════════════════════════════════════════════
print('\n[3.13] View sincronizacao-unificada/index.blade.php...')
fp = 'resources/views/admin/sincronizacao-unificada/index.blade.php'
if os.path.exists(fp):
    backup(fp)
    with open(fp, 'r') as f:
        content = f.read()
    
    # Substituir texto do header
    content = content.replace(
        'Gerenciamento centralizado de dados DataJuri + ESPO CRM',
        'Gerenciamento centralizado de dados DataJuri'
    )
    
    # Remover bloco ESPOCRM da view (entre os marcadores)
    # Bloco começa com {{-- SEÇÃO ESPOCRM --}}
    espo_start = content.find('{{-- SEÇÃO ESPOCRM --}}')
    if espo_start != -1:
        # Encontrar o fim da seção (próximo </div> de nível adequado ou próxima seção)
        # Vamos buscar o fechamento do bloco - tipicamente termina antes da próxima seção
        # Procurar pelo próximo bloco grande ou fim do container
        espo_section_end = content.find('{{-- SEÇÃO', espo_start + 30)
        if espo_section_end == -1:
            # Tentar encontrar fechamento alternativo
            espo_section_end = content.find('</div>\n\n', espo_start + 100)
            if espo_section_end != -1:
                espo_section_end += 8
        
        if espo_section_end != -1:
            espo_block = content[espo_start:espo_section_end]
            content = content.replace(espo_block, 
                '{{-- SEÇÃO ESPOCRM REMOVIDA em 13/02/2026 - substituído por CRM Nativo --}}\n\n        ')
            print('  [OK] Seção ESPOCRM removida da view')
        else:
            print('  [WARN] Não encontrei fim da seção ESPOCRM')
    
    # Remover JS do ESPO (botões test e sync)
    # Comentar bloco JS ESPOCRM
    content = content.replace(
        "    // ESPOCRM - Testar Conexão",
        "    /* ESPOCRM REMOVIDO 13/02/2026"
    )
    
    # Encontrar o fim do bloco JS do ESPO (após o último showToast ESPOCRM)
    espo_js_end = content.find("showToast('Erro ao sincronizar ESPOCRM', 'error');")
    if espo_js_end != -1:
        # Achar o fechamento do bloco try/catch (próximas 3 linhas: }); })
        close_pos = content.find('});', espo_js_end)
        if close_pos != -1:
            close_pos = content.find('\n', close_pos) + 1
            content = content[:close_pos] + '    ESPOCRM JS REMOVIDO */\n' + content[close_pos:]
            print('  [OK] JS ESPOCRM comentado')
    
    with open(fp, 'w') as f:
        f.write(content)
    patches_ok += 1

# ═══════════════════════════════════════════════════════════
# 3.14 View leads/show.blade.php - remover referência ESPO
# ═══════════════════════════════════════════════════════════
print('\n[3.14] leads/show.blade.php...')
fp = 'resources/views/leads/show.blade.php'
if os.path.exists(fp):
    backup(fp)
    with open(fp, 'r') as f:
        content = f.read()
    
    # Remover botão e display do espocrm_id
    content = content.replace(
        "@if(!$lead->espocrm_id)",
        "@if(false) {{-- ESPO removido 13/02/2026 --}}"
    )
    content = content.replace(
        "@if($lead->espocrm_id)",
        "@if(false) {{-- ESPO removido --}}"
    )
    content = content.replace(
        "if (!confirm('Enviar este lead para o EspoCRM?')) return;",
        "alert('ESPO CRM desativado'); return; // ESPO removido"
    )
    
    with open(fp, 'w') as f:
        f.write(content)
    print('  [OK] leads/show.blade.php ESPO removido')
    patches_ok += 1

# ═══════════════════════════════════════════════════════════
# RELATÓRIO FINAL
# ═══════════════════════════════════════════════════════════
print('\n' + '='*60)
print(f'✅ FASE 3 CONCLUÍDA')
print(f'   Patches aplicados: {patches_ok}')
print(f'   Patterns não encontrados: {patches_fail}')
print('='*60)
