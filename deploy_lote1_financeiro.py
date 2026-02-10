#!/usr/bin/env python3
"""
LOTE 1 — Deploy: KPI Card Universal v2 + Dashboard Financeiro (Waterfall, Pareto, Insights)
Projeto: Intranet Mayer Advogados — Sistema RESULTADOS!
Data: 2026-02-10

USO:
  python3 deploy_lote1_financeiro.py

AÇÕES:
  1. Backup dos arquivos que serão modificados
  2. Substitui _kpi-card.blade.php pela v2 (com sparkline, meta null, status)
  3. Cria partials novos: _insights-financeiro, _charts-financeiro-extra
  4. Patch cirúrgico em visao-gerencial.blade.php para:
     a) Adicionar sparklines nos KPI cards existentes
     b) Incluir os 2 novos gráficos (waterfall + pareto)
     c) Incluir bloco de insights
  5. Patch cirúrgico em DashboardFinanceProdService para adicionar método getSparklineData()
  6. Patch cirúrgico em DashboardController para passar sparklines ao template

ROLLBACK:
  Os backups ficam em .bak_YYYYMMDD_HHMMSS
"""

import os
import sys
import shutil
from datetime import datetime

# ══════════════════════════════════════════════════════════════
# CONFIGURAÇÃO
# ══════════════════════════════════════════════════════════════
BASE = os.path.expanduser("~/domains/mayeradvogados.adv.br/public_html/Intranet")
TIMESTAMP = datetime.now().strftime("%Y%m%d_%H%M%S")

# Caminhos dos arquivos
PATHS = {
    "kpi_card":       f"{BASE}/resources/views/dashboard/partials/_kpi-card.blade.php",
    "insights":       f"{BASE}/resources/views/dashboard/partials/_insights-financeiro.blade.php",
    "charts_extra":   f"{BASE}/resources/views/dashboard/partials/_charts-financeiro-extra.blade.php",
    "visao_gerencial": f"{BASE}/resources/views/dashboard/visao-gerencial.blade.php",
    "service":        f"{BASE}/app/Services/DashboardFinanceProdService.php",
    "controller":     f"{BASE}/app/Http/Controllers/DashboardController.php",
}

# ══════════════════════════════════════════════════════════════
# HELPERS
# ══════════════════════════════════════════════════════════════
def backup(filepath):
    """Cria backup com timestamp."""
    if os.path.exists(filepath):
        bak = f"{filepath}.bak_{TIMESTAMP}"
        shutil.copy2(filepath, bak)
        print(f"  ✅ Backup: {os.path.basename(bak)}")
    else:
        print(f"  ℹ️  Arquivo novo (sem backup): {os.path.basename(filepath)}")

def read_file(filepath):
    """Lê arquivo com encoding adequado."""
    with open(filepath, 'r', encoding='utf-8') as f:
        return f.read()

def write_file(filepath, content):
    """Escreve arquivo."""
    os.makedirs(os.path.dirname(filepath), exist_ok=True)
    with open(filepath, 'w', encoding='utf-8') as f:
        f.write(content)
    print(f"  ✅ Escrito: {os.path.relpath(filepath, BASE)}")

def patch_replace(filepath, old, new, description=""):
    """Substituição cirúrgica em arquivo. Falha se old não encontrado ou encontrado mais de 1 vez."""
    content = read_file(filepath)
    count = content.count(old)
    if count == 0:
        print(f"  ⚠️  SKIP ({description}): trecho não encontrado em {os.path.basename(filepath)}")
        return False
    if count > 1:
        print(f"  ⚠️  SKIP ({description}): trecho encontrado {count}x (esperava 1) em {os.path.basename(filepath)}")
        return False
    content = content.replace(old, new)
    write_file(filepath, content)
    print(f"  ✅ Patch ({description}): {os.path.basename(filepath)}")
    return True

