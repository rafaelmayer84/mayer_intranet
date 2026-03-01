#!/bin/sh
cd /home/u492856976/domains/mayeradvogados.adv.br/public_html/Intranet || exit 1
/usr/bin/php artisan queue:work database --queue=justus --once --timeout=300 --memory=256 >/dev/null 2>&1
