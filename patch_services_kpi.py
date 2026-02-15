#!/usr/bin/env python3
"""
Patch cirúrgico: Integrar KpiMetaHelper nos 3 Services
- DashboardFinanceProdService.php → trocar Configuracao::get por KpiMetaHelper::get
- ClientesMercadoService.php → adicionar 'meta' nos 8 KPIs
- ProcessosInternosService.php → adicionar 'meta' nos 6 cards
"""
import os, sys, shutil

BASE = os.path.expanduser('~/domains/mayeradvogados.adv.br/public_html/Intranet')
SERVICES = os.path.join(BASE, 'app/Services')

errors = []

def patch_file(filepath, replacements, label):
    """Apply a list of (old, new) replacements to a file."""
    full = os.path.join(SERVICES, filepath)
    if not os.path.exists(full):
        errors.append(f"ERRO: {filepath} não encontrado")
        return False

    # Backup
    bak = full + '.bak_kpimeta'
    if not os.path.exists(bak):
        shutil.copy2(full, bak)
        print(f"  Backup: {bak}")

    with open(full, 'r', encoding='utf-8') as f:
        content = f.read()

    original = content
    for i, (old, new) in enumerate(replacements, 1):
        if old not in content:
            errors.append(f"  [{label}] Replacement #{i}: OLD string not found!")
            print(f"  [{label}] ⚠️  Replacement #{i} NOT FOUND — skipping")
            # Show first 80 chars of old for debug
            print(f"    Expected: {repr(old[:120])}")
            continue
        count = content.count(old)
        if count > 1:
            print(f"  [{label}] ⚠️  Replacement #{i} found {count} times — applying first only")
            content = content.replace(old, new, 1)
        else:
            content = content.replace(old, new)
        print(f"  [{label}] ✅ Replacement #{i} applied")

    if content != original:
        with open(full, 'w', encoding='utf-8') as f:
            f.write(content)
        print(f"  [{label}] Arquivo salvo ✅")
        return True
    else:
        print(f"  [{label}] Nenhuma alteração")
        return False


# =========================================================================
# PATCH 1: DashboardFinanceProdService.php
# =========================================================================
print("\n" + "="*70)
print("PATCH 1: DashboardFinanceProdService.php")
print("="*70)

fin_replacements = [
    # 1a. Add use statement
    (
        "use Illuminate\\Support\\Facades\\Schema;",
        "use Illuminate\\Support\\Facades\\Schema;\nuse App\\Helpers\\KpiMetaHelper;"
    ),

    # 1b. Replace meta calculation block (lines ~763-771)
    (
        '        $metaPf = (float) Configuracao::get("meta_pf_{$ano}_{$mes}", 0);\n'
        '        $metaPj = (float) Configuracao::get("meta_pj_{$ano}_{$mes}", 0);\n'
        '        $metaReceita = $metaPf + $metaPj;\n'
        '        $metaDespesas = (float) Configuracao::get("meta_despesas_{$ano}_{$mes}", 0);\n'
        '\n'
        '        $metaResultado = (float) Configuracao::get("meta_resultado_{$ano}_{$mes}", max($metaReceita - $metaDespesas, 0));\n'
        '        $metaMargem = (float) Configuracao::get(\n'
        '            "meta_margem_{$ano}_{$mes}",\n'
        '            $metaReceita > 0 ? round((($metaReceita - $metaDespesas) / $metaReceita) * 100, 1) : 0\n'
        '        );',

        "        $metaPf = (float) KpiMetaHelper::get('receita_pf', $ano, $mes, 0);\n"
        "        $metaPj = (float) KpiMetaHelper::get('receita_pj', $ano, $mes, 0);\n"
        "        $metaReceita = $metaPf + $metaPj;\n"
        "        $metaDespesas = (float) KpiMetaHelper::get('despesas', $ano, $mes, 0);\n"
        "\n"
        "        $metaResultado = (float) KpiMetaHelper::get('resultado_operacional', $ano, $mes, max($metaReceita - $metaDespesas, 0));\n"
        "        $metaMargem = $metaReceita > 0 ? round((($metaReceita - $metaDespesas) / $metaReceita) * 100, 1) : 0;"
    ),

    # 1c. Replace getMetasMensais method to use KpiMetaHelper
    (
        "    private function getMetasMensais(string $tipo, int $ano): array\n"
        "    {\n"
        "        $metas = [];\n"
        "        for ($m = 1; $m <= 12; $m++) {\n"
        '            $metas[$m] = (float) Configuracao::get("{$tipo}_{$ano}_{$m}", 0);\n'
        "        }\n"
        "        return $metas;\n"
        "    }",

        "    private function getMetasMensais(string $tipo, int $ano): array\n"
        "    {\n"
        "        $keyMap = ['meta_pf' => 'receita_pf', 'meta_pj' => 'receita_pj'];\n"
        "        $kpiKey = $keyMap[$tipo] ?? $tipo;\n"
        "        $metas = [];\n"
        "        for ($m = 1; $m <= 12; $m++) {\n"
        "            $metas[$m] = (float) KpiMetaHelper::get($kpiKey, $ano, $m, 0);\n"
        "        }\n"
        "        return $metas;\n"
        "    }"
    ),
]

