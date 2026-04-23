<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ===== CRON JOBS - SINCRONIZAÇÃO AUTOMÁTICA =====

// DataJuri → 2x/dia (2h, 12h) - Horário de Brasília
Schedule::command('cron:sync-datajuri')
    ->dailyAt('02:00')
    ->timezone('America/Sao_Paulo')
    ->appendOutputTo(storage_path('logs/cron-datajuri.log'));

Schedule::command('cron:sync-datajuri')
    ->dailyAt('12:00')
    ->timezone('America/Sao_Paulo')
    ->appendOutputTo(storage_path('logs/cron-datajuri.log'));

// ESPO CRM removido em 13/02/2026 - substituído por CRM Nativo

// Contas a Receber → 2x/dia (logo após DataJuri)
Schedule::command('sync:contas-receber-rapido')
    ->dailyAt('02:05')
    ->timezone('America/Sao_Paulo')
    ->appendOutputTo(storage_path('logs/cron-contas-receber.log'));

Schedule::command('sync:contas-receber-rapido')
    ->dailyAt('12:05')
    ->timezone('America/Sao_Paulo')
    ->appendOutputTo(storage_path('logs/cron-contas-receber.log'));

// CRM Sync Carteira → 2x/dia após sync DataJuri (02:06, 12:06)
Schedule::command('crm:sync-carteira')
    ->dailyAt('02:06')
    ->timezone('America/Sao_Paulo')
    ->appendOutputTo(storage_path('logs/cron-crm-sync-carteira.log'));

Schedule::command('crm:sync-carteira')
    ->dailyAt('12:06')
    ->timezone('America/Sao_Paulo')
    ->appendOutputTo(storage_path('logs/cron-crm-sync-carteira.log'));

// CRM Carteira → 2x/dia após sync (02:08, 12:08)
Schedule::command('crm:recalcular-carteira')
    ->dailyAt('02:08')
    ->timezone('America/Sao_Paulo')
    ->appendOutputTo(storage_path('logs/cron-crm-carteira.log'));

Schedule::command('crm:recalcular-carteira')
    ->dailyAt('12:08')
    ->timezone('America/Sao_Paulo')
    ->appendOutputTo(storage_path('logs/cron-crm-carteira.log'));

// CRM Gates: detectar divergências e processar resolução/escalação (após carteira)
Schedule::command('crm:gates-detectar')
    ->dailyAt('02:20')
    ->timezone('America/Sao_Paulo')
    ->appendOutputTo(storage_path('logs/cron-crm-gates.log'));

Schedule::command('crm:gates-processar')
    ->dailyAt('02:25')
    ->timezone('America/Sao_Paulo')
    ->appendOutputTo(storage_path('logs/cron-crm-gates.log'));

Schedule::command('crm:gates-detectar')
    ->dailyAt('12:20')
    ->timezone('America/Sao_Paulo')
    ->appendOutputTo(storage_path('logs/cron-crm-gates.log'));

Schedule::command('crm:gates-processar')
    ->dailyAt('12:25')
    ->timezone('America/Sao_Paulo')
    ->appendOutputTo(storage_path('logs/cron-crm-gates.log'));

// ===== NEXO: Purge tokens públicos expirados =====
Schedule::command('nexo:purge-expired-tokens')
    ->hourly()
    ->timezone('America/Sao_Paulo')
    ->appendOutputTo(storage_path('logs/cron-nexo-purge-tokens.log'));

// ===== NEXO: Gerenciamento de chats inativos =====
// A cada hora: lembrete após 6h de inatividade, encerramento após 23h
Schedule::command('nexo:close-abandoned-chats --reminder-hours=6 --close-hours=23')
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
// 2x/dia após sync DataJuri (02:10, 12:10) — cria notificações pendentes
Schedule::command('nexo:verificar-andamentos')
    ->dailyAt('02:10')
    ->timezone('America/Sao_Paulo')
    ->appendOutputTo(storage_path('logs/cron-nexo-andamentos.log'));

Schedule::command('nexo:verificar-andamentos')
    ->dailyAt('12:10')
    ->timezone('America/Sao_Paulo')
    ->appendOutputTo(storage_path('logs/cron-nexo-andamentos.log'));

// OS - 1x/dia após sync das 10h
Schedule::command('nexo:verificar-os')
    ->dailyAt('10:15')
    ->timezone('America/Sao_Paulo')
    ->appendOutputTo(storage_path('logs/cron-nexo-os.log'));

// GDP Apuracao diaria — todos os meses abertos do ciclo (00:30 BRT, apos syncs DataJuri)
Schedule::command('gdp:apurar')
    ->dailyAt('00:30')
    ->timezone('America/Sao_Paulo')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/cron-gdp-apuracao.log'));

