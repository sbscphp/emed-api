<?php

namespace App\Console;

use Carbon\Carbon;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // $schedule->command('inspire')->hourly();
        $schedule->command('notifications:clear-read')->daily();
        $schedule->command('newsletter:send')->everyTwoWeeks();

        // Generate monthly usage fee charges on the last day of every month at midnight.
        // Laravel's lastDayOfMonth() dynamically resolves the correct final day
        // regardless of whether the month has 28, 29, 30, or 31 days.
        $schedule->command('charges:generate-monthly')->lastDayOfMonth('00:00');
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
