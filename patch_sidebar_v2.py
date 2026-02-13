#!/usr/bin/env python3
"""
Patch: Sidebar Logo + Layout + Profundidade
- Substitui logo.png pelo icone SVG branco
- Remove botao de tema (lua)
- Reposiciona toggle da sidebar (discreto, embaixo)
- Adiciona profundidade ao fundo (gradiente navy suave)
"""
import os, sys, shutil
from datetime import datetime

BASE = sys.argv[1] if len(sys.argv) > 1 else '.'
LAYOUT = os.path.join(BASE, 'resources/views/layouts/app.blade.php')
THEME_CSS = os.path.join(BASE, 'public/css/theme.css')
SVG_DEST = os.path.join(BASE, 'public/img/logo-icon-white.svg')

# Backup
ts = datetime.now().strftime('%Y%m%d_%H%M%S')
shutil.copy2(LAYOUT, f'{LAYOUT}.bak_{ts}')
print(f'[OK] Backup: app.blade.php.bak_{ts}')

# 1. Criar SVG branco no public/img/
os.makedirs(os.path.join(BASE, 'public/img'), exist_ok=True)
SVG_CONTENT = '''<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1021.6 952.7">
  <defs><style>.st0{fill:#ffffff;}</style></defs>
  <path class="st0" d="M804.5,243.1c-.2-.1-.5,0-.7.3-13.6,17.1-99.3,124.7-257.1,322.7-1.3,1.6-2.6,3.6-4.2,5.5-36.7,43.9-67.7,81.2-93.1,111.9-5.7,6.8-12.3,10.1-21.2,7.8-2.5-.6-6.5-3.3-12-8.1-2.8-2.4-8.8-7.4-17.9-14.9-3.7-3.1-6.1-5.3-7-6.9-4.5-7.2-4.2-15.7,1.2-22.2,23.1-27.8,46.8-56.4,71.3-85.9.3-.4.3-1,0-1.4L217.4,243.1c-.1-.1-.3-.2-.5,0,0,0-.1.2-.1.3,0,133.3,0,337.2,0,611.5,0,8.5-1.6,17.2-11,19.8-3.1.9-6.2,1.3-9.1,1.3-14.3,0-25.3,0-33,0-4.4,0-8.3-.8-11.9-2.4-7.4-3.3-8.2-12.4-8.2-19.5,0-15.7,0-264.4,0-746,0-8.4,1.4-21.4,7.2-26.8,7.4-6.9,19.7-5.5,27.4.2,2.9,2.2,5.9,4.9,9.1,8.2,5.8,6.2,11.7,12.9,17.5,20.2,5.4,6.7,107.8,135.7,307.4,386.9.3.3.8.4,1.1.1,0,0,0,0,.1-.1,117.7-150.3,217.5-277.8,299.5-382.6,6.5-8.2,12.3-15,19.9-23.4,7.2-8,15-14.8,26.3-14.2,18.6,1,18.6,23.3,18.6,36.6v736.8c0,2.9-.1,5.6-.3,8.2-.4,4.7-.6,7.4-.6,8.2,0,3,0,6,.1,8.9,0,.4-.3.7-.7.7,0,0,0,0,0,0-172.1,0-260.8,0-266.2,0-6.5,0-10.9-.4-13.4-1.4-6-2.5-9.8-7.3-11.4-14.4,0-.4-.1-.8-.1-1.2,0-7.3,0-19.4,0-36.3,0-10.6,8.3-18.7,18.8-18.7,14.5,0,81,0,199.6,0,.4,0,.8-.4.8-.8V243.4c0,0,0-.2-.1-.2Z"/>
  <rect class="st0" x="864.7" y="829.4" width="13.2" height="46.6"/>
</svg>'''
with open(SVG_DEST, 'w') as f:
    f.write(SVG_CONTENT)
print(f'[OK] SVG branco criado: public/img/logo-icon-white.svg')

# 2. Patch do layout
with open(LAYOUT, 'r') as f:
    content = f.read()

# 2a. Substituir bloco do logo + botao tema
OLD_LOGO_BLOCK = '''            <!-- Logo/Nome do Sistema -->
            <div class="sidebar-logo p-6 border-b border-gray-700 flex items-center justify-between">
                <img src="/logo.png" alt="Mayer Albanez" class="h-16 menu-text">
                <!-- Botão de Tema (visível em desktop) -->
                <button id="theme-toggle-btn" class="theme-toggle ml-2 hidden md:block" aria-label="Alternar tema">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24" class="w-5 h-5">
                        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
                    </svg>
                </button>
            </div>'''

NEW_LOGO_BLOCK = '''            <!-- Logo/Nome do Sistema -->
            <div class="sidebar-logo p-5 border-b border-white/10 flex items-center justify-center">
                <img src="/img/logo-icon-white.svg" alt="Mayer Albanez" class="h-10 w-auto menu-text" style="filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3));">
            </div>'''

if OLD_LOGO_BLOCK in content:
    content = content.replace(OLD_LOGO_BLOCK, NEW_LOGO_BLOCK)
    print('[OK] Logo substituido + botao tema removido')
else:
    print('[WARN] Bloco do logo nao encontrado - verificar manualmente')