// GDP Scanner Conformidade — diario 08:00 BRT (verifica ocorrencias automaticas)
Schedule::command('gdp:penalizacoes')
    ->dailyAt('08:00')
    ->timezone('America/Sao_Paulo')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/cron-gdp-conformidade.log'));



// GDP Lembrete Acordo pendente → diário 09:00 BRT
Schedule::command('cron:gdp-lembrete-acordo')
    ->dailyAt('09:00')
    ->timezone('America/Sao_Paulo')
    ->appendOutputTo(storage_path('logs/cron-gdp-lembrete.log'));

// GDP Eval180 → dia 1 de cada mês às 08:00 (abre avaliação do mês anterior)
Schedule::command('gdp:abrir-eval180')
    ->monthlyOn(1, '08:00')
    ->timezone('America/Sao_Paulo')
    ->appendOutputTo(storage_path('logs/cron-eval180.log'));

// Limpeza audit_logs > 90 dias (diario as 03:00)
use App\Models\AuditLog;
use App\Models\SystemEvent;
Schedule::call(function () {
    AuditLog::olderThan(90)->delete();
})->dailyAt('03:00')->name('audit-log-cleanup');

// Cleanup erros de aplicação > 90 dias
use App\Models\SystemErrorLog;
Schedule::call(function () {
    SystemErrorLog::olderThan(90)->delete();
})->dailyAt('03:05')->name('error-log-cleanup');

// --- NEXO QA: Pesquisa de Qualidade ---
use App\Models\NexoQaCampaign;
use App\Jobs\NexoQaWeeklySamplingJob;
use App\Jobs\NexoQaWeeklyAggregateJob;

Schedule::call(function () {
    $campaigns = NexoQaCampaign::active()->get();
    foreach ($campaigns as $campaign) {
        NexoQaWeeklySamplingJob::dispatch($campaign->id);
    }
})->dailyAt('20:00')
  ->timezone('America/Sao_Paulo')
  ->name('nexo-qa-daily-sampling')
  ->withoutOverlapping();

Schedule::call(function () {
    $weekStart = now('America/Sao_Paulo')->startOfWeek(\Carbon\Carbon::MONDAY)->format('Y-m-d');
    NexoQaWeeklyAggregateJob::dispatch($weekStart);
})->weeklyOn(0, '22:00')
  ->timezone('America/Sao_Paulo')
  ->name('nexo-qa-weekly-aggregate')
  ->withoutOverlapping();

// Processar fila de jobs a cada 5 minutos (Hostinger não suporta queue:work permanente)

// ===== JUSTUS: Sync jurisprudência =====
// STJ via CKAN — diário 04:00 BRT
Schedule::command('justus:sync-stj')
    ->dailyAt('04:00')
    ->timezone('America/Sao_Paulo')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/cron-justus-stj.log'));

// TJSC via eproc — diário 04:30 BRT (último mês + mês atual)
// Migrado de busca.tjsc.jus.br para eproc em 18/03/2026
Schedule::command('justus:sync-tjsc-eproc --meses-atras=1 --ps=50')
    ->dailyAt('04:30')
    ->timezone('America/Sao_Paulo')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/cron-justus-tjsc.log'));

Schedule::command('queue:work --stop-when-empty --max-time=55 --tries=3')
    ->everyFiveMinutes()
    ->timezone('America/Sao_Paulo')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/queue-worker.log'));

// Cleanup system_events mais de 365 dias (03:15 BRT)
Schedule::call(function () {
    $deleted = SystemEvent::olderThan(365)->delete();
    if ($deleted > 0) {
        SystemEvent::sistema('cleanup.executado', 'info', "Cleanup: {$deleted} eventos antigos removidos");
    }
})->dailyAt('03:15')->timezone('America/Sao_Paulo');

// CRM Inadimplência — verificar contas vencidas e notificar responsáveis — 10:12 BRT
Schedule::command('crm:check-inadimplencia')
    ->dailyAt('10:12')
    ->timezone('America/Sao_Paulo')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/cron-crm-inadimplencia.log'));

// CRM Cadência — verificar tasks vencendo hoje (sininho + email) — 8h BRT
Schedule::command('crm:cadence-check')
    ->dailyAt('08:00')
    ->timezone('America/Sao_Paulo')
    ->appendOutputTo(storage_path('logs/cron-crm-cadence.log'));

// CRM Health Score — recalcular diariamente às 06:00 BRT
Schedule::command('crm:recalc-health')
    ->dailyAt('06:00')
    ->timezone('America/Sao_Paulo')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/cron-crm-health.log'));

