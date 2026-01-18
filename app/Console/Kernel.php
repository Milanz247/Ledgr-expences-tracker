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
        // Process recurring transactions daily at midnight
        $schedule->command('recurring:process')
            ->daily()
            ->timezone('Asia/Colombo')
            ->withoutOverlapping(5);

        // Send daily reports at 9 PM
        $schedule->command('reports:send --frequency=daily')
            ->dailyAt('21:00')
            ->timezone('Asia/Colombo')
            ->withoutOverlapping(10);

        // Send weekly reports every Monday at 9 AM
        $schedule->command('reports:send --frequency=weekly')
            ->weeklyOn(1, '09:00') // Monday
            ->timezone('Asia/Colombo')
            ->withoutOverlapping(10);

        // Send monthly reports on the 1st of each month at 9 AM
        $schedule->command('reports:send --frequency=monthly')
            ->monthlyOn(1, '09:00')
            ->timezone('Asia/Colombo')
            ->withoutOverlapping(10);

        // Process recurring expenses daily at 02:00
        $schedule->command('recurring:process')
            ->dailyAt('02:00')
            ->timezone('Asia/Colombo')
            ->withoutOverlapping(10);
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
