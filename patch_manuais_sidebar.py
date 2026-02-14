#!/usr/bin/env python3
"""
Patch: Fix sidebar item 'Manuais Normativos'
- Adiciona classe nav-link (para CSS collapsed funcionar)
- Adiciona data-tooltip (tooltip quando colapsado)
- Envolve texto em <span class="menu-text"> (esconde quando colapsado)
- Padroniza classes visuais com os demais itens da sidebar
"""
import os, sys

BASE = os.path.expanduser("~/domains/mayeradvogados.adv.br/public_html/Intranet")
LAYOUT = os.path.join(BASE, "resources/views/layouts/app.blade.php")

# Ler arquivo
with open(LAYOUT, 'r', encoding='utf-8') as f:
    content = f.read()

# ============================================================
# TRECHO ANTIGO (exato, linhas 238-245)
# ============================================================
OLD = """                    <!-- Manuais Normativos -->
                    <a href="{{ route('manuais-normativos.index') }}"
                       class="flex items-center px-4 py-2.5 text-sm font-medium rounded-lg transition-colors duration-150 {{ request()->is('manuais-normativos*') ? 'bg-blue-50 text-blue-700 dark:bg-blue-900/50 dark:text-blue-300' : 'text-gray-600 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700/50' }}">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                        </svg>
                        Manuais Normativos
                    </a>"""

# ============================================================
# TRECHO NOVO (padronizado com os demais nav-links)
# ============================================================
NEW = """                    <!-- Manuais Normativos -->
                    <a href="{{ route('manuais-normativos.index') }}"
                       class="nav-link flex items-center px-4 py-3 rounded-lg transition-colors {{ request()->is('manuais-normativos*') ? 'nav-link-active' : '' }}"
                       data-tooltip="Manuais Normativos">
                        <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                        </svg>
                        <span class="menu-text">Manuais Normativos</span>
                    </a>"""

if OLD not in content:
    print("[ERRO] Trecho antigo de Manuais Normativos NAO encontrado!")
    print("       O arquivo pode ja ter sido alterado.")
    sys.exit(1)

content = content.replace(OLD, NEW)
print("[OK] Manuais Normativos padronizado (nav-link + data-tooltip + menu-text + flex-shrink-0)")

# Salvar
with open(LAYOUT, 'w', encoding='utf-8') as f:
    f.write(content)

print("[OK] Arquivo salvo: resources/views/layouts/app.blade.php")
print()
print("Executar agora:")
print("  php artisan view:clear && php artisan cache:clear")
