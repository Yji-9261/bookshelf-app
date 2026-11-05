<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void {
        // 読書計画リマインダー通知コマンド
        $schedule->command('app:send-reading-plan-reminders')->dailyAt('00:00');

        // 読書計画自動失効通知コマンド
        $schedule->command('app:expire-reading-plan')->dailyAt('00:00');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
