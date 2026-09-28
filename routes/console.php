<?php

use Illuminate\Support\Facades\Schedule;

// Scheduled sends and snoozed messages. Requires `php artisan schedule:run` via cron.
Schedule::command('mail:process-deferred')->everyMinute()->withoutOverlapping();
