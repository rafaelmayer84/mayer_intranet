# UI REFRESH — IMPLEMENTAÇÃO
## Intranet Mayer Albanez | Fevereiro 2026

---

## RESUMO

Refresh visual completo da intranet seguindo o Manual da Marca:
- **Cores:** #1B334A (navy), #385776 (primary/azul médio), #FFFFFF (branco)
- **Fonte:** Montserrat (Google Fonts)
- **Zero alteração** em lógica, rotas, controllers, services, queries

---

## ARQUIVOS DO PACOTE

```
ui-refresh/
├── resources/
│   ├── css/
│   │   └── theme.css                          ← Design tokens + tema global
│   └── views/
│       └── components/
│           ├── card.blade.php                  ← <x-card>
│           ├── kpi-card.blade.php              ← <x-kpi-card>
│           ├── filter-bar.blade.php            ← <x-filter-bar>
│           ├── data-table.blade.php            ← <x-data-table>
│           ├── insights.blade.php              ← <x-insights>
│           ├── page-header.blade.php           ← <x-page-header>
│           └── badge.blade.php                 ← <x-badge>
├── deploy_theme.py                             ← Script de deploy automático
└── IMPLEMENTACAO.md                            ← Este arquivo
```

---

## DEPLOY — PASSO A PASSO

### Passo 0: Backup
```bash
cd ~/domains/mayeradvogados.adv.br/public_html/Intranet
mkdir -p ~/backups/ui-refresh-$(date +%Y%m%d)
cp resources/views/layouts/app.blade.php ~/backups/ui-refresh-$(date +%Y%m%d)/
cp -r resources/views/components/ ~/backups/ui-refresh-$(date +%Y%m%d)/components_old/ 2>/dev/null || true
echo "Backup concluido em ~/backups/ui-refresh-$(date +%Y%m%d)/"
```

### Passo 1: Upload do pacote
Suba `ui-refresh.tar.gz` via hPanel para a raiz do Intranet.

### Passo 2: Extrair e posicionar
```bash
cd ~/domains/mayeradvogados.adv.br/public_html/Intranet
tar -xzvf ui-refresh.tar.gz

# Copiar theme.css para public/css/
mkdir -p public/css
cp resources/css/theme.css public/css/theme.css
echo "[OK] theme.css copiado para public/css/"
```

### Passo 3: Injetar no layout (via Python)
```bash
python3 deploy_theme.py .
```

O script faz:
1. Backup automático de `app.blade.php`
2. Injeta link do Google Fonts (Montserrat)
3. Injeta link do `theme.css`

**OU manualmente** — adicione antes de `</head>` em `resources/views/layouts/app.blade.php`:
```html
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400;1,600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/theme.css') }}">
```

### Passo 4: Limpar cache
```bash
php artisan view:clear && php artisan cache:clear && php artisan config:clear
```

