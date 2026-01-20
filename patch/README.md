# Patch: Correção de KPIs Zerados Enganosos - Visão Gerencial

## O que mudou

### 1. **DashboardFinanceProdService.php**

#### Correção A: Mix PF/PJ (sem "zero mentiroso")
- Adicionada detecção de movimentos com `classificacao = 'PENDENTE_CLASSIFICACAO'`
- Quando há pendentes: warning informativo é adicionado
- Exemplo: `"Há 239 movimentos sem classificação; Mix PF/PJ indisponível até classificar."`

#### Correção B: Inadimplência / Taxa de Cobrança (sem "0 mentiroso")
- Adicionada detecção de contas sem `data_pagamento`
- Quando 100% das contas têm `data_pagamento = NULL`:
  - `taxaCobranca` retorna `null` (indisponível)
  - Warning informativo é adicionado
  - Exemplo: `"Sem base de pagamentos (data_pagamento vazia); taxa de cobrança indisponível."`

#### Correção C: Tabela Contas em Atraso (Cliente, Nº, dias)
- **Antes:** `cliente_nome` (coluna inexistente) → fallback "Cliente"
- **Depois:** `cliente` (coluna real)
- **Novo campo:** `numero` (usa `datajuri_id` ou `id`)
- **Renomeado:** `diasAtraso` → `dias_atraso` (snake_case)
- Estrutura final:
  ```json
  {
    "numero": 14631,
    "cliente": "(Sem cliente)",
    "valor": 1.15,
    "dias_atraso": 4510,
    "status": "critico"
  }
  ```

### 2. **resources/views/dashboard/visao-gerencial.blade.php**

#### Correção: JavaScript para renderizar tabela corretamente
- **Antes:** `c.cliente`, `c.dias`, `c.cliente_nome` (undefined)
- **Depois:** `c.numero`, `c.cliente`, `c.dias_atraso`
- Tabela agora exibe:
  | Nº | Cliente | Valor | Dias |
  |----|---------|-------|------|
  | 14631 | (Sem cliente) | R$ 1,15 | 4510 |

---

## Como aplicar o patch

### Opção 1: Copiar arquivos manualmente
```bash
cp app/Services/DashboardFinanceProdService.php /caminho/para/Intranet/app/Services/
cp resources/views/dashboard/visao-gerencial.blade.php /caminho/para/Intranet/resources/views/dashboard/
```

### Opção 2: Usar Git (se disponível)
```bash
cd /caminho/para/Intranet
git apply patch.diff
```

---

## Validações executadas

| Validação | Status | Detalhes |
|-----------|--------|----------|
| Sintaxe PHP | ✅ | Sem erros |
| SQL Sanity | ✅ | 239 pendentes, 0 com pagamento, 1.759 total |
| API - Taxa Cobrança | ✅ | `null` (correto) |
| API - Dias Atraso | ✅ | `1278` dias |
| API - Primeira Conta | ✅ | `numero: 14631, cliente: "(Sem cliente)", dias_atraso: 4510` |
| API - Warnings | ✅ | 2 warnings informativos |
| Stress Test 50x | ✅ | 50/50 OK (100%) |

---

## Rollback (se necessário)

### Opção 1: Git
```bash
git restore app/Services/DashboardFinanceProdService.php
git restore resources/views/dashboard/visao-gerencial.blade.php
```

### Opção 2: Backup manual
```bash
# Se você fez backup antes:
cp app/Services/DashboardFinanceProdService.php.backup app/Services/DashboardFinanceProdService.php
cp resources/views/dashboard/visao-gerencial.blade.php.backup resources/views/dashboard/visao-gerencial.blade.php
```

### Opção 3: Limpar cache
```bash
php artisan cache:forget 'dash_fin_exec:v1:2026:1'
```

---

## Próximas ações recomendadas

1. **Sincronizar dados com DataJuri:**
   ```bash
   php artisan sync:contas-receber
   ```
   Isso preencherá o campo `cliente` com dados reais.

2. **Classificar movimentos pendentes:**
   ```bash
   php artisan financeiro:backfill-classificacao
   ```
   Isso permitirá que Mix PF/PJ seja calculado corretamente.

3. **Importar dados de pagamento:**
   Se DataJuri envia `data_pagamento`, sincronizar novamente para calcular Taxa de Cobrança.

---

## Critério de aceite

- ✅ Mix PF/PJ não retorna "0%" quando há pendentes (retorna warning)
- ✅ Taxa de Cobrança não retorna "0" quando sem pagamentos (retorna `null` + warning)
- ✅ Tabela Contas em Atraso exibe `numero`, `cliente`, `dias_atraso` (sem undefined)
- ✅ 50 requisições consecutivas: 100% sucesso
- ✅ Sem erros de sintaxe PHP

---

## Suporte

Se encontrar problemas:
1. Verifique se os arquivos foram copiados corretamente
2. Execute `php artisan cache:clear` para limpar cache
3. Verifique logs em `storage/logs/laravel.log`
4. Rollback se necessário usando as instruções acima