// CRM Segmentação IA — batch semanal domingo 05:00 BRT
Schedule::command('crm:segmentar-batch')
    ->weeklyOn(0, '05:00')
    ->timezone('America/Sao_Paulo')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/cron-crm-segmentacao.log'));

// CRM Notificação contas inativas — diário 09:00 BRT
Schedule::command('crm:notify-inactive')
    ->dailyAt('09:00')
    ->timezone('America/Sao_Paulo')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/cron-crm-notify.log'));

// JUSTUS TRF4 Sync - diário 05:00 BRT (02/03/2026)
Schedule::command('justus:sync-trf4 --meses-atras=1 --ps=50 --tipo=1')
    ->dailyAt('05:00')
    ->timezone('America/Sao_Paulo')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/cron-justus-trf4.log'));

// TRT12 via API Falcao — diario 05:30 BRT (ultimos 7 dias)
Schedule::command('justus:sync-trt12 --modo=recentes --max-paginas=20')
    ->dailyAt('05:30')
    ->timezone('America/Sao_Paulo')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/cron-justus-trt12.log'));

// EVIDENTIA - Chunk + Embed novos acordaos (diario 03:30 BRT)
Schedule::command('evidentia:chunk --limit=5000')
    ->dailyAt('06:00')
    ->timezone('America/Sao_Paulo')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/cron-evidentia-chunk.log'));

Schedule::command('evidentia:embed --sync --limit=20000')
    ->twiceDaily(3, 15)
    ->timezone('America/Sao_Paulo')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/cron-evidentia-embed.log'));

// EVIDENTIA - Worker de fila (a cada 5 min)
Schedule::command('queue:work database --queue=evidentia --timeout=120 --tries=2 --stop-when-empty')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/cron-evidentia-queue.log'));


// CRM AI Weekly Digest — Segunda-feira 07:00 BRT
Schedule::command('crm:generate-insights --type=weekly')
    ->weeklyOn(1, '07:00')
    ->timezone('America/Sao_Paulo')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/crm-ai-insights.log'));


// CRM: Verificar oportunidades com prazo vencido — Diário 08:00 BRT
// VIGILIA: Cruzamento diario atividades x andamentos — 07:00 BRT
Schedule::command('vigilia:cruzar')
    ->dailyAt('07:00')
    ->timezone('America/Sao_Paulo')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/vigilia-cruzar.log'));

// VIGILIA Machine C: Classificação AI de andamentos — 07:30 BRT (após sync DataJuri e cruzamento)
Schedule::command('vigilia:classificar')
    ->dailyAt('07:30')
    ->timezone('America/Sao_Paulo')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/vigilia-classificar.log'));

// VIGILIA Machine B: Auditoria AI de cruzamentos suspeitos — 08:00 BRT (após classificar)
Schedule::command('vigilia:auditar')
    ->dailyAt('08:00')
    ->timezone('America/Sao_Paulo')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/vigilia-auditar.log'));

Schedule::command('crm:check-deadlines')
    ->dailyAt('08:00')
    ->timezone('America/Sao_Paulo')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/crm-deadlines.log'));

// Alertas proativos de processos administrativos (prazos, etapas atrasadas, inatividade)
Schedule::command('admin-processes:alerts')
    ->dailyAt('08:30')
    ->timezone('America/Sao_Paulo')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/admin-processes-alerts.log'));

// ===== PULSO DO CLIENTE =====
// Consolidação diária — 23:00 BRT (após expediente)
Schedule::command('pulso:consolidar')
    ->dailyAt('23:00')
    ->timezone('America/Sao_Paulo')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/cron-pulso-consolidar.log'));

// Lembrete upload ligações — sexta 9:00 BRT
Schedule::command('pulso:lembrete-telefone')
    ->weeklyOn(5, '09:00')
    ->timezone('America/Sao_Paulo')
    ->appendOutputTo(storage_path('logs/cron-pulso-lembrete.log'));

// SISRH: Lembrete semanal para importar frequencia (segunda 09:00 BRT)
Schedule::call(function () {
    \Illuminate\Support\Facades\DB::table('notifications')->insert([
        'user_id' => 1,
        'tipo' => 'lembrete',
        'titulo' => 'Importar Frequencia Semanal',
        'mensagem' => 'Lembre-se de importar os dados de login do DataJuri desta semana na Folha de Frequencia (SISRH > Frequencia).',
        'lida' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
})->weeklyOn(1, '09:00')->timezone('America/Sao_Paulo');

// Relatório CEO — dias 1 e 15 de cada mês às 07:00 BRT
Schedule::command('relatorio:gerar-ceo')
    ->twiceMonthly(1, 15, '07:00')
    ->timezone('America/Sao_Paulo')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/cron-relatorio-ceo.log'));

