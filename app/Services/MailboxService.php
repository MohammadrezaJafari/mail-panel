<?php

namespace App\Services;

use App\Enums\MailboxStatus;
use App\Enums\UserRole;
use App\Mail\Contracts\MailProvider;
use App\Models\Domain;
use App\Models\Mailbox;
use App\Models\MailboxMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class MailboxService
{
    public function __construct(
        protected MailProvider $provider,
        protected AuditLogger $audit,
    ) {}

    /**
     * Create a mailbox on the provider, locally, and (optionally) a portal user for it.
     */
    public function create(Domain $domain, array $attributes, string $password, bool $createUser = true): Mailbox
    {
        $this->assertDomainCapacity($domain);

        return DB::transaction(function () use ($domain, $attributes, $password, $createUser) {
            $mailbox = new Mailbox($attributes + [
                'quota_mb' => $domain->default_quota_mb,
                'status' => MailboxStatus::Active,
            ]);
            $mailbox->organization_id = $domain->organization_id;
            $mailbox->domain()->associate($domain);
            $mailbox->address = strtolower($mailbox->local_part.'@'.$domain->name);
            $mailbox->save();
            $mailbox->refresh();

            $this->provider->createMailbox($mailbox, $password);

            if ($createUser && ! $mailbox->is_shared) {
                $this->attachPortalUser($mailbox, $password);
            }

            $this->audit->log('mailbox.created', $mailbox, ['address' => $mailbox->address, 'quota_mb' => $mailbox->quota_mb]);

            return $mailbox;
        });
    }

    public function update(Mailbox $mailbox, array $attributes): Mailbox
    {
        return DB::transaction(function () use ($mailbox, $attributes) {
            $mailbox->fill($attributes)->save();
            $this->provider->updateMailbox($mailbox);
            $this->audit->log('mailbox.updated', $mailbox, $mailbox->getChanges());

            return $mailbox;
        });
    }

    public function setQuota(Mailbox $mailbox, int $quotaMb): Mailbox
    {
        if ($quotaMb > $mailbox->domain->max_quota_mb) {
            throw ValidationException::withMessages(['quota_mb' => "Quota exceeds the domain maximum of {$mailbox->domain->max_quota_mb} MB."]);
        }

        return DB::transaction(function () use ($mailbox, $quotaMb) {
            $old = $mailbox->quota_mb;
            $mailbox->forceFill(['quota_mb' => $quotaMb])->save();
            $this->provider->setQuota($mailbox, $quotaMb);
            $this->audit->log('mailbox.quota_changed', $mailbox, ['from' => $old, 'to' => $quotaMb]);

            return $mailbox;
        });
    }

    public function resetPassword(Mailbox $mailbox, string $password, bool $byUser = false): Mailbox
    {
        return DB::transaction(function () use ($mailbox, $password, $byUser) {
            $this->provider->setMailboxPassword($mailbox, $password);

            // Keep portal logins in sync with the mailbox password.
            foreach ($mailbox->users as $user) {
                $user->forceFill(['password' => $password, 'mail_password' => $password])->save();
            }

            $this->audit->log($byUser ? 'mailbox.password_changed' : 'mailbox.password_reset', $mailbox);

            return $mailbox;
        });
    }

    public function suspend(Mailbox $mailbox): Mailbox
    {
        return DB::transaction(function () use ($mailbox) {
            $mailbox->forceFill(['status' => MailboxStatus::Suspended])->save();
            $this->provider->suspendMailbox($mailbox);
            $this->audit->log('mailbox.suspended', $mailbox);

            return $mailbox;
        });
    }

    public function activate(Mailbox $mailbox): Mailbox
    {
        return DB::transaction(function () use ($mailbox) {
            $mailbox->forceFill(['status' => MailboxStatus::Active])->save();
            $this->provider->activateMailbox($mailbox);
            $this->audit->log('mailbox.activated', $mailbox);

            return $mailbox;
        });
    }

    public function delete(Mailbox $mailbox): void
    {
        DB::transaction(function () use ($mailbox) {
            $this->provider->deleteMailbox($mailbox);
            $this->audit->log('mailbox.deleted', $mailbox, ['address' => $mailbox->address]);
            $mailbox->delete();
        });
    }

    /**
     * Update forwarding / auto-reply / signature and push rules to the provider.
     */
    public function updateRules(Mailbox $mailbox, array $attributes): Mailbox
    {
        return DB::transaction(function () use ($mailbox, $attributes) {
            $mailbox->fill($attributes)->save();
            $this->provider->syncMailboxRules($mailbox);
            $this->audit->log('mailbox.rules_updated', $mailbox, array_keys($mailbox->getChanges()) ? $mailbox->getChanges() : []);

            return $mailbox;
        });
    }

    public function syncUsage(Mailbox $mailbox): Mailbox
    {
        $usage = $this->provider->getMailboxUsage($mailbox);

        if ($usage) {
            $mailbox->forceFill([
                'used_bytes' => $usage->usedBytes,
                'message_count' => $usage->messages,
                'usage_synced_at' => now(),
            ])->save();
        }

        return $mailbox;
    }

    public function attachPortalUser(Mailbox $mailbox, string $password, string $role = MailboxMember::ROLE_OWNER): User
    {
        $user = User::firstOrNew(['email' => $mailbox->address]);
        $user->fill([
            'name' => $mailbox->name,
            'organization_id' => $mailbox->organization_id,
            'role' => $user->exists ? $user->role : UserRole::User,
            'is_active' => true,
        ]);
        $user->password = $password;
        $user->mail_password = $password;
        $user->save();

        MailboxMember::updateOrCreate(['mailbox_id' => $mailbox->id, 'user_id' => $user->id], ['role' => $role]);

        return $user;
    }

    public function addMember(Mailbox $mailbox, User $user, string $role = MailboxMember::ROLE_MEMBER): void
    {
        MailboxMember::updateOrCreate(['mailbox_id' => $mailbox->id, 'user_id' => $user->id], ['role' => $role]);
        $this->audit->log('mailbox.member_added', $mailbox, ['user_id' => $user->id, 'role' => $role]);
    }

    public function removeMember(Mailbox $mailbox, User $user): void
    {
        MailboxMember::where(['mailbox_id' => $mailbox->id, 'user_id' => $user->id])->delete();
        $this->audit->log('mailbox.member_removed', $mailbox, ['user_id' => $user->id]);
    }

    protected function assertDomainCapacity(Domain $domain): void
    {
        if ($domain->mailboxes()->count() >= $domain->max_mailboxes) {
            throw ValidationException::withMessages(['local_part' => "Domain {$domain->name} reached its mailbox limit ({$domain->max_mailboxes})."]);
        }

        $org = $domain->organization;
        if ($org->max_mailboxes && $org->mailboxes()->count() >= $org->max_mailboxes) {
            throw ValidationException::withMessages(['local_part' => "Organization reached its mailbox limit ({$org->max_mailboxes})."]);
        }
    }

    public function verifyPassword(Mailbox $mailbox, string $password): bool
    {
        return Hash::check($password, $mailbox->users()->first()?->password ?? '');
    }
}
