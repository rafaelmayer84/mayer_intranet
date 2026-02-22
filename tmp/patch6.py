#!/usr/bin/env python3
path = 'app/Services/DashboardFinanceProdService.php'
with open(path, 'r') as f:
    c = f.read()
n = 0

# 1) Adicionar use + propriedade
old1 = "class DashboardFinanceProdService"
new1 = """class DashboardFinanceProdService
{
    private FinanceiroCalculatorService $calc;

    public function __construct()
    {
        $this->calc = app(FinanceiroCalculatorService::class);
    }"""
# Remover o { existente após class
if old1 in c:
    # Encontrar "class X\n{"
    c = c.replace(old1 + "\n{", new1, 1)
    n += 1; print(f'P{n}: constructor com FinanceiroCalculatorService')

# 2) Adicionar use statement
if 'use App\\Services\\FinanceiroCalculatorService;' not in c:
    c = c.replace('use App\\Models\\Movimento;', 'use App\\Models\\Movimento;\nuse App\\Services\\FinanceiroCalculatorService;', 1)
    n += 1; print(f'P{n}: use statement adicionado')

# 3) Substituir sumReceitaFinanceira pelo calc
old3 = """    /**
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
    }"""
new3 = """    /**
     * FIN-009: Delega para FinanceiroCalculatorService.
     */
    private function sumReceitaFinanceira(int $ano, int $mes): float
    {
        return $this->calc->sum($ano, $mes, ['RECEITA_FINANCEIRA', 'OUTRAS_RECEITAS']);
    }"""
if old3 in c:
    c = c.replace(old3, new3, 1)
    n += 1; print(f'P{n}: sumReceitaFinanceira delega para calc')

with open(path, 'w') as f:
    f.write(c)
print(f'\nTotal: {n}')
