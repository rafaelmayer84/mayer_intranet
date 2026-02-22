#!/usr/bin/env python3
path = 'app/Services/BscInsights/BscInsightSnapshotBuilder.php'
with open(path, 'r') as f:
    c = f.read()
n = 0

# 1) Adicionar use
if 'FinanceiroCalculatorService' not in c:
    c = c.replace('use App\\Models\\Movimento;', 'use App\\Models\\Movimento;\nuse App\\Services\\FinanceiroCalculatorService;', 1)
    n += 1; print(f'P{n}: use FinanceiroCalculatorService')

# 2) Substituir queries diretas por calc->dre()
old2 = """                $receitaPF[$m['key']] = (float) (clone $base)->where('classificacao', 'RECEITA_PF')->sum('valor');
                $receitaPJ[$m['key']] = (float) (clone $base)->where('classificacao', 'RECEITA_PJ')->sum('valor');
                $despesas[$m['key']]  = (float) (clone $base)->where('classificacao', 'DESPESA')->sum('valor');
                $deducoes[$m['key']]  = (float) (clone $base)->where('classificacao', 'DEDUCAO')->sum('valor');"""
new2 = """                $calc = app(FinanceiroCalculatorService::class);
                $dre = $calc->dre($m['ano'], $m['mes']);
                $receitaPF[$m['key']] = $dre['receita_pf'];
                $receitaPJ[$m['key']] = $dre['receita_pj'];
                $despesas[$m['key']]  = -$dre['despesas'];
                $deducoes[$m['key']]  = -$dre['deducoes'];"""
if old2 in c:
    c = c.replace(old2, new2, 1)
    n += 1; print(f'P{n}: queries substituidas por calc->dre()')

# 3) Incluir receita financeira no total
old3 = """                $receitaTotal[$m['key']] = $receitaPF[$m['key']] + $receitaPJ[$m['key']];"""
new3 = """                $receitaTotal[$m['key']] = $dre['receita_total'];"""
if old3 in c:
    c = c.replace(old3, new3, 1)
    n += 1; print(f'P{n}: receitaTotal inclui financeira')

with open(path, 'w') as f:
    f.write(c)
print(f'\nTotal: {n}')
