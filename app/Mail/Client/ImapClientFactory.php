<?php

namespace App\Mail\Client;

use App\Models\User;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;

/**
 * Builds IMAP connections for end users with their own mailbox credentials.
 */
class ImapClientFactory
{
    public function __construct(protected ClientManager $manager) {}

    public function make(string $username, string $password): Client
    {
        $cfg = config('mailprovider.imap');

        return $this->manager->make([
            'host' => $cfg['host'],
            'port' => $cfg['port'],
            'encryption' => $cfg['encryption'] ?: false,
            'validate_cert' => $cfg['validate_cert'],
            'username' => $username,
            'password' => $password,
            'protocol' => 'imap',
            'timeout' => 20,
        ]);
    }

    public function forUser(User $user, ?string $address = null): Client
    {
        if (blank($user->mail_password)) {
            throw new MailCredentialsMissingException('Mailbox credentials are not available. Please sign in again.');
        }

        return $this->make($address ?? $user->email, $user->mail_password);
    }

    /** Try to authenticate; returns true on success without throwing. */
    public function check(string $username, string $password): bool
    {
        try {
            $client = $this->make($username, $password);
            $client->connect();
            $ok = $client->isConnected();
            $client->disconnect();

            return $ok;
        } catch (\Throwable) {
            return false;
        }
    }
}
