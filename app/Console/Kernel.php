<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        Commands\CronSyncDataJuri::class,
        Commands\CronGdpApurar::class,
        // CronSyncEspoCrm removido em 13/02/2026
    ];

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // DataJuri → 3x/dia (2h, 10h, 18h)
        $schedule->command('cron:sync-datajuri')
            ->dailyAt('02:00')
            ->timezone('America/Sao_Paulo')
            ->appendOutputTo(storage_path('logs/cron-datajuri.log'));
        
        $schedule->command('cron:sync-datajuri')
            ->dailyAt('10:00')
            ->timezone('America/Sao_Paulo')
            ->appendOutputTo(storage_path('logs/cron-datajuri.log'));
        
        $schedule->command('cron:sync-datajuri')
            ->dailyAt('18:00')
            ->timezone('America/Sao_Paulo')
            ->appendOutputTo(storage_path('logs/cron-datajuri.log'));

        // GDP Apuracao → 3x/dia (02:30, 10:30, 18:30 - apos sync DataJuri)
        $schedule->command('cron:gdp-apurar')
            ->dailyAt('02:30')
            ->timezone('America/Sao_Paulo')
            ->appendOutputTo(storage_path('logs/cron-gdp.log'));
        $schedule->command('cron:gdp-apurar')
            ->dailyAt('10:30')
            ->timezone('America/Sao_Paulo')
            ->appendOutputTo(storage_path('logs/cron-gdp.log'));
        $schedule->command('cron:gdp-apurar')
            ->dailyAt('18:30')
            ->timezone('America/Sao_Paulo')
            ->appendOutputTo(storage_path('logs/cron-gdp.log'));

        // ESPO CRM → 2x/dia (9h, 17h)
        $schedule->command('cron:sync-espo')
            ->dailyAt('09:00')
            ->timezone('America/Sao_Paulo')
            ->appendOutputTo(storage_path('logs/cron-espo.log'));
        
        $schedule->command('cron:sync-espo')
            ->dailyAt('17:00')
            ->timezone('America/Sao_Paulo')
            ->appendOutputTo(storage_path('logs/cron-espo.log'));
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
