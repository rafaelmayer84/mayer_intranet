#!/usr/bin/env python3
"""
deploy_manuais_normativos.py
────────────────────────────
Script de deploy para o módulo Manuais Normativos.
Executa patches cirúrgicos no User.php, web.php e layouts/app.blade.php.

USO (via SSH no servidor):
  cd ~/domains/mayeradvogados.adv.br/public_html/Intranet
  python3 deploy_manuais_normativos.py
"""

import os
import sys
import shutil
from datetime import datetime

BASE = os.path.expanduser('~/domains/mayeradvogados.adv.br/public_html/Intranet')

# ─── Utilitários ───
def backup(filepath):
    if os.path.exists(filepath):
        ts = datetime.now().strftime('%Y%m%d_%H%M%S')
        bak = f"{filepath}.bak_{ts}"
        shutil.copy2(filepath, bak)
        print(f"  ✓ Backup: {bak}")

def read_file(filepath):
    with open(filepath, 'r', encoding='utf-8') as f:
        return f.read()

def write_file(filepath, content):
    with open(filepath, 'w', encoding='utf-8') as f:
        f.write(content)

def patch_file(filepath, search, replace, label=""):
    content = read_file(filepath)
    if search not in content:
        if replace.strip() in content:
            print(f"  ⏭ [{label}] Já aplicado em {os.path.basename(filepath)}")
            return True
        print(f"  ✗ [{label}] Trecho não encontrado em {os.path.basename(filepath)}")
        print(f"    Procurado: {search[:80]}...")
        return False
    content = content.replace(search, replace, 1)
    write_file(filepath, content)
    print(f"  ✓ [{label}] Patch aplicado em {os.path.basename(filepath)}")
    return True

# ─── PASSO 1: Patch User.php — adicionar relação manuaisGrupos() ───
def patch_user_model():
    print("\n── PASSO 1: Patch User.php ──")
    filepath = os.path.join(BASE, 'app/Models/User.php')
    backup(filepath)

    content = read_file(filepath)

    # Verificar se já existe
    if 'manuaisGrupos' in content:
        print("  ⏭ Relação manuaisGrupos() já existe no User.php")
        return True

    # Adicionar import se necessário
    if 'use Illuminate\\Database\\Eloquent\\Relations\\BelongsToMany;' not in content:
        # Adicionar após último use statement do model
        if 'use Illuminate\\Database\\Eloquent\\' in content:
            # Encontrar posição para inserir
            pass  # BelongsToMany pode não ser necessário como import separado no User

    # Encontrar a última } do arquivo (fechamento da class) e inserir antes
    # Estratégia: inserir antes do último }
    last_brace_pos = content.rfind('}')
    if last_brace_pos == -1:
        print("  ✗ Não encontrou fechamento da classe User")
        return False

    relation_code = """
    /**
     * Grupos de manuais normativos atribuídos ao usuário.
     */
    public function manuaisGrupos()
    {
        return $this->belongsToMany(
            \\App\\Models\\ManualGrupo::class,
            'manuais_grupo_user',
            'user_id',
            'grupo_id'
        )->withTimestamps();
    }
"""

    new_content = content[:last_brace_pos] + relation_code + content[last_brace_pos:]
    write_file(filepath, new_content)
    print("  ✓ Relação manuaisGrupos() adicionada ao User.php")
    return True

# ─── PASSO 2: Incluir rotas no web.php ───
def patch_routes():
    print("\n── PASSO 2: Patch web.php (include rotas) ──")
    filepath = os.path.join(BASE, 'routes/web.php')
    backup(filepath)

    content = read_file(filepath)

    include_line = "require __DIR__ . '/_manuais_routes.php';"

    if include_line in content:
        print("  ⏭ Include já presente no web.php")
        return True

    # Adicionar ao final do arquivo
    if not content.endswith('\n'):
        content += '\n'
    content += f"\n// Manuais Normativos\n{include_line}\n"
    write_file(filepath, content)
    print("  ✓ Include adicionado ao web.php")
    return True

