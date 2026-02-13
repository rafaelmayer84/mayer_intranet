<?php
/**
 * ESPO CRM MySQL Connection
 * 
 * Adicionar este bloco dentro de 'connections' em config/database.php:
 *
 * 'mysql_espo' => [
 *     'driver'    => 'mysql',
 *     'host'      => env('ESPO_DB_HOST', '127.0.0.1'),
 *     'port'      => env('ESPO_DB_PORT', '3306'),
 *     'database'  => env('ESPO_DB_DATABASE', 'espocrm'),
 *     'username'  => env('ESPO_DB_USERNAME', ''),
 *     'password'  => env('ESPO_DB_PASSWORD', ''),
 *     'charset'   => 'utf8mb4',
 *     'collation' => 'utf8mb4_unicode_ci',
 *     'prefix'    => '',
 *     'strict'    => false,
 * ],
 *
 * E adicionar ao .env:
 * ESPO_DB_HOST=127.0.0.1
 * ESPO_DB_DATABASE=nome_do_banco_espo
 * ESPO_DB_USERNAME=usuario
 * ESPO_DB_PASSWORD=senha
 */