patch_file('DashboardFinanceProdService.php', fin_replacements, 'FINANCEIRO')


# =========================================================================
# PATCH 2: ClientesMercadoService.php
# =========================================================================
print("\n" + "="*70)
print("PATCH 2: ClientesMercadoService.php")
print("="*70)

cli_replacements = [
    # 2a. Add use statement
    (
        "use Carbon\\Carbon;",
        "use Carbon\\Carbon;\nuse App\\Helpers\\KpiMetaHelper;"
    ),

    # 2b. leads_novos: add meta after subtitle
    (
        "                'subtitle' => $leadsNovos > 0 ? 'Acumulado mês atual' : 'Nenhum lead no período',\n"
        "            ],",
        "                'subtitle' => $leadsNovos > 0 ? 'Acumulado mês atual' : 'Nenhum lead no período',\n"
        "                'meta' => KpiMetaHelper::get('leads_novos', $refDate->year, $refDate->month, 0),\n"
        "            ],"
    ),

    # 2c. oportunidades_ganhas: add meta after subtitle
    (
        "                'subtitle' => $opsGanhas > 0 ? 'Fechamentos confirmados' : 'Sem fechamentos no mês',\n"
        "            ],",
        "                'subtitle' => $opsGanhas > 0 ? 'Fechamentos confirmados' : 'Sem fechamentos no mês',\n"
        "                'meta' => KpiMetaHelper::get('oportunidades_ganhas', $refDate->year, $refDate->month, 0),\n"
        "            ],"
    ),

    # 2d. clientes_ativos: add meta (no subtitle, ends with 'formato' => 'numero')
    (
        "                'cor' => 'purple',\n"
        "                'formato' => 'numero'\n"
        "            ],\n"
        "            'valor_ganho'",
        "                'cor' => 'purple',\n"
        "                'formato' => 'numero',\n"
        "                'meta' => KpiMetaHelper::get('clientes_ativos', $refDate->year, $refDate->month, 0),\n"
        "            ],\n"
        "            'valor_ganho'"
    ),

    # 2e. valor_ganho: add meta (last KPI in kpis_principais, ends with 'moeda' then ] then ];)
    (
        "                'cor' => 'green',\n"
        "                'formato' => 'moeda'\n"
        "            ]\n"
        "        ];",
        "                'cor' => 'green',\n"
        "                'formato' => 'moeda',\n"
        "                'meta' => KpiMetaHelper::get('valor_ganho', $refDate->year, $refDate->month, 0),\n"
        "            ]\n"
        "        ];"
    ),

    # 2f. taxa_conversao: add meta after detalhe (secondary KPIs)
    (
        '                \'detalhe\' => "{$leadsConvertidos} de {$leadsNovos} leads"\n'
        "            ],\n"
        "            'ticket_medio'",
        '                \'detalhe\' => "{$leadsConvertidos} de {$leadsNovos} leads",\n'
        "                'meta' => 0,\n"
        "            ],\n"
        "            'ticket_medio'"
    ),

    # 2g. ticket_medio: add meta
    (
        "                'label' => 'Ticket Médio',\n"
        "                'icon' => '🎫',\n"
        "                'cor' => 'blue',\n"
        "                'formato' => 'moeda'\n"
        "            ],\n"
        "            'pipeline_aberto'",
        "                'label' => 'Ticket Médio',\n"
        "                'icon' => '🎫',\n"
        "                'cor' => 'blue',\n"
        "                'formato' => 'moeda',\n"
        "                'meta' => 0,\n"
        "            ],\n"
        "            'pipeline_aberto'"
    ),

    # 2h. pipeline_aberto: add meta
    (
        "                'label' => 'Pipeline Aberto',\n"
        "                'icon' => '📈',\n"
        "                'cor' => 'purple',\n"
        "                'formato' => 'moeda'\n"
        "            ],\n"
        "            'win_rate'",
        "                'label' => 'Pipeline Aberto',\n"
        "                'icon' => '📈',\n"
        "                'cor' => 'purple',\n"
        "                'formato' => 'moeda',\n"
        "                'meta' => 0,\n"
        "            ],\n"
        "            'win_rate'"
    ),

    # 2i. win_rate: add meta (last KPI in secundarios)
    (
        '                \'detalhe\' => "{$opsGanhasMes} de {$totalFechadas} fechadas"\n'
        "            ]\n"
        "        ];",
        '                \'detalhe\' => "{$opsGanhasMes} de {$totalFechadas} fechadas",\n'
        "                'meta' => KpiMetaHelper::get('win_rate', $refDate->year, $refDate->month, 0),\n"
        "            ]\n"
        "        ];"
    ),
]