def patch_insert_after(filepath, anchor, insert_text, description=""):
    """Insere texto APÓS um trecho âncora. Falha se âncora não encontrada."""
    content = read_file(filepath)
    if anchor not in content:
        print(f"  ⚠️  SKIP ({description}): âncora não encontrada em {os.path.basename(filepath)}")
        return False
    if insert_text.strip() in content:
        print(f"  ℹ️  SKIP ({description}): já aplicado em {os.path.basename(filepath)}")
        return True
    content = content.replace(anchor, anchor + insert_text)
    write_file(filepath, content)
    print(f"  ✅ Insert after ({description}): {os.path.basename(filepath)}")
    return True

def patch_insert_before(filepath, anchor, insert_text, description=""):
    """Insere texto ANTES de um trecho âncora."""
    content = read_file(filepath)
    if anchor not in content:
        print(f"  ⚠️  SKIP ({description}): âncora não encontrada em {os.path.basename(filepath)}")
        return False
    if insert_text.strip() in content:
        print(f"  ℹ️  SKIP ({description}): já aplicado em {os.path.basename(filepath)}")
        return True
    content = content.replace(anchor, insert_text + anchor)
    write_file(filepath, content)
    print(f"  ✅ Insert before ({description}): {os.path.basename(filepath)}")
    return True

# ══════════════════════════════════════════════════════════════
# PASSO 0: VALIDAÇÃO
# ══════════════════════════════════════════════════════════════
def step0_validate():
    print("\n═══ PASSO 0: Validação do ambiente ═══")
    if not os.path.isdir(BASE):
        print(f"  ❌ Diretório base não encontrado: {BASE}")
        sys.exit(1)
    for key in ["kpi_card", "visao_gerencial", "service", "controller"]:
        if not os.path.exists(PATHS[key]):
            print(f"  ❌ Arquivo obrigatório não encontrado: {PATHS[key]}")
            sys.exit(1)
        print(f"  ✅ {key}: encontrado")
    print("  ✅ Ambiente validado")

# ══════════════════════════════════════════════════════════════
# PASSO 1: BACKUPS
# ══════════════════════════════════════════════════════════════
def step1_backups():
    print("\n═══ PASSO 1: Backups ═══")
    for key in ["kpi_card", "visao_gerencial", "service", "controller"]:
        backup(PATHS[key])

# ══════════════════════════════════════════════════════════════
# PASSO 2: Substituir _kpi-card.blade.php pela v2
# ══════════════════════════════════════════════════════════════
def step2_kpi_card():
    print("\n═══ PASSO 2: KPI Card v2 ═══")
    # O novo arquivo será copiado pelo script SSH (cat heredoc)
    # Aqui verificamos se o conteúdo já é v2
    content = read_file(PATHS["kpi_card"])
    if "sparkline" in content and "sem_meta" in content:
        print("  ℹ️  KPI Card já é v2 (sparkline + sem_meta encontrados)")
        return
    # Será substituído pelo heredoc no script SSH
    print("  ℹ️  KPI Card v1 detectado — será substituído pelo heredoc SSH")

# ══════════════════════════════════════════════════════════════
# PASSO 3: Criar partials novos
# ══════════════════════════════════════════════════════════════
def step3_partials():
    print("\n═══ PASSO 3: Partials novos ═══")
    # Verificar se já existem
    for key in ["insights", "charts_extra"]:
        if os.path.exists(PATHS[key]):
            print(f"  ℹ️  {os.path.basename(PATHS[key])} já existe")
        else:
            print(f"  ℹ️  {os.path.basename(PATHS[key])} será criado pelo heredoc SSH")

