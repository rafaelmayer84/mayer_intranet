<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     * TODOS os agendamentos estão em routes/console.php (fonte única).
     */
    protected function schedule(Schedule $schedule): void
    {
        // Nada aqui — routes/console.php é a fonte única de schedules
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
