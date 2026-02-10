#!/usr/bin/env python3
"""
Deploy Script: Processos Internos (BSC)
========================================
Aplica patches cirúrgicos no servidor para ativar o módulo.

USO (via SSH no servidor):
    cd ~/domains/mayeradvogados.adv.br/public_html/Intranet
    python3 deploy_processos_internos.py

OU se python3 não disponível:
    php artisan tinker
    ... executar queries SQL manualmente (ver IMPLEMENTACAO.md)

PASSOS:
    1. Adiciona require das rotas no web.php
    2. Adiciona item de menu no layout app.blade.php
    3. Adiciona módulo AndamentoFase ao config/datajuri.php
"""

import os
import sys

BASE_DIR = os.path.dirname(os.path.abspath(__file__))

def patch_file(filepath, search, replace, description):
    """Substitui uma ocorrência única de 'search' por 'replace' no arquivo."""
    full_path = os.path.join(BASE_DIR, filepath)
    if not os.path.exists(full_path):
        print(f"  ⚠ ARQUIVO NÃO ENCONTRADO: {filepath}")
        return False

    with open(full_path, 'r', encoding='utf-8') as f:
        content = f.read()

    if search not in content:
        if replace.strip() in content:
            print(f"  ✔ JÁ APLICADO: {description}")
            return True
        print(f"  ⚠ TRECHO NÃO ENCONTRADO: {description}")
        print(f"    Buscando: {repr(search[:80])}...")
        return False

    new_content = content.replace(search, replace, 1)

    with open(full_path, 'w', encoding='utf-8') as f:
        f.write(new_content)

    print(f"  ✔ APLICADO: {description}")
    return True


