#!/bin/bash
cd /home/u492856976/domains/mayeradvogados.adv.br/public_html/Intranet

LOG="storage/logs/justus-tjsc-carga-historica.log"
echo "=== CARGA HISTORICA TJSC - INICIO $(date) ===" >> $LOG

# 2025: fev a dez (jan já importado)
for MES in 2 3 4 5 6 7 8 9 10 11 12; do
    echo ">>> 2025-$MES $(date)" >> $LOG
    php artisan justus:sync-tjsc --mes=$MES --ano=2025 --ps=50 >> $LOG 2>&1
    sleep 5
done

# 2024: jan a dez
for MES in 1 2 3 4 5 6 7 8 9 10 11 12; do
    echo ">>> 2024-$MES $(date)" >> $LOG
    php artisan justus:sync-tjsc --mes=$MES --ano=2024 --ps=50 >> $LOG 2>&1
    sleep 5
done

# STJ: sync completo (todos datasets)
echo ">>> STJ FULL SYNC $(date)" >> $LOG
php artisan justus:sync-stj >> $LOG 2>&1

echo "=== CARGA HISTORICA - FIM $(date) ===" >> $LOG

php artisan tinker --execute="echo 'TJSC: ' . App\Models\JustusJurisprudencia::where('tribunal','TJSC')->count() . ' | STJ: ' . App\Models\JustusJurisprudencia::where('tribunal','STJ')->count();" >> $LOG 2>&1
