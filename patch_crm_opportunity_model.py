#!/usr/bin/env python3
"""
Patch CrmOpportunity model — adiciona campos ESPO/SIPEX ao fillable e casts.
"""

path = 'app/Models/Crm/CrmOpportunity.php'
with open(path, 'r') as f:
    c = f.read()

# Adicionar novos campos ao fillable
old_fillable = "'lost_reason', 'won_at', 'lost_at',"
new_fillable = "'lost_reason', 'tipo_demanda', 'lead_source', 'espo_id',\n        'amount', 'currency', 'probability', 'close_date',\n        'won_at', 'lost_at',"

if 'espo_id' not in c:
    c = c.replace(old_fillable, new_fillable)
    print('OK - fillable atualizado')
else:
    print('SKIP - espo_id ja existe no fillable')

# Adicionar casts para novos campos
old_casts = "'lost_at'         => 'datetime',"
new_casts = """'lost_at'         => 'datetime',
        'close_date'      => 'date',
        'amount'          => 'decimal:2',
        'probability'     => 'integer',"""

if 'close_date' not in c:
    c = c.replace(old_casts, new_casts)
    print('OK - casts atualizado')
else:
    print('SKIP - close_date ja existe nos casts')

with open(path, 'w') as f:
    f.write(c)

print('Patch CrmOpportunity concluido')
