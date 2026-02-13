#!/usr/bin/env python3
"""
MAYER ALBANEZ — UI REFRESH DEPLOY SCRIPT
Patches layouts/app.blade.php to inject:
  1. Montserrat font from Google Fonts
  2. theme.css import
  
Usage:
  python3 deploy_theme.py /path/to/Intranet

This script is SAFE: it creates backups and only patches if not already patched.
"""
import sys
import os
import shutil
from datetime import datetime

def main():
    if len(sys.argv) < 2:
        base = os.getcwd()
    else:
        base = sys.argv[1]
    
    layout_path = os.path.join(base, 'resources', 'views', 'layouts', 'app.blade.php')
    css_dest = os.path.join(base, 'public', 'css', 'theme.css')
    css_src = os.path.join(base, 'resources', 'css', 'theme.css')
    
    if not os.path.exists(layout_path):
        print(f"ERRO: {layout_path} nao encontrado")
        sys.exit(1)
    
    # Backup
    ts = datetime.now().strftime('%Y%m%d_%H%M%S')
    backup = f"{layout_path}.bak_{ts}"
    shutil.copy2(layout_path, backup)
    print(f"[OK] Backup: {backup}")
    
    # Read layout
    with open(layout_path, 'r', encoding='utf-8') as f:
        content = f.read()
    
    # Check if already patched
    if 'theme.css' in content and 'Montserrat' in content:
        print("[SKIP] Layout ja possui theme.css e Montserrat. Nada a fazer.")
        return
    
    changes = []
    
    # 1. Inject Montserrat font (before </head>)
    montserrat_tag = """
    <!-- Mayer Albanez: Montserrat Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400;1,600&display=swap" rel="stylesheet">
    """
    
    if 'Montserrat' not in content:
        if '</head>' in content:
            content = content.replace('</head>', montserrat_tag + '    </head>', 1)
            changes.append("Montserrat font injected")
        elif '</HEAD>' in content:
            content = content.replace('</HEAD>', montserrat_tag + '    </HEAD>', 1)
            changes.append("Montserrat font injected")
    
    # 2. Inject theme.css link (after Montserrat or before </head>)
    theme_tag = '    <link rel="stylesheet" href="{{ asset(\'css/theme.css\') }}">\n'
    
    if 'theme.css' not in content:
        # Try to inject after the last <link> or before </head>
        if 'display=swap' in content:
            # After Montserrat link
            content = content.replace(
                "rel=\"stylesheet\">\n",
                "rel=\"stylesheet\">\n" + theme_tag,
                1  # Only first occurrence might not work, use a safer approach
            )
            # Safer: inject before </head>
            if 'theme.css' not in content:
                content = content.replace('</head>', theme_tag + '    </head>', 1)
            changes.append("theme.css linked")
        else:
            content = content.replace('</head>', theme_tag + '    </head>', 1)
            changes.append("theme.css linked")
    
    # 3. Copy theme.css to public/css/
    os.makedirs(os.path.dirname(css_dest), exist_ok=True)
    if os.path.exists(css_src):
        shutil.copy2(css_src, css_dest)
        print(f"[OK] theme.css copiado para {css_dest}")
    else:
        print(f"[WARN] {css_src} nao encontrado. Copie manualmente para {css_dest}")
    
    # Write patched layout
    with open(layout_path, 'w', encoding='utf-8') as f:
        f.write(content)
    
    for c in changes:
        print(f"[OK] {c}")
    
    print(f"\n[DONE] Layout atualizado. Backup em: {backup}")
    print("Execute: php artisan view:clear && php artisan cache:clear")

if __name__ == '__main__':
    main()
