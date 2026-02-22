#!/usr/bin/env python3
"""Tarefa 1: Reinserir resolveReceitaClassificacoes + criar sumReceitaFinanceira helper"""
import sys
path = 'app/Services/DashboardFinanceProdService.php'
with open(path, 'r') as f:
    c = f.read()
n = 0

# 1) Reinserir resolveReceitaClassificacoes antes de applyReceitaTipoFilter
if 'resolveReceitaClassificacoes' not in c:
    novo_resolve = """    /**
     * FIN-003: Fonte unica - classificacao_regras via UI.
     */
    private function resolveReceitaClassificacoes(): array
    {
        return ['pf' => ['RECEITA_PF'], 'pj' => ['RECEITA_PJ']];
    }

    /**
     * FIN-003: Filtro direto por classificacao. Sem fallbacks."""
    c = c.replace("    /**\n     * FIN-003: Filtro direto por classificacao. Sem fallbacks.", novo_resolve, 1)
    n += 1; print(f'  P{n}: resolveReceitaClassificacoes reinserido')

# 2) Verificar se applyReceitaTipoFilter usa resolveReceitaClassificacoes
if "private function applyReceitaTipoFilter" in c and "resolveReceitaClassificacoes" not in c.split("applyReceitaTipoFilter")[1].split("private function")[0]:
    old_filter = """        $vals = $tipo === 'pj' ? ['RECEITA_PJ'] : ['RECEITA_PF'];
        $query->whereIn('classificacao', $vals);"""
    new_filter = """        $map = $this->resolveReceitaClassificacoes();
        $vals = $tipo === 'pj' ? $map['pj'] : $map['pf'];
        $query->whereIn('classificacao', $vals);"""
    if old_filter in c:
        c = c.replace(old_filter, new_filter, 1)
        n += 1; print(f'  P{n}: applyReceitaTipoFilter usa resolveReceitaClassificacoes')

# 3) Criar helper sumReceitaFinanceira e substituir as 4 copias
helper = """
    /**
     * FIN-009: Soma receitas financeiras e outras receitas operacionais.
     */
    private function sumReceitaFinanceira(int $ano, int $mes): float
    {
        return (float) abs(
            Movimento::where('ano', $ano)
                ->where('mes', $mes)
                ->whereIn('classificacao', ['RECEITA_FINANCEIRA', 'OUTRAS_RECEITAS'])
                ->sum('valor')
        );
    }
"""
# Inserir antes de sumReceitaTipo
if 'sumReceitaFinanceira' not in c:
    c = c.replace('    private function sumReceitaTipo(', helper + '    private function sumReceitaTipo(', 1)
    n += 1; print(f'  P{n}: sumReceitaFinanceira helper criado')

# 4a) getResumoExecutivo - substituir inline por helper
old4a = """// FIN-009: Receitas financeiras e outras receitas operacionais
        $receitaFinanceira = (float) abs(Movimento::where('ano', $ano)->where('mes', $mes)
            ->whereIn('classificacao', ['RECEITA_FINANCEIRA', 'OUTRAS_RECEITAS'])->sum('valor'));
        $receitaTotal = $receitaPf + $receitaPj + $receitaFinanceira;"""
new4a = """// FIN-009: Receitas financeiras e outras receitas operacionais
        $receitaFinanceira = $this->sumReceitaFinanceira($ano, $mes);
        $receitaTotal = $receitaPf + $receitaPj + $receitaFinanceira;"""
if old4a in c:
    c = c.replace(old4a, new4a, 1)
    n += 1; print(f'  P{n}: getResumoExecutivo usa helper')

# 4b) Prev
old4b = """$receitaFinanceiraPrev = (float) abs(Movimento::where('ano', $pAno)->where('mes', $pMes)
            ->whereIn('classificacao', ['RECEITA_FINANCEIRA', 'OUTRAS_RECEITAS'])->sum('valor'));
        $receitaPrev = $receitaPfPrev + $receitaPjPrev + $receitaFinanceiraPrev;"""
new4b = """$receitaFinanceiraPrev = $this->sumReceitaFinanceira($pAno, $pMes);
        $receitaPrev = $receitaPfPrev + $receitaPjPrev + $receitaFinanceiraPrev;"""
if old4b in c:
    c = c.replace(old4b, new4b, 1)
    n += 1; print(f'  P{n}: Prev usa helper')

# 4c) YoY
old4c = """+ (float) abs(Movimento::where('ano', $yoyAno)->where('mes', $mes)->whereIn('classificacao', ['RECEITA_FINANCEIRA', 'OUTRAS_RECEITAS'])->sum('valor'));"""
new4c = """+ $this->sumReceitaFinanceira($yoyAno, $mes);"""
if old4c in c:
    c = c.replace(old4c, new4c, 1)
    n += 1; print(f'  P{n}: YoY usa helper')

# 4d) resumoBasico
old4d = """$receitaFin = (float) abs(Movimento::where('ano', $ano)->where('mes', $mes)
            ->whereIn('classificacao', ['RECEITA_FINANCEIRA', 'OUTRAS_RECEITAS'])->sum('valor'));
        $receita = $receitaPf + $receitaPj + $receitaFin;"""
new4d = """$receitaFin = $this->sumReceitaFinanceira($ano, $mes);
        $receita = $receitaPf + $receitaPj + $receitaFin;"""
if old4d in c:
    c = c.replace(old4d, new4d, 1)
    n += 1; print(f'  P{n}: resumoBasico usa helper')

with open(path, 'w') as f:
    f.write(c)
print(f'\nTotal: {n} patches')
