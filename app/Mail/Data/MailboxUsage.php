<?php

namespace App\Mail\Data;

final readonly class MailboxUsage
{
    public function __construct(
        public int $usedBytes,
        public int $quotaBytes,
        public int $messages = 0,
    ) {}
}
