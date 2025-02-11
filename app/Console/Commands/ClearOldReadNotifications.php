<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ClearOldReadNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:clear-read';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear all read notifications that are older than one year';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $oneYearAgo = Carbon::now()->subYear();
        $sixMonthsAgo = Carbon::now()->subMonths(6);

        // Delete old read notifications
        DB::table('user_notifications')
            ->where('read', 'true')
            ->where('created_at', '<', $sixMonthsAgo)
            ->delete();

        $this->info('Old read notifications cleared successfully.');
        return 0;
    }

    //On server - CRON
    // * * * * * php /path-to-your-project/artisan schedule:run >> /dev/null 2>&1


    //php artisan notifications:clear-read
}
