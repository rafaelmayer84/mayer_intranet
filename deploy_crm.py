#!/usr/bin/env python3
"""
CRM Comercial - Script de Deploy
Intranet Mayer Advogados
Gerado em: 13/02/2026

Uso: python3 deploy_crm.py
Executar de dentro do diretório Intranet/
"""

import os
import sys
import shutil
from datetime import datetime

BASE = os.getcwd()
BACKUP_SUFFIX = f".bak_crm_{datetime.now().strftime('%Y%m%d_%H%M')}"

def log(msg):
    print(f"  → {msg}")

def backup(filepath):
    if os.path.exists(filepath):
        bak = filepath + BACKUP_SUFFIX
        shutil.copy2(filepath, bak)
        log(f"Backup: {bak}")
        return True
    return False

def patch_file(filepath, search, replace, description):
    """Patch cirúrgico: substitui search por replace no arquivo."""
    if not os.path.exists(filepath):
        print(f"  ❌ Arquivo não encontrado: {filepath}")
        return False

    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()

    if replace in content:
        log(f"Já aplicado: {description}")
        return True

    if search not in content:
        print(f"  ⚠️  Âncora não encontrada para: {description}")
        print(f"      Procurado: {repr(search[:80])}")
        return False

    content = content.replace(search, replace, 1)

    with open(filepath, 'w', encoding='utf-8') as f:
        f.write(content)

    log(f"Aplicado: {description}")
    return True


