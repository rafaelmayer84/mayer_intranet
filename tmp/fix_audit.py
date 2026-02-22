#!/usr/bin/env python3
path = 'app/Console/Commands/AuditFinance.php'
with open(path, 'r') as f:
    c = f.read()

# Substituir bloco de inadimplencia
old = """        // Inadimplencia
        $inadTotal = Movimento::where('ano', $ano)->where('mes', $mes)
            ->where('status', 'Não lançado')
            ->count();
        $inadValor = (float) abs(Movimento::where('ano', $ano)->where('mes', $mes)
            ->where('status', 'Não lançado')
            ->sum('valor'));"""

new = """        // Inadimplencia (ContaReceber, nao Movimento)
        $inadTotal = \\App\\Models\\ContaReceber::where('status', 'Não lançado')
            ->whereNotNull('data_vencimento')
            ->where('data_vencimento', '<', now())
            ->count();
        $inadValor = (float) abs(\\App\\Models\\ContaReceber::where('status', 'Não lançado')
            ->whereNotNull('data_vencimento')
            ->where('data_vencimento', '<', now())
            ->sum('valor'));"""

c = c.replace(old, new)

# Atualizar criterios
old2 = "        $this->line('  Inadimplencia = status \"Nao lancado\"');"
new2 = "        $this->line('  Inadimplencia = ContaReceber status \"Nao lancado\" + vencimento < hoje');"

c = c.replace(old2, new2)

with open(path, 'w') as f:
    f.write(c)
print('OK')