def main():
    print("=" * 60)
    print("DEPLOY: Processos Internos (BSC)")
    print("=" * 60)

    # ─── PASSO 1: Adicionar rota ao web.php ───
    print("\n[PASSO 1] Adicionando rota ao routes/web.php...")

    # Buscar o último require de rotas existente para inserir depois
    # Padrão esperado: require de _avisos_routes.php ou sincronizacao-unificada.php
    routes_file = 'routes/web.php'
    full_path = os.path.join(BASE_DIR, routes_file)

    if os.path.exists(full_path):
        with open(full_path, 'r', encoding='utf-8') as f:
            content = f.read()

        route_require = "require __DIR__ . '/_processos_internos_routes.php';"

        if route_require in content:
            print("  ✔ JÁ APLICADO: Rota de Processos Internos")
        else:
            # Encontrar último require e inserir depois
            lines = content.split('\n')
            insert_idx = len(lines) - 1

            # Procurar último require
            for i in range(len(lines) - 1, -1, -1):
                if 'require' in lines[i] and '_routes' in lines[i]:
                    insert_idx = i + 1
                    break

            lines.insert(insert_idx, '')
            lines.insert(insert_idx + 1, '// Processos Internos (BSC)')
            lines.insert(insert_idx + 2, route_require)

            with open(full_path, 'w', encoding='utf-8') as f:
                f.write('\n'.join(lines))

            print("  ✔ APLICADO: require de _processos_internos_routes.php")
    else:
        print(f"  ⚠ ARQUIVO NÃO ENCONTRADO: {routes_file}")

    # ─── PASSO 2: Adicionar menu no layout ───
    print("\n[PASSO 2] Adicionando item de menu no sidebar...")

    # O menu RESULTADOS já tem itens: Financeiro, Clientes & Mercado
    # Precisamos adicionar "Processos Internos" depois de "Clientes & Mercado"
    layout_file = 'resources/views/layouts/app.blade.php'
    full_layout = os.path.join(BASE_DIR, layout_file)

    if os.path.exists(full_layout):
        with open(full_layout, 'r', encoding='utf-8') as f:
            layout_content = f.read()

        menu_item = 'processos-internos'

        if menu_item in layout_content:
            print("  ✔ JÁ APLICADO: Item de menu Processos Internos")
        else:
            # Procurar por "clientes-mercado" ou "Clientes" no menu para inserir depois
            # Padrão do menu: <a href="..." class="...">Clientes & Mercado</a>
            # Inserir o novo item logo depois

            search_patterns = [
                'Clientes & Mercado</a>',
                'Clientes &amp; Mercado</a>',
                'clientes-mercado',
            ]

            inserted = False
            for pattern in search_patterns:
                if pattern in layout_content:
                    # Encontrar a linha com o padrão
                    lines = layout_content.split('\n')
                    for i, line in enumerate(lines):
                        if pattern in line:
                            # Encontrar o </li> ou </a> correspondente
                            # Inserir novo <li><a>...</a></li> na mesma indentação
                            indent = '                        '  # padrão do sidebar
                            new_menu = f'{indent}<a href="{{{{ route(\'resultados.bsc.processos-internos.index\') }}}}" class="block px-4 py-2 text-sm {{{{ request()->routeIs(\'resultados.bsc.processos-internos.*\') ? \'text-blue-600 dark:text-blue-400 font-medium\' : \'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700\' }}}}">Processos Internos</a>'

                            # Inserir depois do fechamento do item anterior
                            for j in range(i + 1, min(i + 5, len(lines))):
                                if '</li>' in lines[j] or '</a>' in lines[j]:
                                    lines.insert(j + 1, new_menu)
                                    inserted = True
                                    break
                            if not inserted:
                                lines.insert(i + 1, new_menu)
                                inserted = True
                            break

                    if inserted:
                        with open(full_layout, 'w', encoding='utf-8') as f:
                            f.write('\n'.join(lines))
                        print("  ✔ APLICADO: Item de menu Processos Internos")
                        break

            if not inserted:
                print("  ⚠ NÃO FOI POSSÍVEL INSERIR MENU AUTOMATICAMENTE")
                print("    Adicione manualmente no sidebar, após 'Clientes & Mercado':")
                print('    <a href="{{ route(\'resultados.bsc.processos-internos.index\') }}">Processos Internos</a>')
    else:
        print(f"  ⚠ ARQUIVO NÃO ENCONTRADO: {layout_file}")

    # ─── PASSO 3: Adicionar módulo AndamentoFase ao config/datajuri.php ───
    print("\n[PASSO 3] Adicionando módulo AndamentoFase ao config/datajuri.php...")

    config_file = 'config/datajuri.php'
    full_config = os.path.join(BASE_DIR, config_file)

    if os.path.exists(full_config):
        with open(full_config, 'r', encoding='utf-8') as f:
            config_content = f.read()

        if 'AndamentoFase' in config_content:
            print("  ✔ JÁ APLICADO: Módulo AndamentoFase no config")
        else:
            # Procurar o último módulo (OrdemServico) e inserir depois
            andamento_config = """
        'AndamentoFase' => [
            'tabela'   => 'andamentos_fase',
            'endpoint' => 'AndamentoFase',
            'campos'   => [
                'datajuri_id'                 => 'id',
                'fase_processo_id_datajuri'   => 'faseProcesso.id',
                'processo_id_datajuri'        => 'processo.id',
                'processo_pasta'              => 'processo.pasta',
                'data_andamento'              => 'dataAndamento',
                'descricao'                   => 'descricao',
                'tipo'                        => 'tipo',
                'parecer'                     => 'parecer',
                'parecer_revisado'            => 'parecerRevisado',
                'parecer_revisado_por'        => 'parecerRevisadoPor',
                'data_parecer_revisado'       => 'dataParecerRevisado',
                'proprietario_id'             => 'proprietario.id',
                'proprietario_nome'           => 'proprietario.nome',
            ],
            'upsert_key' => 'datajuri_id',
        ],"""

            # Inserir antes do último ];  do array modulos
            search_terms = ["'OrdemServico'", "'ContasReceber'"]
            for term in search_terms:
                if term in config_content:
                    # Encontrar o final deste bloco
                    idx = config_content.index(term)
                    # Procurar o próximo '],' depois deste módulo
                    bracket_count = 0
                    pos = idx
                    while pos < len(config_content):
                        if config_content[pos] == '[':
                            bracket_count += 1
                        elif config_content[pos] == ']':
                            bracket_count -= 1
                            if bracket_count == 0:
                                # Encontrar o próximo ','
                                comma_pos = config_content.index(',', pos)
                                insert_pos = comma_pos + 1
                                config_content = config_content[:insert_pos] + andamento_config + config_content[insert_pos:]

                                with open(full_config, 'w', encoding='utf-8') as f:
                                    f.write(config_content)
                                print("  ✔ APLICADO: Módulo AndamentoFase no config")
                                break
                        pos += 1
                    break
            else:
                print("  ⚠ NÃO FOI POSSÍVEL INSERIR MÓDULO AUTOMATICAMENTE")
                print("    Adicione manualmente ao array 'modulos' em config/datajuri.php")
    else:
        print(f"  ⚠ ARQUIVO NÃO ENCONTRADO: {config_file}")

    # ─── FINALIZAÇÃO ───
    print("\n" + "=" * 60)
    print("PRÓXIMOS PASSOS (executar manualmente):")
    print("=" * 60)
    print("""
1. Executar migrations:
   php artisan migrate --force

2. Limpar cache:
   php artisan cache:clear && php artisan view:clear && php artisan config:clear && php artisan route:clear

3. Sincronizar novo módulo AndamentoFase:
   php artisan sync:datajuri-completo

4. Verificar no navegador:
   https://intranet.mayeradvogados.adv.br/resultados/bsc/processos-internos

5. Validar dados:
   SELECT 'andamentos_fase' as tabela, COUNT(*) as total FROM andamentos_fase;
""")


if __name__ == '__main__':
    main()