def main():
    print("=" * 60)
    print("  CRM COMERCIAL - DEPLOY")
    print("  Intranet Mayer Advogados")
    print("=" * 60)
    print()

    # Verificar que estamos no diretório correto
    if not os.path.exists(os.path.join(BASE, 'artisan')):
        print("❌ Execute este script dentro do diretório Intranet/")
        print(f"   Diretório atual: {BASE}")
        sys.exit(1)

    # =========================================================
    # PASSO 1: Verificar se diretórios CRM existem (upload prévio)
    # =========================================================
    print("[1/6] Verificando estrutura de arquivos...")

    dirs_needed = [
        'app/Http/Controllers/Crm',
        'app/Models/Crm',
        'app/Services/Crm',
        'resources/views/crm',
    ]

    for d in dirs_needed:
        full = os.path.join(BASE, d)
        if not os.path.isdir(full):
            print(f"  ❌ Diretório não encontrado: {d}")
            print(f"     Faça upload do pacote crm_module primeiro.")
            sys.exit(1)
        log(f"OK: {d}")

    # Verificar arquivos essenciais
    files_needed = [
        'app/Http/Controllers/Crm/PipelineController.php',
        'app/Models/Crm/Account.php',
        'app/Services/Crm/CrmIdentityResolver.php',
        'resources/views/crm/pipeline.blade.php',
        'routes/_crm_routes.php',
        'database/seeders/CrmStagesSeeder.php',
    ]

    for f in files_needed:
        full = os.path.join(BASE, f)
        if not os.path.isfile(full):
            print(f"  ❌ Arquivo não encontrado: {f}")
            sys.exit(1)

    # Contar migrations CRM
    mig_dir = os.path.join(BASE, 'database/migrations')
    crm_migs = [f for f in os.listdir(mig_dir) if 'crm_' in f]
    log(f"Migrations CRM encontradas: {len(crm_migs)}")

    print()

    # =========================================================
    # PASSO 2: Backup de arquivos que serão patcheados
    # =========================================================
    print("[2/6] Criando backups...")

    backup(os.path.join(BASE, 'routes/web.php'))
    backup(os.path.join(BASE, 'resources/views/layouts/app.blade.php'))

    print()

    # =========================================================
    # PASSO 3: Patch routes/web.php
    # =========================================================
    print("[3/6] Patcheando routes/web.php...")

    web_php = os.path.join(BASE, 'routes/web.php')

    # Verificar se já tem require do CRM
    with open(web_php, 'r', encoding='utf-8') as f:
        web_content = f.read()

    if '_crm_routes.php' in web_content:
        log("Rota CRM já incluída em web.php - pulando")
    else:
        # Estratégia: adicionar require antes do último require existente de _*_routes
        # ou no final do arquivo se não encontrar padrão

        # Buscar último require de rotas parciais
        import re
        requires = list(re.finditer(r"require\s+__DIR__\s*\.\s*'/_([\w]+)_routes\.php';", web_content))

        if requires:
            # Inserir após o último require de rotas parciais
            last_require = requires[-1]
            insert_pos = last_require.end()
            new_line = "\nrequire __DIR__ . '/_crm_routes.php';"

            web_content = web_content[:insert_pos] + new_line + web_content[insert_pos:]

            with open(web_php, 'w', encoding='utf-8') as f:
                f.write(web_content)
            log("Adicionado require _crm_routes.php após último require parcial")
        else:
            # Fallback: adicionar no final
            with open(web_php, 'a', encoding='utf-8') as f:
                f.write("\n\n// CRM Comercial\nrequire __DIR__ . '/_crm_routes.php';\n")
            log("Adicionado require _crm_routes.php no final do arquivo")

    print()

    # =========================================================
    # PASSO 4: Patch app.blade.php (menu sidebar)
    # =========================================================
    print("[4/6] Patcheando menu na sidebar...")

    layout_file = os.path.join(BASE, 'resources/views/layouts/app.blade.php')

    with open(layout_file, 'r', encoding='utf-8') as f:
        layout_content = f.read()

    if "crm.pipeline" in layout_content:
        log("Item CRM já existe no menu - pulando")
    else:
        # Procurar âncora: item "Administração" ou "ADMINISTRAÇÃO" no menu
        # Inserir ANTES da seção Administração

        # Padrão 1: procurar pelo texto "Administração" ou "ADMINISTRA"
        admin_anchors = [
            'Administração',
            'ADMINISTRA',
            'administracao',
            'admin/sincronizacao',
        ]

        crm_menu_item = '''
                {{-- CRM Comercial --}}
                <a href="{{ route('crm.pipeline') }}"
                   class="flex items-center gap-3 px-3 py-2 rounded-lg transition-colors {{ request()->is('crm*') ? 'bg-[#385776]/10 text-[#385776] font-medium' : 'text-gray-600 hover:bg-gray-100' }}">
                    <span class="text-lg">💼</span>
                    <span class="sidebar-label">CRM Comercial</span>
                </a>
'''

        inserted = False
        for anchor in admin_anchors:
            idx = layout_content.find(anchor)
            if idx > 0:
                # Encontrar o início da tag <a ou <div ou <!-- antes dessa âncora
                # Procurar para trás o início do bloco
                search_start = max(0, idx - 500)
                block_start = layout_content.rfind('\n', search_start, idx)
                if block_start < 0:
                    block_start = idx

                # Procurar mais para trás para pegar o início do item de menu completo
                # Buscar a tag de abertura mais próxima
                line_start = layout_content.rfind('\n', max(0, block_start - 200), block_start)
                if line_start < 0:
                    line_start = block_start

                # Verificar se há um comentário {{-- antes
                comment_check = layout_content[max(0, line_start - 100):line_start + 1]
                comment_pos = comment_check.rfind('{{--')
                if comment_pos >= 0:
                    actual_start = max(0, line_start - 100) + comment_pos
                else:
                    # Procurar <a href ou <div antes da âncora
                    href_pos = layout_content.rfind('<a ', max(0, idx - 300), idx)
                    div_pos = layout_content.rfind('<div', max(0, idx - 300), idx)
                    actual_start = max(href_pos, div_pos)
                    if actual_start < 0:
                        actual_start = block_start

                # Inserir antes do bloco Administração
                layout_content = layout_content[:actual_start] + crm_menu_item + '\n' + layout_content[actual_start:]

                with open(layout_file, 'w', encoding='utf-8') as f:
                    f.write(layout_content)

                log(f"Menu CRM inserido antes de '{anchor}'")
                inserted = True
                break

        if not inserted:
            # Fallback: procurar padrão genérico de item de menu e inserir antes do último
            log("⚠️  Âncora Administração não encontrada no menu")
            log("    Adicione manualmente ao menu:")
            log("    <a href=\"{{ route('crm.pipeline') }}\" class=\"...\">💼 CRM Comercial</a>")

    print()

    # =========================================================
    # PASSO 5: Informar sobre migrations e seeder
    # =========================================================
    print("[5/6] Instruções para migrations e seeder...")
    print()
    print("  Execute os seguintes comandos via SSH:")
    print()
    print("  cd ~/domains/mayeradvogados.adv.br/public_html/Intranet")
    print("  php artisan migrate --force")
    print("  php artisan db:seed --class=CrmStagesSeeder --force")
    print()

    # =========================================================
    # PASSO 6: Cache clear
    # =========================================================
    print("[6/6] Limpeza de cache...")
    print()
    print("  Execute:")
    print("  php artisan config:clear && php artisan route:clear && php artisan view:clear && php artisan cache:clear")
    print()

    # =========================================================
    # RESUMO
    # =========================================================
    print("=" * 60)
    print("  DEPLOY CONCLUÍDO")
    print("=" * 60)
    print()
    print("  Arquivos patcheados:")
    print("    - routes/web.php (require _crm_routes.php)")
    print("    - resources/views/layouts/app.blade.php (menu CRM)")
    print()
    print("  Próximos passos:")
    print("    1. php artisan migrate --force")
    print("    2. php artisan db:seed --class=CrmStagesSeeder --force")
    print("    3. php artisan config:clear && route:clear && view:clear && cache:clear")
    print("    4. Acessar /crm no navegador")
    print("    5. git add -A && git commit -m 'feat: CRM Comercial module' && git push")
    print()


if __name__ == '__main__':
    main()
