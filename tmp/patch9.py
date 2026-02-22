#!/usr/bin/env python3
path = 'app/Services/DataJuriSyncService.php'
with open(path, 'r') as f:
    c = f.read()
n = 0

# Encontrar onde o movimento é salvo/atualizado e adicionar logs
# Procurar o bloco de save/updateOrCreate de movimentos
target = "$movimento->save();"
if target in c:
    logs = """$movimento->save();

                            // PATCH-9: Log estruturado de anomalias
                            if (empty($movimento->classificacao) || $movimento->classificacao === 'IGNORAR') {
                                Log::channel('daily')->notice('DataJuri Sync: classificacao ausente/ignorar', [
                                    'movimento_id' => $movimento->id,
                                    'datajuri_id' => $movimento->datajuri_id,
                                    'valor' => $movimento->valor,
                                ]);
                            }
                            if (abs($movimento->valor) < 0.01) {
                                Log::channel('daily')->warning('DataJuri Sync: valor zero', [
                                    'movimento_id' => $movimento->id,
                                    'datajuri_id' => $movimento->datajuri_id,
                                    'descricao' => $movimento->descricao,
                                ]);
                            }"""
    c = c.replace(target, logs, 1)
    n += 1; print(f'P{n}: logs anomalias apos save')

with open(path, 'w') as f:
    f.write(c)
print(f'Total: {n}')