# ─── PASSO 3: Adicionar item no menu lateral ───
def patch_sidebar_menu():
    print("\n── PASSO 3: Patch menu lateral (layouts/app.blade.php) ──")
    filepath = os.path.join(BASE, 'resources/views/layouts/app.blade.php')
    backup(filepath)

    content = read_file(filepath)

    if 'manuais-normativos' in content:
        print("  ⏭ Item 'Manuais Normativos' já presente no menu")
        return True

    # O item de menu vai ser inserido próximo ao item "Quadro de Avisos" (/avisos)
    # Procurar referência ao avisos no menu para inserir após
    menu_item = """
                    <!-- Manuais Normativos -->
                    <a href="{{ route('manuais-normativos.index') }}"
                       class="flex items-center px-4 py-2.5 text-sm font-medium rounded-lg transition-colors duration-150 {{ request()->is('manuais-normativos*') ? 'bg-blue-50 text-blue-700 dark:bg-blue-900/50 dark:text-blue-300' : 'text-gray-600 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700/50' }}">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                        </svg>
                        Manuais Normativos
                    </a>"""

    # Tentar encontrar o link de Avisos para inserir depois
    # Padrões comuns: /avisos, avisos, Quadro de Avisos
    search_patterns = [
        'Quadro de Avisos',
        '/avisos',
        'avisos.index',
        'AvisoController',
    ]

    inserted = False
    for pattern in search_patterns:
        if pattern in content:
            # Encontrar o </a> mais próximo após esse padrão
            idx = content.find(pattern)
            if idx != -1:
                # Encontrar o próximo </a> após essa posição
                close_a_idx = content.find('</a>', idx)
                if close_a_idx != -1:
                    insert_pos = close_a_idx + len('</a>')
                    new_content = content[:insert_pos] + menu_item + content[insert_pos:]
                    write_file(filepath, new_content)
                    print(f"  ✓ Item de menu inserido após '{pattern}'")
                    inserted = True
                    break

    if not inserted:
        # Fallback: inserir antes do primeiro link admin ou no final da nav
        # Procurar por 'Configurações' ou '/configuracoes'
        for fallback in ['Configurações', '/configuracoes', 'configuracoes']:
            if fallback in content:
                idx = content.find(fallback)
                # Encontrar o <a anterior a esse texto
                a_tag_idx = content.rfind('<a ', 0, idx)
                if a_tag_idx != -1:
                    new_content = content[:a_tag_idx] + menu_item + '\n' + ' ' * 20 + content[a_tag_idx:]
                    write_file(filepath, new_content)
                    print(f"  ✓ Item de menu inserido antes de '{fallback}'")
                    inserted = True
                    break

    if not inserted:
        print("  ⚠ Não conseguiu localizar ponto de inserção no menu.")
        print("    AÇÃO MANUAL: Adicione o seguinte trecho no sidebar de layouts/app.blade.php:")
        print(menu_item)
        return False

    return True

# ─── EXECUÇÃO ───
def main():
    print("=" * 60)
    print("DEPLOY: Módulo Manuais Normativos")
    print(f"Data: {datetime.now().strftime('%d/%m/%Y %H:%M')}")
    print("=" * 60)

    if not os.path.isdir(BASE):
        print(f"\n✗ Diretório base não encontrado: {BASE}")
        sys.exit(1)

    results = []
    results.append(('User.php', patch_user_model()))
    results.append(('web.php', patch_routes()))
    results.append(('Menu lateral', patch_sidebar_menu()))

    print("\n" + "=" * 60)
    print("RESUMO:")
    for label, ok in results:
        status = '✓' if ok else '✗'
        print(f"  {status} {label}")

    print("\n── PRÓXIMOS PASSOS (executar manualmente) ──")
    print(f"  cd {BASE}")
    print("  php artisan migrate --force")
    print("  php artisan cache:clear && php artisan view:clear && php artisan config:clear")
    print("  php artisan route:list --name=manuais")
    print("=" * 60)

if __name__ == '__main__':
    main()
