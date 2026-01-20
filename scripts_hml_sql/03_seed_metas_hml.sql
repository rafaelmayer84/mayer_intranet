-- HML ONLY.
-- Seed de metas (cria chaves meta_{kpi}_{ano}_{mes} com 0).
SET @YEAR := 2026;

-- Exemplo para meta_pf. Repita para outros KPIs conforme necessário.
INSERT INTO configuracoes (chave, valor, created_at, updated_at)
SELECT CONCAT('meta_pf_', @YEAR, '_', m.mes) AS chave, 0 AS valor, NOW(), NOW()
FROM (
  SELECT 1 AS mes UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL
  SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL
  SELECT 9 UNION ALL SELECT 10 UNION ALL SELECT 11 UNION ALL SELECT 12
) m
WHERE NOT EXISTS (
  SELECT 1 FROM configuracoes c WHERE c.chave = CONCAT('meta_pf_', @YEAR, '_', m.mes)
);
