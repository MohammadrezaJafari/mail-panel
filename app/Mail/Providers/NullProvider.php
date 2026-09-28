<?php

namespace App\Mail\Providers;

use App\Mail\Contracts\MailProvider;
use App\Mail\Data\MailboxUsage;
use App\Models\Alias;
use App\Models\Domain;
use App\Models\Mailbox;

/**
 * In-memory provider for local development and automated tests.
 * Records every call so tests can assert on what the product asked for.
 */
class NullProvider implements MailProvider
{
    /** @var array<int, array{0: string, 1: array}> */
    public array $calls = [];

    /** @var array<string, string> address => password */
    public array $passwords = [];

    /** @var array<string, MailboxUsage> */
    public array $usages = [];

    public function name(): string
    {
        return 'fake';
    }

    private function record(string $method, array $args = []): void
    {
        $this->calls[] = [$method, $args];
    }

    public function createDomain(Domain $domain): void
    {
        $this->record(__FUNCTION__, [$domain->name]);
        $domain->rememberExternalId($domain->name, [], 'fake');
    }

    public function updateDomain(Domain $domain): void
    {
        $this->record(__FUNCTION__, [$domain->name]);
    }

    public function deleteDomain(Domain $domain): void
    {
        $this->record(__FUNCTION__, [$domain->name]);
    }

    public function getDkim(Domain $domain): ?array
    {
        return ['selector' => 'dkim', 'txt' => 'v=DKIM1;k=rsa;p=NULLPROVIDER'];
    }

    public function createMailbox(Mailbox $mailbox, string $password): void
    {
        $this->record(__FUNCTION__, [$mailbox->address]);
        $this->passwords[$mailbox->address] = $password;
        $mailbox->rememberExternalId($mailbox->address, [], 'fake');
    }

    public function updateMailbox(Mailbox $mailbox): void
    {
        $this->record(__FUNCTION__, [$mailbox->address]);
    }

    public function deleteMailbox(Mailbox $mailbox): void
    {
        $this->record(__FUNCTION__, [$mailbox->address]);
        unset($this->passwords[$mailbox->address]);
    }

    public function setMailboxPassword(Mailbox $mailbox, string $password): void
    {
        $this->record(__FUNCTION__, [$mailbox->address]);
        $this->passwords[$mailbox->address] = $password;
    }

    public function setQuota(Mailbox $mailbox, int $quotaMb): void
    {
        $this->record(__FUNCTION__, [$mailbox->address, $quotaMb]);
    }

    public function suspendMailbox(Mailbox $mailbox): void
    {
        $this->record(__FUNCTION__, [$mailbox->address]);
    }

    public function activateMailbox(Mailbox $mailbox): void
    {
        $this->record(__FUNCTION__, [$mailbox->address]);
    }

    public function getMailboxUsage(Mailbox $mailbox): ?MailboxUsage
    {
        return $this->usages[$mailbox->address] ?? new MailboxUsage(0, $mailbox->quotaBytes(), 0);
    }

    public function syncMailboxRules(Mailbox $mailbox): void
    {
        $this->record(__FUNCTION__, [$mailbox->address]);
    }

    public function createAlias(Alias $alias): void
    {
        $this->record(__FUNCTION__, [$alias->address]);
        $alias->rememberExternalId((string) $alias->id, [], 'fake');
    }

    public function updateAlias(Alias $alias): void
    {
        $this->record(__FUNCTION__, [$alias->address]);
    }

    public function deleteAlias(Alias $alias): void
    {
        $this->record(__FUNCTION__, [$alias->address]);
    }

    public function health(): array
    {
        return [
            'smtp' => ['status' => 'ok', 'message' => 'null provider'],
            'imap' => ['status' => 'ok', 'message' => 'null provider'],
            'antispam' => ['status' => 'ok', 'message' => 'null provider'],
        ];
    }
}
