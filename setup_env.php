<?php
// Script para configurar o .env do Laravel
$envContent = <<<'ENV'
APP_NAME="Intranet Mayer"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://mayeradvogados.adv.br/Intranet

LOG_CHANNEL=stack
LOG_LEVEL=debug

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=u492856976_intranet
DB_USERNAME=u492856976_intranet
DB_PASSWORD=9x+&]pqRYmRs?hJ

BROADCAST_DRIVER=log
CACHE_DRIVER=file
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
SESSION_LIFETIME=120

DATAJURI_CLIENT_ID=a79mtxvdhsq0pgob733z
DATAJURI_SECRET_ID=80d50612-1656-49b4-a920-868b421ed56b
DATAJURI_EMAIL=rafaelmayer@mayeradvogados.adv.br
DATAJURI_PASSWORD=Mayer01.
ENV;

file_put_contents('/home/u492856976/domains/mayeradvogados.adv.br/public_html/Intranet/.env', $envContent);
echo "Arquivo .env criado com sucesso!\n";

// Gerar APP_KEY
chdir('/home/u492856976/domains/mayeradvogados.adv.br/public_html/Intranet');
exec('php artisan key:generate', $output);
echo implode("\n", $output);
