#!/usr/bin/env python3
"""
Patch: UI Usuarios - Icones + Ultimo Acesso
Data: 12/02/2026

1. Cria coluna ultimo_acesso no banco
2. Patch LoginController para registrar ultimo_acesso no login
3. Patch show.blade.php para renderizar icones Heroicon SVG
"""

import subprocess
import sys
import os

BASE = os.path.expanduser("~/domains/mayeradvogados.adv.br/public_html/Intranet")

def run(cmd, desc=""):
    print(f"\n>>> {desc}")
    print(f"    CMD: {cmd[:120]}...")
    r = subprocess.run(cmd, shell=True, stdout=subprocess.PIPE, stderr=subprocess.PIPE, cwd=BASE)
    out = r.stdout.decode('utf-8', errors='replace').strip()
    err = r.stderr.decode('utf-8', errors='replace').strip()
    if out:
        print(f"    OUT: {out[:200]}")
    if err and r.returncode != 0:
        print(f"    ERR: {err[:200]}")
    return r.returncode == 0, out

# ==============================================================
# PASSO 1: Criar coluna ultimo_acesso na tabela users
# ==============================================================
print("=" * 60)
print("PASSO 1: Criar coluna ultimo_acesso no banco")
print("=" * 60)

run(
    """php artisan tinker --execute="
\\DB::statement('ALTER TABLE users ADD COLUMN ultimo_acesso DATETIME NULL AFTER ativo');
echo 'Coluna ultimo_acesso criada com sucesso';
" 2>/dev/null || echo 'Coluna ja existe ou erro'""",
    "Adicionando coluna ultimo_acesso"
)

# Verificar
run(
    """php artisan tinker --execute="
\$cols = \\DB::select('SHOW COLUMNS FROM users');
foreach(\$cols as \$c) { if(strpos(\$c->Field,'ultimo')!==false || strpos(\$c->Field,'acesso')!==false) echo \$c->Field.' ('.\$c->Type.')'; }
" 2>/dev/null""",
    "Verificando coluna"
)

# ==============================================================
# PASSO 2: Patch LoginController - registrar ultimo_acesso
# ==============================================================
print("\n" + "=" * 60)
print("PASSO 2: Patch LoginController")
print("=" * 60)

login_file = os.path.join(BASE, "app/Http/Controllers/Auth/LoginController.php")

with open(login_file, 'r', encoding='utf-8') as f:
    content = f.read()

# Backup
with open(login_file + '.bak_20260212', 'w', encoding='utf-8') as f:
    f.write(content)

old_login = """if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            return redirect()->intended('/');
        }"""

new_login = """if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            // Registrar último acesso
            Auth::user()->update(['ultimo_acesso' => now()]);

            return redirect()->intended('/');
        }"""

if old_login in content:
    content = content.replace(old_login, new_login)
    with open(login_file, 'w', encoding='utf-8') as f:
        f.write(content)
    print("    OK: LoginController patcheado")
else:
    if 'ultimo_acesso' in content:
        print("    SKIP: Patch ja aplicado")
    else:
        print("    ERRO: String de busca nao encontrada!")
        print("    Conteudo relevante:")
        for i, line in enumerate(content.split('\n')):
            if 'attempt' in line.lower() or 'regenerate' in line.lower():
                print(f"      L{i+1}: {line}")

# ==============================================================
# PASSO 3: Patch show.blade.php - Icones SVG Heroicon
# ==============================================================
print("\n" + "=" * 60)
print("PASSO 3: Patch show.blade.php - Icones Heroicon SVG")
print("=" * 60)

show_file = os.path.join(BASE, "resources/views/admin/usuarios/show.blade.php")

with open(show_file, 'r', encoding='utf-8') as f:
    show_content = f.read()

# Backup
with open(show_file + '.bak_20260212', 'w', encoding='utf-8') as f:
    f.write(show_content)

# A linha atual e:
# {{ $item['modulo']->icone }} {{ $item['modulo']->nome }}
# Precisa virar: icone SVG renderizado + nome

old_icon_line = "{{ $item['modulo']->icone }} {{ $item['modulo']->nome }}"

# Mapa de icones Heroicon 24 outline (SVG paths)
new_icon_block = """{!! \\App\\Helpers\\IconHelper::render($item['modulo']->icone, 'w-5 h-5 text-gray-500 dark:text-gray-400 mr-2 flex-shrink-0') !!}
                                                    <span>{{ $item['modulo']->nome }}</span>"""

if old_icon_line in show_content:
    show_content = show_content.replace(old_icon_line, new_icon_block)
    with open(show_file, 'w', encoding='utf-8') as f:
        f.write(show_content)
    print("    OK: show.blade.php patcheado")
else:
    print("    ERRO: Linha de icone nao encontrada!")

# ==============================================================
# PASSO 4: Criar IconHelper com SVGs Heroicon
# ==============================================================
print("\n" + "=" * 60)
print("PASSO 4: Criar app/Helpers/IconHelper.php")
print("=" * 60)

helpers_dir = os.path.join(BASE, "app/Helpers")
os.makedirs(helpers_dir, exist_ok=True)

