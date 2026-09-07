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

        // Bill every client for the month that has just ended.
        //
        // On the 1st, not the last day of the month: the command bills the
        // PREVIOUS calendar month, so running it on 31 August billed July and
        // left August waiting until 30 September. Running it on 1 September
        // bills August, which is the month that has actually finished.
        //
        // Half past midnight rather than on it, so a run is not competing with
        // everything else scheduled at 00:00.
        $schedule->command('charges:generate-monthly')
            ->monthlyOn(1, '00:30')
            ->withoutOverlapping();
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
