#!/bin/bash
echo "Executando rollback..."
cd ~/domains/mayeradvogados.adv.br/public_html/Intranet
cp resources/views/layouts/app.blade.php.backup_20260123_174011 resources/views/layouts/app.blade.php
cp resources/views/dashboard/visao-gerencial.blade.php.backup_20260123_174011 resources/views/dashboard/visao-gerencial.blade.php
php artisan view:clear
php artisan optimize:clear
echo "✓ Rollback concluído"