### Passo 5: Verificar
1. Abra a intranet no navegador
2. A sidebar deve estar navy (#1B334A)
3. A fonte deve ser Montserrat
4. Botões azuis devem estar em #385776
5. Scrollbars devem estar discretas

### Passo 6: Commit
```bash
git add resources/css/theme.css public/css/theme.css resources/views/components/ deploy_theme.py
git commit -m "UI Refresh: tema Mayer Albanez + componentes base + Montserrat"
git push origin main
```

---

## O QUE MUDA IMEDIATAMENTE (sem tocar em views)

O `theme.css` usa seletores CSS agressivos que **sobrescrevem** classes Tailwind e hex inline:

| Elemento | Antes | Depois |
|----------|-------|--------|
| **Sidebar fundo** | `bg-gray-800` (cinza genérico) | `#1B334A` (navy da marca) |
| **Sidebar links** | text-gray-300/400 | Branco 65% com hover suave a 95% |
| **Sidebar ativo** | highlight genérico | Pill com borda esquerda branca |
| **Sidebar submenu** | mesmo tom de cinza | Fundo escurecido com radius |
| **Sidebar tooltip** | cinza | Navy com sombra + blur |
| **Botões primários** | bg-blue-600 / bg-indigo-600 | `#385776` (azul marca) |
| **Botões hover** | azul mais escuro | `#2d475f` + sombra suave |
| **Focus rings** | ring-blue-500 | `#385776` com glow suave |
| **Border radius** | rounded-lg (8px) | 16px (moderno) |
| **Scrollbars** | padrão do browser | Finas 6px + discretas |
| **Fonte** | sistema / Inter | **Montserrat** (todos os pesos) |
| **Background geral** | branco puro / gray-50 | `#F4F6F9` (off-white sofisticado) |
| **Text colors** | text-gray-900/800/700/600/500 | Escala navy (#1B334A → #8896A6) |
| **Borders** | border-gray-200/300 | `#E2E8F0` (suave, uniforme) |
| **Cards (bg-white)** | retangulares, sem sombra | Radius 16px + shadow suave |
| **Tabelas** | headers genéricos | Headers uppercase com bg surface |
| **Tables hover** | sem efeito | Highlight suave com transição |
| **Badges (green/yellow/red)** | cores Tailwind raw | Tons suaves com borda |
| **Inputs/selects** | focus azul Tailwind | Focus #385776 + glow rgba |
| **Modals** | overlay preto | Overlay navy blur(4px) |
| **Ícones sidebar** | tamanhos variados | 18x18px consistentes |
| **Transições** | 0ms ou variável | 150ms cubic-bezier global |

---

## COMPONENTES BLADE DISPONÍVEIS

### `<x-card>`
```blade
<x-card title="Título" subtitle="Descrição" accent="#0D9467">
    <p>Conteúdo do card</p>
</x-card>
```

Props: `title`, `subtitle`, `padding` (bool), `accent` (cor hex), `class`

### `<x-kpi-card>`
```blade
<x-kpi-card
    label="Receita Total"
    value="R$ 42.158"
    delta="+12.3%"
    delta-type="positive"
    meta="Meta: R$ 50.000"
    accent="success"
/>
```

Props: `label`, `value`, `delta`, `deltaType` (positive/negative), `meta`, `accent` (primary/success/warning/danger/info), `icon` (HTML SVG), `tooltip`

### `<x-filter-bar>`
```blade
<x-filter-bar>
    <select><option>2026</option></select>
    <select><option>Janeiro</option></select>
    <button class="ma-btn ma-btn-primary ma-btn-sm">Filtrar</button>
</x-filter-bar>
```

### `<x-data-table>`
```blade
<x-data-table :headers="['Cliente', 'Valor', 'Status']">
    <tr>
        <td>João Silva</td>
        <td>R$ 5.000</td>
        <td><x-badge type="success">Pago</x-badge></td>
    </tr>
</x-data-table>
```

### `<x-badge>`
```blade
<x-badge type="success" dot>Ativo</x-badge>
<x-badge type="warning">Pendente</x-badge>
<x-badge type="danger">Atrasado</x-badge>
<x-badge type="info">Novo</x-badge>
<x-badge>Padrão</x-badge>
```

Props: `type` (success/warning/danger/info/neutral), `dot` (bool), `size` (md/sm)

### `<x-page-header>`
```blade
<x-page-header title="Dashboard Financeiro" subtitle="Visão executiva de resultados">
    <button class="ma-btn ma-btn-primary">
        <svg class="w-4 h-4">...</svg>
        Exportar
    </button>
</x-page-header>
```

### `<x-insights>`
```blade
<x-insights :items="[
    ['level' => 'positive', 'text' => 'Receita 12% acima da meta'],
    ['level' => 'attention', 'text' => 'Inadimplência acima de 5%'],
    ['level' => 'critical', 'text' => '3 processos sem movimentação há 90 dias'],
]" />
```

---

## CLASSES CSS GLOBAIS DISPONÍVEIS

### Botões
```html
<button class="ma-btn ma-btn-primary">Primário</button>
<button class="ma-btn ma-btn-secondary">Secundário</button>
<button class="ma-btn ma-btn-ghost">Ghost</button>
<button class="ma-btn ma-btn-danger">Perigo</button>
<button class="ma-btn ma-btn-primary ma-btn-sm">Pequeno</button>
```

### Cards
```html
<div class="ma-card">
    <div class="ma-card-header">Título</div>
    <div class="ma-card-body">Conteúdo</div>
    <div class="ma-card-footer">Rodapé</div>
</div>
```

### Tabelas
```html
<table class="ma-table">
    <thead><tr><th>Col</th></tr></thead>
    <tbody><tr><td>Dado</td></tr></tbody>
</table>
```

### Alerts
```html
<div class="ma-alert ma-alert--success">Operação concluída</div>
<div class="ma-alert ma-alert--warning">Atenção necessária</div>
<div class="ma-alert ma-alert--danger">Erro encontrado</div>
```

### Tabs
```html
<div class="ma-tabs">
    <button class="ma-tab ma-tab--active">Aba 1</button>
    <button class="ma-tab">Aba 2</button>
</div>
```

### Formulários
```html
<label class="ma-label">Campo</label>
<input class="ma-input" placeholder="Digite...">
```

### Chart container
```html
<div class="ma-chart-container">
    <canvas id="meuGrafico"></canvas>
</div>
```

---

## ROLLBACK

Se algo quebrar:
```bash
cd ~/domains/mayeradvogados.adv.br/public_html/Intranet

# Restaurar layout original
cp ~/backups/ui-refresh-YYYYMMDD/app.blade.php resources/views/layouts/app.blade.php

# Remover theme.css
rm public/css/theme.css

# Limpar cache
php artisan view:clear && php artisan cache:clear

# Pronto: sistema volta ao visual anterior
```

---

## CHECKLIST VISUAL

- [ ] Sidebar nova (navy #1B334A, links claros, active com pill)
- [ ] Tipografia Montserrat em toda a interface
- [ ] Background geral off-white (#F4F6F9)
- [ ] Botões padronizados (#385776 primary)
- [ ] Focus rings na cor da marca
- [ ] Scrollbars discretas
- [ ] Border radius 16px nos cards
- [ ] Sombras sutis com tom navy
- [ ] Badges com cores suaves
- [ ] Tabs com borda inferior na cor da marca
- [ ] Componentes Blade disponíveis (x-card, x-kpi-card, etc.)
- [ ] Nenhum módulo quebrado
- [ ] Nenhum KPI removido
- [ ] JS existente funcional (IDs/classes preservados)

---

## USO PROGRESSIVO NAS VIEWS

O tema funciona **imediatamente** sem alterar views (via overrides CSS). Para maximizar o efeito, substitua progressivamente nas Blade views:

**Prioridade 1 (maior impacto):**
- `dashboard/visao-gerencial.blade.php` — usar `<x-kpi-card>` e `<x-card>`
- `dashboard/clientes-mercado.blade.php` — idem
- `dashboard/processos-internos.blade.php` — idem

**Prioridade 2:**
- `leads/index.blade.php` — usar `<x-data-table>` e `<x-badge>`
- `admin/sincronizacao-unificada/index.blade.php` — idem

**Prioridade 3:**
- `nexo/atendimento/index.blade.php` — já tem CSS customizado, ajustar cores para tokens
- `siric/` e `precificacao/` — idem

Cada view pode ser migrada independentemente, sem afetar as demais.

---

**Gerado em:** 13/02/2026
**Autor:** Claude (Assistente IA)
**Versão:** 1.0
