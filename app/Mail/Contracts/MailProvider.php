<?php

namespace App\Mail\Contracts;

use App\Mail\Data\MailboxUsage;
use App\Models\Alias;
use App\Models\Domain;
use App\Models\Mailbox;

/**
 * Everything the product needs from a mail backend. Business logic must never
 * talk to mailcow (or any future provider) except through this contract.
 */
interface MailProvider
{
    public function name(): string;

    // Domains
    public function createDomain(Domain $domain): void;

    public function updateDomain(Domain $domain): void;

    public function deleteDomain(Domain $domain): void;

    /** @return array{selector: string, txt: string}|null */
    public function getDkim(Domain $domain): ?array;

    // Mailboxes
    public function createMailbox(Mailbox $mailbox, string $password): void;

    public function updateMailbox(Mailbox $mailbox): void;

    public function deleteMailbox(Mailbox $mailbox): void;

    public function setMailboxPassword(Mailbox $mailbox, string $password): void;

    public function setQuota(Mailbox $mailbox, int $quotaMb): void;

    public function suspendMailbox(Mailbox $mailbox): void;

    public function activateMailbox(Mailbox $mailbox): void;

    public function getMailboxUsage(Mailbox $mailbox): ?MailboxUsage;

    /** Push forwarding + auto-reply settings stored on the mailbox model. */
    public function syncMailboxRules(Mailbox $mailbox): void;

    // Aliases
    public function createAlias(Alias $alias): void;

    public function updateAlias(Alias $alias): void;

    public function deleteAlias(Alias $alias): void;

    /**
     * Health of the backend, keyed by component (smtp, imap, antispam, ...).
     *
     * @return array<string, array{status: string, message?: string}>
     */
    public function health(): array;
}
