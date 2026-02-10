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

// ESPO CRM → 2x/dia (9h, 17h) - Horário de Brasília
Schedule::command('cron:sync-espo')
    ->dailyAt('09:00')
    ->timezone('America/Sao_Paulo')
    ->appendOutputTo(storage_path('logs/cron-espo.log'));

Schedule::command('cron:sync-espo')
    ->dailyAt('17:00')
    ->timezone('America/Sao_Paulo')
    ->appendOutputTo(storage_path('logs/cron-espo.log'));
