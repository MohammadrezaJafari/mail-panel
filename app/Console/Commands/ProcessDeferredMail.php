<?php

namespace App\Console\Commands;

use App\Services\ScheduledMailService;
use Illuminate\Console\Command;

class ProcessDeferredMail extends Command
{
    protected $signature = 'mail:process-deferred';

    protected $description = 'Send scheduled messages and wake snoozed messages that are due';

    public function handle(ScheduledMailService $service): int
    {
        $sent = $service->sendDue();
        $woken = $service->wakeDue();

        $this->info("Scheduled sent: {$sent}, snoozes woken: {$woken}");

        return self::SUCCESS;
    }
}
