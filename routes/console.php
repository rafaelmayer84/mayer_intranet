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

// CRM Carteira → 3x/dia após sync (02:08, 10:08, 18:08)
Schedule::command('crm:recalcular-carteira')
    ->dailyAt('02:08')
    ->timezone('America/Sao_Paulo')
    ->appendOutputTo(storage_path('logs/cron-crm-carteira.log'));

Schedule::command('crm:recalcular-carteira')
    ->dailyAt('10:08')
    ->timezone('America/Sao_Paulo')
    ->appendOutputTo(storage_path('logs/cron-crm-carteira.log'));

Schedule::command('crm:recalcular-carteira')
    ->dailyAt('18:08')
    ->timezone('America/Sao_Paulo')
    ->appendOutputTo(storage_path('logs/cron-crm-carteira.log'));

// ===== NEXO: Fechamento automático de chats abandonados =====
// A cada hora, fecha chats com >6h de inatividade e notifica cliente
Schedule::command('nexo:close-abandoned-chats --notify')
    ->hourly()
    ->timezone('America/Sao_Paulo')
    ->appendOutputTo(storage_path('logs/cron-nexo-close-chats.log'));

// ===== NEXO: Lembretes de audiência WhatsApp =====
// Diário às 07h BRT — envia templates para audiências nos próximos 10 dias
Schedule::command('nexo:verificar-audiencias')
    ->dailyAt('07:00')
    ->timezone('America/Sao_Paulo')
    ->appendOutputTo(storage_path('logs/cron-nexo-audiencias.log'));

// ===== NEXO: Detecção de andamentos processuais novos =====
// 3x/dia após sync DataJuri (02:10, 10:10, 18:10) — cria notificações pendentes
Schedule::command('nexo:verificar-andamentos')
    ->dailyAt('02:10')
    ->timezone('America/Sao_Paulo')
    ->appendOutputTo(storage_path('logs/cron-nexo-andamentos.log'));

Schedule::command('nexo:verificar-andamentos')
    ->dailyAt('10:10')
    ->timezone('America/Sao_Paulo')
    ->appendOutputTo(storage_path('logs/cron-nexo-andamentos.log'));

Schedule::command('nexo:verificar-andamentos')
    ->dailyAt('18:10')
    ->timezone('America/Sao_Paulo')
    ->appendOutputTo(storage_path('logs/cron-nexo-andamentos.log'));

// OS - 1x/dia após sync das 10h
Schedule::command('nexo:verificar-os')
    ->dailyAt('10:15')
    ->timezone('America/Sao_Paulo')
    ->appendOutputTo(storage_path('logs/cron-nexo-os.log'));

// GDP Apuracao diaria (scores + penalizacoes)
Schedule::command('gdp:apurar')->dailyAt('06:00');



// Limpeza audit_logs > 90 dias (diario as 03:00)
use App\Models\AuditLog;
Schedule::call(function () {
    AuditLog::olderThan(90)->delete();
})->dailyAt('03:00')->name('audit-log-cleanup');
