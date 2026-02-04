<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Process recurring transactions daily at 02:00
        $schedule->command('recurring:process')
            ->dailyAt('02:00')
            ->timezone('Asia/Colombo')
            ->withoutOverlapping(10);

        // Telegram Daily Summary - runs every minute, command checks time internally
        $schedule->command('telegram:daily-summary')
            ->everyMinute()
            ->timezone('Asia/Colombo')
            ->withoutOverlapping(5);

        // Telegram Monthly Summary - runs every minute, command checks day/time internally
        $schedule->command('telegram:monthly-summary')
            ->everyMinute()
            ->timezone('Asia/Colombo')
            ->withoutOverlapping(5);
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