patch_file('ClientesMercadoService.php', cli_replacements, 'CLIENTES')


# =========================================================================
# PATCH 3: ProcessosInternosService.php
# =========================================================================
print("\n" + "="*70)
print("PATCH 3: ProcessosInternosService.php")
print("="*70)

proc_replacements = [
    # 3a. Add use statement
    (
        "use Illuminate\\Support\\Carbon;",
        "use Illuminate\\Support\\Carbon;\nuse App\\Helpers\\KpiMetaHelper;"
    ),

    # 3b. Add $ano/$mes extraction before return and replace SLA meta
    (
        "        $horasAnterior = $this->calcHoras($periodoAnterior, $filtros);\n"
        "\n"
        "        return [\n"
        "            'sla' => [\n"
        "                'id'        => 'sla',\n"
        "                'titulo'    => 'SLA (no prazo)',\n"
        "                'valor'     => $slaAtual,\n"
        "                'formato'   => 'percent',\n"
        "                'variacao'  => $this->variacao($slaAtual, $slaAnterior),\n"
        "                'icon'      => '⏱️',\n"
        "                'accent'    => $slaAtual >= 80 ? 'green' : ($slaAtual >= 60 ? 'yellow' : 'red'),\n"
        "                'meta'      => 80,\n"
        "            ],",

        "        $horasAnterior = $this->calcHoras($periodoAnterior, $filtros);\n"
        "\n"
        "        $_kpiAno = (int) $periodo['fim']->year;\n"
        "        $_kpiMes = (int) $periodo['fim']->month;\n"
        "\n"
        "        return [\n"
        "            'sla' => [\n"
        "                'id'        => 'sla',\n"
        "                'titulo'    => 'SLA (no prazo)',\n"
        "                'valor'     => $slaAtual,\n"
        "                'formato'   => 'percent',\n"
        "                'variacao'  => $this->variacao($slaAtual, $slaAnterior),\n"
        "                'icon'      => '⏱️',\n"
        "                'accent'    => $slaAtual >= 80 ? 'green' : ($slaAtual >= 60 ? 'yellow' : 'red'),\n"
        "                'meta'      => KpiMetaHelper::get('sla_percentual', $_kpiAno, $_kpiMes, 80),\n"
        "            ],"
    ),

    # 3c. backlog: add meta
    (
        "                'accent'    => $backlog['total'] == 0 ? 'green' : ($backlog['total'] <= 10 ? 'yellow' : 'red'),\n"
        "            ],\n"
        "            'wip'",
        "                'accent'    => $backlog['total'] == 0 ? 'green' : ($backlog['total'] <= 10 ? 'yellow' : 'red'),\n"
        "                'meta'      => KpiMetaHelper::get('backlog', $_kpiAno, $_kpiMes, 0),\n"
        "            ],\n"
        "            'wip'"
    ),

    # 3d. wip: no meta in DB, add 0
    (
        "                'icon'      => '🔄',\n"
        "                'accent'    => 'blue',\n"
        "            ],\n"
        "            'sem_andamento'",
        "                'icon'      => '🔄',\n"
        "                'accent'    => 'blue',\n"
        "                'meta'      => 0,\n"
        "            ],\n"
        "            'sem_andamento'"
    ),

    # 3e. sem_andamento: add meta (maps to 'sem_movimentacao' in DB)
    (
        "                'accent'    => $semAndamento == 0 ? 'green' : ($semAndamento <= 5 ? 'yellow' : 'red'),\n"
        "            ],\n"
        "            'throughput'",
        "                'accent'    => $semAndamento == 0 ? 'green' : ($semAndamento <= 5 ? 'yellow' : 'red'),\n"
        "                'meta'      => KpiMetaHelper::get('sem_movimentacao', $_kpiAno, $_kpiMes, 0),\n"
        "            ],\n"
        "            'throughput'"
    ),

    # 3f. throughput: add meta
    (
        "                'variacao'  => $this->variacao($throughputAtual, $throughputAnterior),\n"
        "                'icon'      => '✅',\n"
        "                'accent'    => 'green',\n"
        "            ],\n"
        "            'horas'",
        "                'variacao'  => $this->variacao($throughputAtual, $throughputAnterior),\n"
        "                'icon'      => '✅',\n"
        "                'accent'    => 'green',\n"
        "                'meta'      => KpiMetaHelper::get('throughput', $_kpiAno, $_kpiMes, 0),\n"
        "            ],\n"
        "            'horas'"
    ),

    # 3g. horas: add meta (last card, ends with ],\n        ];)
    (
        "                'variacao'  => $this->variacao($horasAtual, $horasAnterior),\n"
        "                'icon'      => '🕐',\n"
        "                'accent'    => 'purple',\n"
        "            ],\n"
        "        ];",
        "                'variacao'  => $this->variacao($horasAtual, $horasAnterior),\n"
        "                'icon'      => '🕐',\n"
        "                'accent'    => 'purple',\n"
        "                'meta'      => KpiMetaHelper::get('horas_trabalhadas', $_kpiAno, $_kpiMes, 0),\n"
        "            ],\n"
        "        ];"
    ),
]

