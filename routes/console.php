<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ===== CRON JOBS - SINCRONIZAÇÃO AUTOMÁTICA =====

// DataJuri → 3x/dia (2h, 10h, 18h) - Horário de Brasília
Schedule::command('cron:sync-datajuri')
    ->dailyAt('02:00')
    ->timezone('America/Sao_Paulo')
    ->appendOutputTo(storage_path('logs/cron-datajuri.log'));

Schedule::command('cron:sync-datajuri')
    ->dailyAt('10:00')
    ->timezone('America/Sao_Paulo')
    ->appendOutputTo(storage_path('logs/cron-datajuri.log'));

Schedule::command('cron:sync-datajuri')
    ->dailyAt('18:00')
    ->timezone('America/Sao_Paulo')
    ->appendOutputTo(storage_path('logs/cron-datajuri.log'));

// ESPO CRM removido em 13/02/2026 - substituído por CRM Nativo

// Contas a Receber → 3x/dia (logo após DataJuri)
Schedule::command('sync:contas-receber-rapido')
    ->dailyAt('02:05')
    ->timezone('America/Sao_Paulo')
    ->appendOutputTo(storage_path('logs/cron-contas-receber.log'));

Schedule::command('sync:contas-receber-rapido')
    ->dailyAt('10:05')
    ->timezone('America/Sao_Paulo')
    ->appendOutputTo(storage_path('logs/cron-contas-receber.log'));

Schedule::command('sync:contas-receber-rapido')
    ->dailyAt('18:05')
    ->timezone('America/Sao_Paulo')
    ->appendOutputTo(storage_path('logs/cron-contas-receber.log'));

// ===== NEXO: Fechamento automático de chats abandonados =====
// A cada hora, fecha chats com >6h de inatividade e notifica cliente
Schedule::command('nexo:close-abandoned-chats --notify')
    ->hourly()
    ->timezone('America/Sao_Paulo')
    ->appendOutputTo(storage_path('logs/cron-nexo-close-chats.log'));

