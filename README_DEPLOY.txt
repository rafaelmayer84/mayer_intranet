README_DEPLOY.txt

CASO (B) - FONTE DE VERDADE (diagnóstico Manus 15/01/2026):
- DataJuri HTTP 200 e listSize=1759
- Banco local: contas_receber_total=0
Logo: DataJuri tem dados, mas a sync não gravou (não foi executada ou falhou).

Este patch aplica SOMENTE a correção do caso (B):
- Implementa SyncService::sincronizarContasReceber() com mapeamento do payload DataJuri (inclui pessoa.nome)
- Adiciona comando Artisan: financeiro:sync-contas-receber
- Adiciona script de evidências: scripts/validar_caso_B.sh

Uso rápido:
1) php artisan financeiro:sync-contas-receber --dry-run --limit=20
2) php artisan financeiro:sync-contas-receber

Logs:
- storage/logs/sync_debug.log