patch_file('ProcessosInternosService.php', proc_replacements, 'PROCESSOS')


# =========================================================================
# VALIDATION
# =========================================================================
print("\n" + "="*70)
print("VALIDAÇÃO")
print("="*70)

# Check syntax of all 3 files
import subprocess
for f in ['DashboardFinanceProdService.php', 'ClientesMercadoService.php', 'ProcessosInternosService.php']:
    full = os.path.join(SERVICES, f)
    result = subprocess.run(['php', '-l', full], capture_output=True, text=True)
    status = '✅' if 'No syntax errors' in result.stdout else '❌'
    print(f"  {status} Syntax check: {f}")
    if 'No syntax errors' not in result.stdout:
        print(f"    {result.stdout.strip()}")
        errors.append(f"SYNTAX ERROR em {f}: {result.stdout.strip()}")

# Verify KpiMetaHelper references
for f in ['DashboardFinanceProdService.php', 'ClientesMercadoService.php', 'ProcessosInternosService.php']:
    full = os.path.join(SERVICES, f)
    with open(full, 'r') as fh:
        content = fh.read()
    count_helper = content.count('KpiMetaHelper')
    count_meta_key = content.count("'meta'")
    print(f"  {f}: KpiMetaHelper={count_helper}x, 'meta'={count_meta_key}x")

if errors:
    print(f"\n⚠️  {len(errors)} AVISOS:")
    for e in errors:
        print(f"  - {e}")
else:
    print("\n✅ TODOS OS PATCHES APLICADOS COM SUCESSO!")

print("\nPróximos passos:")
print("  php artisan config:clear && php artisan cache:clear && php artisan view:clear && php artisan route:clear")
print("  Testar os 3 dashboards no browser")
