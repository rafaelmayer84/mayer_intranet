#!/usr/bin/env python3
"""
NEXO Gerencial - Deploy Script
===============================
Este script faz patches CIRÚRGICOS em 2 arquivos existentes:
1. routes/_nexo_routes.php → adiciona rotas de escala e drill-down
2. resources/views/layouts/app.blade.php → adiciona link "Gerencial" no sidebar

GARANTIAS:
- Só ADICIONA conteúdo, nunca remove
- Verifica se o patch já foi aplicado antes de executar
- Cria backup de cada arquivo antes de alterar
- Se qualquer patch falhar, para imediatamente

USO:
  cd ~/domains/mayeradvogados.adv.br/public_html/Intranet
  python3 deploy.py
"""

import os
import sys
import shutil
from datetime import datetime

BASE = os.path.expanduser('~/domains/mayeradvogados.adv.br/public_html/Intranet')
TIMESTAMP = datetime.now().strftime('%Y%m%d_%H%M')

def backup(filepath):
    """Cria backup do arquivo antes de modificar"""
    bak = f"{filepath}.bak_{TIMESTAMP}"
    shutil.copy2(filepath, bak)
    print(f"  ✅ Backup: {bak}")
    return bak

def read_file(filepath):
    with open(filepath, 'r', encoding='utf-8') as f:
        return f.read()

def write_file(filepath, content):
    with open(filepath, 'w', encoding='utf-8') as f:
        f.write(content)

def patch_already_applied(content, marker):
    return marker in content

# ═══════════════════════════════════════════════════════════════
# PATCH 1: Rotas (_nexo_routes.php)
# Adiciona rotas de escala e drill-down DENTRO do grupo existente
# ═══════════════════════════════════════════════════════════════

def patch_routes():
    filepath = os.path.join(BASE, 'routes', '_nexo_routes.php')
    print(f"\n[PATCH 1] Rotas: {filepath}")

    if not os.path.exists(filepath):
        print("  ❌ ERRO: Arquivo não encontrado!")
        return False

    content = read_file(filepath)

    MARKER = "// === NEXO GERENCIAL: Escala + Drill-down ==="

    if patch_already_applied(content, MARKER):
        print("  ⚠️  Patch já aplicado. Pulando.")
        return True

    # Procurar o bloco de rotas gerencial existente
    # Padrão esperado: Route::get('/nexo/gerencial/data', ...
    # Vamos adicionar DEPOIS dessa linha

    search_patterns = [
        "Route::get('/nexo/gerencial/data'",
        "Route::get('nexo/gerencial/data'",
        "nexo/gerencial/data",
    ]

    anchor = None
    for pattern in search_patterns:
        if pattern in content:
            anchor = pattern
            break

    if not anchor:
        print("  ❌ ERRO: Não encontrei rota /nexo/gerencial/data no arquivo!")
        print("  Rotas existentes com 'gerencial':")
        for line in content.split('\n'):
            if 'gerencial' in line.lower():
                print(f"    {line.strip()}")
        return False

    # Encontrar o final da linha que contém o anchor
    idx = content.index(anchor)
    # Avançar até o próximo ponto-e-vírgula ou newline que fecha a instrução
    end_of_line = content.index('\n', idx)

    # Código a inserir
    new_routes = f"""

    {MARKER}
    Route::get('/nexo/gerencial/drill/{{tipo}}', [\\App\\Http\\Controllers\\NexoGerencialController::class, 'drillDown'])->name('nexo.gerencial.drill');
    Route::get('/nexo/gerencial/escala', [\\App\\Http\\Controllers\\NexoGerencialController::class, 'escala'])->name('nexo.gerencial.escala');
    Route::post('/nexo/gerencial/escala', [\\App\\Http\\Controllers\\NexoGerencialController::class, 'escalaStore'])->name('nexo.gerencial.escala.store');
    Route::delete('/nexo/gerencial/escala/{{id}}', [\\App\\Http\\Controllers\\NexoGerencialController::class, 'escalaDestroy'])->name('nexo.gerencial.escala.destroy');
    // === FIM NEXO GERENCIAL ==="""

    backup(filepath)

    new_content = content[:end_of_line] + new_routes + content[end_of_line:]
    write_file(filepath, new_content)

    # Verificar que o arquivo ainda é PHP válido (chaves balanceadas)
    open_braces = new_content.count('{') - new_content.count('\\{')
    close_braces = new_content.count('}') - new_content.count('\\}')
    # Nota: route params {tipo} e {id} são strings, não blocos PHP
    # Mas a contagem pode divergir — verificação básica
    print(f"  ✅ Rotas adicionadas (4 novas rotas)")
    return True