# 2b. Mover toggle para baixo da sidebar (antes do bloco usuario)
# Remover toggle da posicao atual (logo apos <aside>)
OLD_TOGGLE = '''            <!-- Botão Toggle Sidebar (Desktop only) -->
            <button id="sidebar-toggle-btn"
                    type="button"
                    class="hidden md:block"
                    aria-label="Retrair/Expandir menu lateral"
                    title="Retrair menu">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"></path>
                </svg>
            </button>'''

if OLD_TOGGLE in content:
    content = content.replace(OLD_TOGGLE, '')
    print('[OK] Toggle removido da posicao original')
else:
    print('[WARN] Toggle original nao encontrado')

# Inserir toggle discreto antes do bloco de usuario
OLD_USER_BLOCK = '''            <!-- Usuário -->
            <div class="p-4 border-t border-gray-700">'''

NEW_USER_BLOCK = '''            <!-- Toggle Sidebar (discreto) -->
            <button id="sidebar-toggle-btn"
                    type="button"
                    class="hidden md:flex items-center justify-center w-full py-2 text-white/30 hover:text-white/70 transition-colors"
                    aria-label="Retrair/Expandir menu lateral"
                    title="Retrair menu">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7"></path>
                </svg>
            </button>

            <!-- Usuário -->
            <div class="p-4 border-t border-white/10">'''

if OLD_USER_BLOCK in content:
    content = content.replace(OLD_USER_BLOCK, NEW_USER_BLOCK)
    print('[OK] Toggle reposicionado (discreto, antes do usuario)')
else:
    print('[WARN] Bloco usuario nao encontrado')

# 2c. Atualizar CSS inline do toggle (posicao absoluta -> inline)
OLD_TOGGLE_CSS = '''            /* Botão de toggle */
            #sidebar-toggle-btn {
                position: absolute;
                top: 1.5rem;
                right: -0.75rem;
                z-index: 50;
                background: #1f2937;
                border: 2px solid #374151;
                border-radius: 9999px;
                padding: 0.375rem;
                cursor: pointer;
                transition: all 0.2s;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
                color: white;
            }

            #sidebar-toggle-btn:hover {
                background: #374151;
                transform: scale(1.1);
            }'''

NEW_TOGGLE_CSS = '''            /* Botão de toggle (discreto, inline) */
            #sidebar-toggle-btn {
                cursor: pointer;
                transition: all 0.2s;
            }'''

if OLD_TOGGLE_CSS in content:
    content = content.replace(OLD_TOGGLE_CSS, NEW_TOGGLE_CSS)
    print('[OK] CSS do toggle atualizado (inline)')
else:
    print('[WARN] CSS toggle original nao encontrado')

# 2d. Tooltip backgrounds: atualizar de #1f2937 para navy
content = content.replace(
    "background: #1f2937;\n                color: white;\n                padding: 0.5rem 1rem;",
    "background: #1B334A;\n                color: white;\n                padding: 0.5rem 1rem;"
)
print('[OK] Tooltip bg atualizado para navy')

# 2e. Atualizar border-gray-700 restantes para white/10 na sidebar
content = content.replace('border-t border-gray-700', 'border-t border-white/10')
content = content.replace('border-b border-gray-700', 'border-b border-white/10')
print('[OK] Borders da sidebar atualizados para white/10')

# Salvar layout
with open(LAYOUT, 'w') as f:
    f.write(content)
print('[OK] Layout salvo')

# 3. Patch do theme.css - adicionar profundidade ao fundo + sidebar refinements
DEPTH_CSS = '''

/* ===== PROFUNDIDADE DE FUNDO (v2) ===== */
body, .min-h-screen {
    background: linear-gradient(135deg, #E8ECF1 0%, #D5DCE5 50%, #E0E5EC 100%) !important;
}

#main-content, main.flex-1 {
    background: transparent !important;
}

/* Sidebar glass depth */
#sidebar, aside.sidebar {
    background: linear-gradient(180deg, #1B334A 0%, #152A3D 60%, #0F2030 100%) !important;
    box-shadow: 4px 0 24px rgba(27, 51, 74, 0.3) !important;
}

/* Logo area subtle glow */
.sidebar-logo {
    background: rgba(255,255,255,0.03) !important;
}

/* Cards com subtle shadow para profundidade */
.ma-card, [class*="bg-white"], .bg-white {
    box-shadow: 0 1px 3px rgba(27, 51, 74, 0.08), 0 4px 12px rgba(27, 51, 74, 0.04) !important;
    border: 1px solid rgba(27, 51, 74, 0.06) !important;
}

/* Toggle discreto na sidebar */
#sidebar-toggle-btn {
    border-top: 1px solid rgba(255,255,255,0.06);
}

/* Divisor da sidebar mais sutil */
#sidebar hr, aside.sidebar hr {
    border-color: rgba(255,255,255,0.08) !important;
}

/* Seccao admin label */
#sidebar .text-gray-500 {
    color: rgba(255,255,255,0.35) !important;
}

/* User avatar com brand color */
.w-10.h-10.rounded-full.bg-blue-600 {
    background: #385776 !important;
}

/* ===== FIM PROFUNDIDADE ===== */
'''

with open(THEME_CSS, 'a') as f:
    f.write(DEPTH_CSS)
print('[OK] Profundidade adicionada ao theme.css')

print()
print('=== DEPLOY COMPLETO ===')
print('Executar agora:')
print('  php artisan view:clear && php artisan cache:clear')
print('  Ctrl+Shift+R no navegador')
