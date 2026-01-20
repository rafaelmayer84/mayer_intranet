-- HML ONLY (NÃO RODAR EM PRODUÇÃO SEM REVISÃO).
-- Backfill de movimentos.classificacao (aproxima a lógica do comando financeiro:backfill-classificacao).
-- Requer coluna codigo_plano preenchida.
-- Ajuste o YEAR conforme necessidade.

SET @YEAR := 2026;

-- PF
UPDATE movimentos
SET classificacao = 'RECEITA_PF'
WHERE ano = @YEAR
  AND (classificacao IS NULL OR classificacao = '' OR classificacao = 'PENDENTE_CLASSIFICACAO')
  AND (
    codigo_plano LIKE '3.01.01.01%' OR
    codigo_plano LIKE '3.01.01.03%'
  );

-- PJ
UPDATE movimentos
SET classificacao = 'RECEITA_PJ'
WHERE ano = @YEAR
  AND (classificacao IS NULL OR classificacao = '' OR classificacao = 'PENDENTE_CLASSIFICACAO')
  AND (
    codigo_plano LIKE '3.01.01.02%' OR
    codigo_plano LIKE '3.01.01.05%'
  );

-- Receita Financeira
UPDATE movimentos
SET classificacao = 'RECEITA_FINANCEIRA'
WHERE ano = @YEAR
  AND (classificacao IS NULL OR classificacao = '' OR classificacao = 'PENDENTE_CLASSIFICACAO')
  AND (
    codigo_plano LIKE '3.03.01%' OR
    codigo_plano LIKE '3.03.03%'
  );

-- Despesa (regra conservadora)
UPDATE movimentos
SET classificacao = 'DESPESA'
WHERE ano = @YEAR
  AND (classificacao IS NULL OR classificacao = '' OR classificacao = 'PENDENTE_CLASSIFICACAO')
  AND codigo_plano LIKE '3.02%';