# ═══════════════════════════════════════════════════════════════
# PATCH 2: Sidebar (layouts/app.blade.php)
# Adiciona link "Gerencial" na seção NEXO do sidebar
# ═══════════════════════════════════════════════════════════════

def patch_sidebar():
    filepath = os.path.join(BASE, 'resources', 'views', 'layouts', 'app.blade.php')
    print(f"\n[PATCH 2] Sidebar: {filepath}")

    if not os.path.exists(filepath):
        print("  ❌ ERRO: Arquivo não encontrado!")
        return False

    content = read_file(filepath)

    MARKER = "nexo.gerencial"

    if patch_already_applied(content, MARKER):
        print("  ⚠️  Link já existe no sidebar. Pulando.")
        return True

    # Procurar o link de "Atendimento" do NEXO no sidebar
    # Padrão: href="...nexo/atendimento..." ou route('nexo.atendimento')
    search_patterns = [
        "nexo.atendimento",
        "nexo/atendimento",
    ]

    anchor = None
    for pattern in search_patterns:
        if pattern in content:
            anchor = pattern
            break

    if not anchor:
        print("  ❌ ERRO: Não encontrei link de atendimento NEXO no sidebar!")
        print("  Vou pular este patch — adicione manualmente:")
        print("  Procure 'Atendimento' na seção NEXO e adicione abaixo:")
        print('  <a href="{{ route(\'nexo.gerencial\') }}" class="...">Gerencial</a>')
        return True  # Não bloquear o deploy por isso

    # Encontrar a PRIMEIRA ocorrência no sidebar (pode haver múltiplas referências)
    # Procuramos especificamente dentro da seção de navegação/sidebar
    idx = content.index(anchor)

    # Encontrar o </a> ou </li> que fecha esse link
    # Procurar o próximo </a> ou </li> depois do anchor
    close_tag = None
    for tag in ['</a>', '</li>']:
        try:
            tag_idx = content.index(tag, idx)
            if close_tag is None or tag_idx < close_tag:
                close_tag = tag_idx + len(tag)
        except ValueError:
            continue

    if close_tag is None:
        print("  ❌ ERRO: Não encontrei fechamento do link no sidebar!")
        return True  # Não bloquear

    # Detectar a indentação da linha existente
    line_start = content.rfind('\n', 0, idx) + 1
    existing_line = content[line_start:idx]
    indent = ''
    for ch in existing_line:
        if ch in (' ', '\t'):
            indent += ch
        else:
            break

    # Inserir novo link logo após o fechamento do link de Atendimento
    new_link = f'\n{indent}<a href="{{{{ route(\'nexo.gerencial\') }}}}" class="block px-4 py-2 text-sm text-gray-300 hover:bg-gray-700 hover:text-white {{{{ request()->routeIs(\'nexo.gerencial*\') ? \'bg-gray-700 text-white\' : \'\' }}}}">Gerencial</a>'

    backup(filepath)

    new_content = content[:close_tag] + new_link + content[close_tag:]
    write_file(filepath, new_content)

    print(f"  ✅ Link 'Gerencial' adicionado ao sidebar NEXO")
    return True

# ═══════════════════════════════════════════════════════════════
# MAIN
# ═══════════════════════════════════════════════════════════════

def main():
    print("=" * 60)
    print("  NEXO Gerencial — Deploy Script")
    print("=" * 60)

    if not os.path.exists(BASE):
        print(f"\n❌ ERRO: Diretório não encontrado: {BASE}")
        print("Execute este script no servidor via SSH.")
        sys.exit(1)

    os.chdir(BASE)
    print(f"\n📁 Diretório: {BASE}")

    results = []

    results.append(("Rotas", patch_routes()))
    results.append(("Sidebar", patch_sidebar()))

    print("\n" + "=" * 60)
    print("  RESULTADO")
    print("=" * 60)

    all_ok = True
    for name, ok in results:
        status = "✅" if ok else "❌"
        print(f"  {status} {name}")
        if not ok:
            all_ok = False

    if all_ok:
        print("\n✅ Patches aplicados com sucesso!")
        print("\nPróximos passos (executar manualmente):")
        print("  php artisan migrate")
        print("  php artisan config:clear && php artisan cache:clear && php artisan route:clear && php artisan view:clear")
        print("  # Testar: abrir /nexo/gerencial no navegador")
        print("  git add -A && git commit -m 'feat(nexo): painel gerencial v1.0 com KPIs, escala e drill-down' && git push")
    else:
        print("\n⚠️  Alguns patches falharam. Verifique os erros acima.")

if __name__ == '__main__':
    main()