# ══════════════════════════════════════════════════════════════
# PASSO 4: Patch visao-gerencial.blade.php
# ══════════════════════════════════════════════════════════════
def step4_visao_gerencial():
    print("\n═══ PASSO 4: Patch visao-gerencial.blade.php ═══")

    vg = PATHS["visao_gerencial"]
    content = read_file(vg)

    # 4a) Adicionar sparklines nos @include do _kpi-card
    # Localizar o primeiro include do _kpi-card (Receita Total) e adicionar sparkline
    # O padrão atual é:
    #   'icon' => '💰'
    #   ])
    # Vamos adicionar 'sparkline' => $sparklines['receita'] ?? null, antes do ])

    # Detectar se já tem sparkline
    if "'sparkline'" in content:
        print("  ℹ️  Sparklines já aplicados na view")
    else:
        # Patch 4a-1: Receita Total
        patch_insert_before(vg,
            """            'trend' => $resumo['receitaTrend'] ?? 0,
            'accent' => 'green',
            'icon' => '💰'""",
            """            'sparkline' => $sparklines['receita'] ?? null,
""",
            "sparkline Receita"
        )

        # Re-read after patch
        content = read_file(vg)

        # Patch 4a-2: Despesas Totais
        patch_insert_before(vg,
            """            'trend' => $resumo['despesasTrend'] ?? 0,
            'accent' => 'blue',
            'icon' => '📊'""",
            """            'sparkline' => $sparklines['despesas'] ?? null,
            'invertTrend' => true,
""",
            "sparkline Despesas"
        )

    # 4b) Incluir partials de waterfall+pareto e insights ANTES do rodapé
    # Procurar uma âncora confiável no final da view antes do @endsection ou scripts
    content = read_file(vg)

    # Inserir gráficos extras e insights
    insert_charts = """
    {{-- ═══ Gráficos Adicionais: Waterfall DRE + Pareto Inadimplência ═══ --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        @include('dashboard.partials._charts-financeiro-extra', ['d' => $dashboardData ?? []])
    </div>

    {{-- ═══ Insights Automáticos ═══ --}}
    @include('dashboard.partials._insights-financeiro', ['d' => $dashboardData ?? []])

"""
    if "_charts-financeiro-extra" not in content:
        # Encontrar um bom ponto de inserção — depois dos gráficos existentes
        # Usar o closing </div> do dashboard-root como âncora
        # Procurar padrão: bloco de warnings ou último grid de gráficos
        # Vamos inserir antes do @endsection
        if "@endsection" in content:
            patch_insert_before(vg, "@endsection", insert_charts, "incluir gráficos + insights")
        else:
            print("  ⚠️  @endsection não encontrado na view")
    else:
        print("  ℹ️  Gráficos extras já incluídos na view")

# ══════════════════════════════════════════════════════════════
# PASSO 5: Patch Service — adicionar getSparklineData()
# ══════════════════════════════════════════════════════════════
def step5_service():
    print("\n═══ PASSO 5: Patch DashboardFinanceProdService ═══")

    svc = PATHS["service"]
    content = read_file(svc)

    if "getSparklineData" in content:
        print("  ℹ️  getSparklineData() já existe no service")
        return

    # Inserir o método antes do último } do arquivo
    new_method = """
    /**
     * Dados para sparklines dos KPI cards (12 meses).
     * Retorna array com séries nomeadas, cada uma com 12 valores.
     *
     * @return array<string, array<int,float>>
     */
    public function getSparklineData(int $ano): array
    {
        $cacheKey = "dash_fin_sparklines:{$ano}";
        return Cache::remember($cacheKey, 3600, function () use ($ano) {
            $receitas = $this->getReceitaByMonth($ano);
            $despesas = $this->despesasOperacionaisByMonth($ano);
            $deducoes = $this->deducoesByMonth($ano);
            $lucro = $this->getLucratividadeByMonth($ano);

            // Receita total por mês = PF + PJ
            $receitaTotal = [];
            for ($i = 0; $i < 12; $i++) {
                $receitaTotal[$i] = round(($receitas['pf'][$i] ?? 0) + ($receitas['pj'][$i] ?? 0), 2);
            }

            // Margem por mês
            $margem = [];
            for ($i = 0; $i < 12; $i++) {
                $rt = $receitaTotal[$i];
                $l = $lucro['lucratividade'][$i] ?? 0;
                $margem[$i] = $rt > 0 ? round(($l / $rt) * 100, 1) : 0;
            }

            return [
                'receita'  => $receitaTotal,
                'despesas' => $despesas,
                'resultado' => $lucro['lucratividade'] ?? array_fill(0, 12, 0),
                'margem'   => $margem,
            ];
        });
    }
"""

    # Encontrar a última chave de fechamento da classe
    last_brace = content.rfind("}")
    if last_brace == -1:
        print("  ❌ Não encontrou } final da classe")
        return

    content = content[:last_brace] + new_method + "\n}\n"
    write_file(svc, content)
    print("  ✅ getSparklineData() adicionado ao service")

