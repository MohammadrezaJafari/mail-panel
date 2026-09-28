<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Mail\Client\ImapClientFactory;
use App\Mail\Client\MailboxClient;
use App\Models\Mailbox;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

trait ResolvesMailbox
{
    protected function currentMailbox(Request $request): Mailbox
    {
        $mailbox = $request->user()->primaryMailbox();

        if (! $mailbox) {
            throw new NotFoundHttpException('No mailbox is linked to this account.');
        }

        return $mailbox;
    }

    protected function client(Request $request): MailboxClient
    {
        return new MailboxClient(app(ImapClientFactory::class)->forUser($request->user()));
    }
}
