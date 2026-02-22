#!/usr/bin/env python3
import sys, re
path = 'app/Services/DashboardFinanceProdService.php'
with open(path, 'r') as f:
    content = f.read()

patches = 0

# Encontrar e remover metodos por regex (pega de "private function X" ate "^    }" no mesmo nivel)
def remove_method(content, name):
    # Remove docblock + metodo
    pattern = r'(\n    /\*\*.*?\*/\n)?    private function ' + re.escape(name) + r'\(.*?\n    \}\n'
    result = re.subn(pattern, '\n', content, count=1, flags=re.DOTALL)
    return result

# FIN-003a: Remover distinctClassificacoes
content, n = remove_method(content, 'distinctClassificacoes')
if n: patches += 1; print(f'  P{patches}: distinctClassificacoes removido')

# FIN-003b: Remover normalizeKey
content, n = remove_method(content, 'normalizeKey')
if n: patches += 1; print(f'  P{patches}: normalizeKey removido')

# FIN-003c: Remover planoCodigoColumn
content, n = remove_method(content, 'planoCodigoColumn')
if n: patches += 1; print(f'  P{patches}: planoCodigoColumn removido')

# FIN-003d: Substituir resolveReceitaClassificacoes
content, n = remove_method(content, 'resolveReceitaClassificacoes')
if n:
    # Inserir versao nova antes de applyReceitaTipoFilter
    novo = """
    /**
     * FIN-003: Fonte unica - classificacao_regras via UI.
     */
    private function resolveReceitaClassificacoes(): array
    {
        return ['pf' => ['RECEITA_PF'], 'pj' => ['RECEITA_PJ']];
    }

"""
    content = content.replace('    private function applyReceitaTipoFilter(', novo + '    private function applyReceitaTipoFilter(', 1)
    patches += 1; print(f'  P{patches}: resolveReceitaClassificacoes simplificado')

# FIN-003e: Substituir applyReceitaTipoFilter
content, n = remove_method(content, 'applyReceitaTipoFilter')
if n:
    novo2 = """
    /**
     * FIN-003: Filtro direto por classificacao. Sem fallbacks.
     */
    private function applyReceitaTipoFilter($query, string $tipo): void
    {
        $vals = $tipo === 'pj' ? ['RECEITA_PJ'] : ['RECEITA_PF'];
        $query->whereIn('classificacao', $vals);
    }

"""
    content = content.replace('    private function sumReceitaTipo(', novo2 + '    private function sumReceitaTipo(', 1)
    patches += 1; print(f'  P{patches}: applyReceitaTipoFilter simplificado')

# FIN-009a: receitaFinanceira no getResumoExecutivo
old9a = "$receitaTotal = $receitaPf + $receitaPj;"
new9a = """// FIN-009: Receitas financeiras e outras receitas operacionais
        $receitaFinanceira = (float) abs(Movimento::where('ano', $ano)->where('mes', $mes)
            ->whereIn('classificacao', ['RECEITA_FINANCEIRA', 'OUTRAS_RECEITAS'])->sum('valor'));
        $receitaTotal = $receitaPf + $receitaPj + $receitaFinanceira;"""
if old9a in content:
    content = content.replace(old9a, new9a, 1)
    patches += 1; print(f'  P{patches}: FIN-009 receitaFinanceira getResumoExecutivo')

# FIN-009b: Prev
old9b = "$receitaPrev = $receitaPfPrev + $receitaPjPrev;"
new9b = """$receitaFinanceiraPrev = (float) abs(Movimento::where('ano', $pAno)->where('mes', $pMes)
            ->whereIn('classificacao', ['RECEITA_FINANCEIRA', 'OUTRAS_RECEITAS'])->sum('valor'));
        $receitaPrev = $receitaPfPrev + $receitaPjPrev + $receitaFinanceiraPrev;"""
if old9b in content:
    content = content.replace(old9b, new9b, 1)
    patches += 1; print(f'  P{patches}: FIN-009 receitaFinanceira Prev')

# FIN-009c: YoY
old9c = "$receitaYoYPrev = $this->sumReceitaTipo($yoyAno, $mes, 'pf') + $this->sumReceitaTipo($yoyAno, $mes, 'pj');"
new9c = """$receitaYoYPrev = $this->sumReceitaTipo($yoyAno, $mes, 'pf') + $this->sumReceitaTipo($yoyAno, $mes, 'pj')
            + (float) abs(Movimento::where('ano', $yoyAno)->where('mes', $mes)->whereIn('classificacao', ['RECEITA_FINANCEIRA', 'OUTRAS_RECEITAS'])->sum('valor'));"""
if old9c in content:
    content = content.replace(old9c, new9c, 1)
    patches += 1; print(f'  P{patches}: FIN-009 receitaFinanceira YoY')

# FIN-009d: resumoBasico
old9d = "$receita = $receitaPf + $receitaPj;"
new9d = """$receitaFin = (float) abs(Movimento::where('ano', $ano)->where('mes', $mes)
            ->whereIn('classificacao', ['RECEITA_FINANCEIRA', 'OUTRAS_RECEITAS'])->sum('valor'));
        $receita = $receitaPf + $receitaPj + $receitaFin;"""
if old9d in content:
    content = content.replace(old9d, new9d, 1)
    patches += 1; print(f'  P{patches}: FIN-009 receitaFinanceira resumoBasico')

with open(path, 'w') as f:
    f.write(content)
print(f'\nTotal: {patches} patches aplicados')