# ══════════════════════════════════════════════════════════════
# PASSO 6: Patch Controller — passar sparklines para a view
# ══════════════════════════════════════════════════════════════
def step6_controller():
    print("\n═══ PASSO 6: Patch DashboardController ═══")

    ctrl = PATHS["controller"]
    content = read_file(ctrl)

    if "sparklines" in content and "getSparklineData" in content:
        print("  ℹ️  sparklines já passados no controller")
        return

    # Primeiro, verificar se existe o método visaoGerencial
    if "function visaoGerencial" not in content:
        print("  ⚠️  método visaoGerencial não encontrado")
        return

    # Descobrir o nome da propriedade do service
    # Procurar padrões: $this->xxxService->getDashboardData ou similar
    import re
    svc_match = re.search(r'\$this->(\w+)->getDashboardData', content)
    if svc_match:
        svc_prop = svc_match.group(1)
        print(f"  ✅ Service property encontrada: $this->{svc_prop}")
    else:
        # Fallback: procurar qualquer propriedade DashboardFinanceProd
        svc_match2 = re.search(r'private\s+DashboardFinanceProdService\s+\$(\w+)', content)
        if not svc_match2:
            svc_match2 = re.search(r'protected\s+DashboardFinanceProdService\s+\$(\w+)', content)
        if svc_match2:
            svc_prop = svc_match2.group(1)
            print(f"  ✅ Service property encontrada (tipo): $this->{svc_prop}")
        else:
            svc_prop = None
            print("  ⚠️  Não conseguiu detectar nome da propriedade do service")
            print("       Possibilidades: $this->service, $this->financeService, $this->dashService")
            print("       AÇÃO NECESSÁRIA: Verificar manualmente o construtor e ajustar")

    # Procurar: 'dashboardData' => $dashboardData (ou variações)
    anchor = None
    for candidate in ["'dashboardData' => $dashboardData", '"dashboardData" => $dashboardData']:
        if candidate in content:
            anchor = candidate
            break

    if not anchor:
        print("  ⚠️  Âncora 'dashboardData' não encontrada no controller")
        print("       Será necessário patch manual — ver IMPLEMENTACAO.md")
        return

    if svc_prop:
        sparkline_code = f",\n            'sparklines' => $this->{svc_prop}->getSparklineData($ano)"
    else:
        sparkline_code = ",\n            'sparklines' => [] // TODO: conectar ao service->getSparklineData($ano)"
        print("  ⚠️  Usando array vazio como fallback — editar após descobrir nome da propriedade")

    patch_insert_after(ctrl, anchor, sparkline_code, "sparklines no controller")

# ══════════════════════════════════════════════════════════════
# MAIN
# ══════════════════════════════════════════════════════════════
def main():
    print("╔══════════════════════════════════════════════════════╗")
    print("║  LOTE 1: KPI Card v2 + Dashboard Financeiro         ║")
    print("║  Waterfall DRE · Pareto Inadimplência · Insights     ║")
    print("╚══════════════════════════════════════════════════════╝")

    step0_validate()
    step1_backups()
    step2_kpi_card()
    step3_partials()
    step4_visao_gerencial()
    step5_service()
    step6_controller()

    print("\n═══ DEPLOY COMPLETO ═══")
    print("  Próximos passos:")
    print("  1. Executar comandos SSH do IMPLEMENTACAO.md para copiar arquivos novos")
    print("  2. php artisan cache:clear && php artisan view:clear")
    print("  3. Testar em /visao-gerencial")
    print(f"  4. Se erro: restaurar backups (.bak_{TIMESTAMP})")
    print()

if __name__ == "__main__":
    main()