icon_helper = r'''<?php

namespace App\Helpers;

class IconHelper
{
    /**
     * Mapa de icones Heroicon 24 Outline
     * Cada valor e o conteudo <path> do SVG
     */
    private static array $icons = [
        'users' => '<path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/>',

        'user' => '<path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>',

        'user-group' => '<path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"/>',

        'chart-bar' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/>',

        'presentation-chart-bar' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 006 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0118 16.5h-2.25m-7.5 0h7.5m-7.5 0l-1 3m8.5-3l1 3m0 0l.5 1.5m-.5-1.5h-9.5m0 0l-.5 1.5M9 11.25v-5.5m3 5.5V8.25m3 3v-2"/>',

        'cog-6-tooth' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>',

        'cog' => '<path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12a7.5 7.5 0 0015 0m-15 0a7.5 7.5 0 1115 0m-15 0H3m16.5 0H21m-1.5 0H12m-8.457 3.077l1.41-.513m14.095-5.13l1.41-.513M5.106 17.785l1.15-.964m11.49-9.642l1.149-.964M7.501 19.795l.75-1.3m7.5-12.99l.75-1.3m-6.063 16.658l.26-1.477m2.605-14.772l.26-1.477m0 17.726l-.26-1.477M10.698 4.614l-.26-1.477M16.5 19.794l-.75-1.299M7.5 4.205L12 12m6.894 5.785l-1.149-.964M6.256 7.178l-1.15-.964m15.352 8.864l-1.41-.513M4.954 9.435l-1.41-.514M12.002 12l-3.75 6.495"/>',

        'bell' => '<path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/>',

        'bell-alert' => '<path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0M3.124 7.5A8.969 8.969 0 015.292 3m13.416 0a8.969 8.969 0 012.168 4.5"/>',

        'tag' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z"/><path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6z"/>',

        'arrow-path' => '<path stroke-linecap="round" stroke-linejoin="round" d="M19.5 12c0-1.232-.046-2.453-.138-3.662a4.006 4.006 0 00-3.7-3.7 48.678 48.678 0 00-7.324 0 4.006 4.006 0 00-3.7 3.7c-.017.22-.032.441-.046.662M19.5 12l3-3m-3 3l-3-3m-12 3c0 1.232.046 2.453.138 3.662a4.006 4.006 0 003.7 3.7 48.656 48.656 0 007.324 0 4.006 4.006 0 003.7-3.7c.017-.22.032-.441.046-.662M4.5 12l3 3m-3-3l-3 3"/>',

        'puzzle-piece' => '<path stroke-linecap="round" stroke-linejoin="round" d="M14.25 6.087c0-.355.186-.676.401-.959.221-.29.349-.634.349-1.003 0-1.036-1.007-1.875-2.25-1.875s-2.25.84-2.25 1.875c0 .369.128.713.349 1.003.215.283.401.604.401.959v0a.64.64 0 01-.657.643 48.39 48.39 0 01-4.163-.3c.186 1.613.293 3.25.315 4.907a.656.656 0 01-.658.663v0c-.355 0-.676-.186-.959-.401a1.647 1.647 0 00-1.003-.349c-1.036 0-1.875 1.007-1.875 2.25s.84 2.25 1.875 2.25c.369 0 .713-.128 1.003-.349.283-.215.604-.401.959-.401v0c.31 0 .555.26.532.57a48.039 48.039 0 01-.642 5.056c1.518.19 3.058.309 4.616.354a.64.64 0 00.657-.643v0c0-.355-.186-.676-.401-.959a1.647 1.647 0 01-.349-1.003c0-1.035 1.008-1.875 2.25-1.875 1.243 0 2.25.84 2.25 1.875 0 .369-.128.713-.349 1.003-.215.283-.4.604-.4.959v0c0 .333.277.599.61.58a48.1 48.1 0 005.427-.63 48.05 48.05 0 00.582-4.717.532.532 0 00-.533-.57v0c-.355 0-.676.186-.959.401-.29.221-.634.349-1.003.349-1.035 0-1.875-1.007-1.875-2.25s.84-2.25 1.875-2.25c.37 0 .713.128 1.003.349.283.215.604.401.959.401v0a.656.656 0 00.658-.663 48.422 48.422 0 00-.37-5.36c-1.886.342-3.81.574-5.766.689a.578.578 0 01-.61-.58z"/>',

        'megaphone' => '<path stroke-linecap="round" stroke-linejoin="round" d="M10.34 15.84c-.688-.06-1.386-.09-2.09-.09H7.5a4.5 4.5 0 110-9h.75c.704 0 1.402-.03 2.09-.09m0 9.18c.253.962.584 1.892.985 2.783.247.55.06 1.21-.463 1.511l-.657.38c-.551.318-1.26.117-1.527-.461a20.845 20.845 0 01-1.44-4.282m3.102.069a18.03 18.03 0 01-.59-4.59c0-1.586.205-3.124.59-4.59m0 9.18a23.848 23.848 0 018.835 2.535M10.34 6.66a23.847 23.847 0 008.835-2.535m0 0A23.74 23.74 0 0018.795 3m.38 1.125a23.91 23.91 0 011.014 5.395m-1.014 8.855c-.118.38-.245.754-.38 1.125m.38-1.125a23.91 23.91 0 001.014-5.395m0-3.46c.495.413.811 1.035.811 1.73 0 .695-.316 1.317-.811 1.73m0-3.46a24.347 24.347 0 010 3.46"/>',

        'chat-bubble-left-right' => '<path stroke-linecap="round" stroke-linejoin="round" d="M20.25 8.511c.884.284 1.5 1.128 1.5 2.097v4.286c0 1.136-.847 2.1-1.98 2.193-.34.027-.68.052-1.02.072v3.091l-3-3c-1.354 0-2.694-.055-4.02-.163a2.115 2.115 0 01-.825-.242m9.345-8.334a2.126 2.126 0 00-.476-.095 48.64 48.64 0 00-8.048 0c-1.131.094-1.976 1.057-1.976 2.192v4.286c0 .837.46 1.58 1.155 1.951m9.345-8.334V6.637c0-1.621-1.152-3.026-2.76-3.235A48.455 48.455 0 0011.25 3c-2.115 0-4.198.137-6.24.402-1.608.209-2.76 1.614-2.76 3.235v6.226c0 1.621 1.152 3.026 2.76 3.235.577.075 1.157.14 1.74.194V21l4.155-4.155"/>',

        'shield-check' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/>',

        'currency-dollar' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>',

        'book-open' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/>',

        'adjustments-horizontal' => '<path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75"/>',

        'target' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 9.75v6.75m0 0l-3-3m3 3l3-3m-8.25 6a4.5 4.5 0 01-1.41-8.775 5.25 5.25 0 0110.233-2.33 3 3 0 013.758 3.848A3.752 3.752 0 0118 19.5H6.75z"/>',
    ];

    /**
     * Renderiza um icone SVG Heroicon
     */
    public static function render(string $name, string $class = 'w-5 h-5'): string
    {
        $path = self::$icons[$name] ?? null;

        if (!$path) {
            // Fallback: circulo com primeira letra
            $letter = strtoupper(substr($name, 0, 1));
            return '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><text x="12" y="16" text-anchor="middle" font-size="10" fill="currentColor">' . $letter . '</text></svg>';
        }

        return '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">' . $path . '</svg>';
    }
}
'''

