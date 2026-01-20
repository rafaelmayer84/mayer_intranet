-- Checagens de integridade (MySQL 5.7)
SELECT COUNT(*) AS movimentos_total FROM movimentos;
SELECT COUNT(*) AS movimentos_com_classificacao
FROM movimentos
WHERE classificacao IS NOT NULL AND classificacao <> '';

SELECT classificacao, COUNT(*) AS total
FROM movimentos
WHERE classificacao IS NOT NULL AND classificacao <> ''
GROUP BY classificacao
ORDER BY total DESC;

SELECT COUNT(*) AS contas_receber_total FROM contas_receber;

SELECT COUNT(*) AS metas_total
FROM configuracoes
WHERE chave LIKE 'meta_%';
