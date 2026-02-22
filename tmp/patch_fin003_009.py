#!/usr/bin/env python3
"""
Patches FIN-003 (classificacao fonte unica) e FIN-009 (receitas financeiras no DRE)
para app/Services/DashboardFinanceProdService.php
"""
import sys

path = 'app/Services/DashboardFinanceProdService.php'
with open(path, 'r') as f:
    lines = f.readlines()

new_lines = []
i = 0
patches_applied = []

def skip_method(lines, i):
    """Avanca i ate fechar o metodo (brace count = 0)"""
    brace = 0
    while i < len(lines):
        brace += lines[i].count('{') - lines[i].count('}')
        i += 1
        if brace <= 0:
            break
    # Pular linha vazia apos metodo
    if i < len(lines) and lines[i].strip() == '':
        i += 1
    return i

def remove_docblock_above(new_lines):
    """Remove docblock PHPdoc acima (ja adicionado em new_lines)"""
    while new_lines and (
        new_lines[-1].strip().startswith('*') or
        new_lines[-1].strip().startswith('/**') or
        new_lines[-1].strip() == ''
    ):
        removed = new_lines.pop()
        if '/**' in removed:
            break

while i < len(lines):
    line = lines[i]

    # === FIN-003a: Remover normalizeKey inteiro ===
    if 'private function normalizeKey(' in line:
        remove_docblock_above(new_lines)
        i = skip_method(lines, i)
        patches_applied.append('normalizeKey removido')
        continue

    # === FIN-003b: Remover planoCodigoColumn inteiro ===
    if 'private function planoCodigoColumn(' in line:
        remove_docblock_above(new_lines)
        i = skip_method(lines, i)
        patches_applied.append('planoCodigoColumn removido')
        continue

    # === FIN-003c: Remover distinctClassificacoes se existir ===
    if 'private function distinctClassificacoes(' in line:
        remove_docblock_above(new_lines)
        i = skip_method(lines, i)
        patches_applied.append('distinctClassificacoes removido')
        continue

    # === FIN-003d: Substituir resolveReceitaClassificacoes ===
    if 'private function resolveReceitaClassificacoes(' in line:
        remove_docblock_above(new_lines)
        i = skip_method(lines, i)
        new_lines.append('    /**\n')
        new_lines.append('     * FIN-003: Fonte unica - classificacao_regras via UI.\n')
        new_lines.append('     */\n')
        new_lines.append('    private function resolveReceitaClassificacoes(): array\n')
        new_lines.append('    {\n')
        new_lines.append("        return ['pf' => ['RECEITA_PF'], 'pj' => ['RECEITA_PJ']];\n")
        new_lines.append('    }\n')
        new_lines.append('\n')
        patches_applied.append('resolveReceitaClassificacoes simplificado')
        continue

    # === FIN-003e: Substituir applyReceitaTipoFilter ===
    if 'private function applyReceitaTipoFilter(' in line:
        remove_docblock_above(new_lines)
        i = skip_method(lines, i)
        new_lines.append('    /**\n')
        new_lines.append('     * FIN-003: Filtro direto por classificacao. Sem fallbacks.\n')
        new_lines.append('     */\n')
        new_lines.append('    private function applyReceitaTipoFilter($query, string $tipo): void\n')
        new_lines.append('    {\n')
        new_lines.append("        $vals = $tipo === 'pj' ? ['RECEITA_PJ'] : ['RECEITA_PF'];\n")
        new_lines.append("        $query->whereIn('classificacao', $vals);\n")
        new_lines.append('    }\n')
        new_lines.append('\n')
        patches_applied.append('applyReceitaTipoFilter simplificado')
        continue

    # === FIN-009a: getResumoExecutivo - receitaTotal ===
    if '$receitaTotal = $receitaPf + $receitaPj;' in line:
        new_lines.append("        // FIN-009: Receitas financeiras e outras receitas operacionais\n")
        new_lines.append("        $receitaFinanceira = (float) abs(Movimento::where('ano', $ano)->where('mes', $mes)\n")
        new_lines.append("            ->whereIn('classificacao', ['RECEITA_FINANCEIRA', 'OUTRAS_RECEITAS'])->sum('valor'));\n")
        new_lines.append("        $receitaTotal = $receitaPf + $receitaPj + $receitaFinanceira;\n")
        i += 1
        patches_applied.append('FIN-009 receitaFinanceira getResumoExecutivo')
        continue

    # === FIN-009b: Prev ===
    if '$receitaPrev = $receitaPfPrev + $receitaPjPrev;' in line:
        new_lines.append("        $receitaFinanceiraPrev = (float) abs(Movimento::where('ano', $pAno)->where('mes', $pMes)\n")
        new_lines.append("            ->whereIn('classificacao', ['RECEITA_FINANCEIRA', 'OUTRAS_RECEITAS'])->sum('valor'));\n")
        new_lines.append("        $receitaPrev = $receitaPfPrev + $receitaPjPrev + $receitaFinanceiraPrev;\n")
        i += 1
        patches_applied.append('FIN-009 receitaFinanceira Prev')
        continue

    # === FIN-009c: YoY ===
    if "sumReceitaTipo($yoyAno, $mes, 'pj');" in line and 'Financeira' not in line:
        # Remover ; do final e adicionar soma
        trimmed = line.rstrip('\n').rstrip()
        if trimmed.endswith(';'):
            trimmed = trimmed[:-1]
        new_lines.append(trimmed + "\n")
        new_lines.append("            + (float) abs(Movimento::where('ano', $yoyAno)->where('mes', $mes)->whereIn('classificacao', ['RECEITA_FINANCEIRA', 'OUTRAS_RECEITAS'])->sum('valor'));\n")
        i += 1
        patches_applied.append('FIN-009 receitaFinanceira YoY')
        continue

    # === FIN-009d: resumoBasico ===
    if '$receita = $receitaPf + $receitaPj;' in line:
        new_lines.append("        $receitaFin = (float) abs(Movimento::where('ano', $ano)->where('mes', $mes)\n")
        new_lines.append("            ->whereIn('classificacao', ['RECEITA_FINANCEIRA', 'OUTRAS_RECEITAS'])->sum('valor'));\n")
        new_lines.append("        $receita = $receitaPf + $receitaPj + $receitaFin;\n")
        i += 1
        patches_applied.append('FIN-009 receitaFinanceira resumoBasico')
        continue

    new_lines.append(line)
    i += 1

with open(path, 'w') as f:
    f.writelines(new_lines)

print(f"Patches aplicados ({len(patches_applied)}):")
for p in patches_applied:
    print(f"  - {p}")
if not patches_applied:
    print("ERRO: Nenhum patch aplicado!")
    sys.exit(1)