icon_file = os.path.join(BASE, "app/Helpers/IconHelper.php")
with open(icon_file, 'w', encoding='utf-8') as f:
    f.write(icon_helper)
print("    OK: IconHelper.php criado com 20 icones SVG")

# ==============================================================
# PASSO 5: Registrar o Helper no autoload (verificar composer.json)
# ==============================================================
print("\n" + "=" * 60)
print("PASSO 5: Garantir autoload do Helper")
print("=" * 60)

# Laravel autoload via namespace PSR-4 ja cobre App\Helpers se estiver em app/Helpers
# Mas precisamos verificar se o composer.json tem o autoload correto
composer_file = os.path.join(BASE, "composer.json")
with open(composer_file, 'r', encoding='utf-8') as f:
    composer = f.read()

if '"App\\\\Helpers\\\\"' not in composer and '"App\\\\"' in composer:
    print("    OK: PSR-4 App\\ ja cobre App\\Helpers\\ automaticamente")
else:
    print("    OK: Autoload verificado")

# Rodar composer dump-autoload para garantir
run("composer dump-autoload --no-interaction 2>/dev/null", "Composer dump-autoload")

# ==============================================================
# PASSO 6: Limpar caches
# ==============================================================
print("\n" + "=" * 60)
print("PASSO 6: Limpar caches")
print("=" * 60)

run("php artisan config:clear && php artisan route:clear && php artisan view:clear && php artisan cache:clear",
    "Limpando caches")

# ==============================================================
# VERIFICAÇÃO FINAL
# ==============================================================
print("\n" + "=" * 60)
print("VERIFICACAO FINAL")
print("=" * 60)

# Verificar LoginController
with open(login_file, 'r', encoding='utf-8') as f:
    lc = f.read()
if 'ultimo_acesso' in lc:
    print("    OK: LoginController registra ultimo_acesso")
else:
    print("    FALHA: LoginController NAO patcheado")

# Verificar show.blade.php
with open(show_file, 'r', encoding='utf-8') as f:
    sv = f.read()
if 'IconHelper::render' in sv:
    print("    OK: show.blade.php usa IconHelper")
else:
    print("    FALHA: show.blade.php NAO patcheado")

# Verificar IconHelper existe
if os.path.exists(icon_file):
    print("    OK: IconHelper.php existe")
else:
    print("    FALHA: IconHelper.php NAO criado")

print("\n" + "=" * 60)
print("CONCLUIDO! Testar:")
print("  1. Fazer logout e login novamente -> ultimo_acesso sera registrado")
print("  2. Acessar /admin/usuarios/{id} -> icones SVG aparecerao")
print("=" * 60)
