<?php

namespace App\Services;

use App\Mail\Client\MailboxClient;
use App\Models\Contact;
use App\Models\Mailbox;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Personal address book learned from sent/received mail, plus the
 * organization directory (mailboxes and aliases of the same organization).
 */
class ContactService
{
    /**
     * @param  array<int, array{email: string, name?: string|null}>  $addresses
     */
    public function remember(User $user, array $addresses, bool $used = true): void
    {
        foreach ($addresses as $address) {
            $email = strtolower(trim((string) ($address['email'] ?? '')));
            if ($email === '' || $email === strtolower($user->email) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $contact = Contact::firstOrNew(['user_id' => $user->id, 'email' => $email]);
            $name = trim((string) ($address['name'] ?? ''));
            if ($name !== '' && ($contact->name === null || $used)) {
                $contact->name = $name;
            }
            if ($used) {
                $contact->times_used = ($contact->times_used ?? 0) + 1;
                $contact->last_used_at = now();
            }
            $contact->save();
        }
    }

    /**
     * Learn contacts from the headers of recent messages in Sent and Inbox.
     */
    public function syncFromMailbox(User $user, MailboxClient $client, int $perFolder = 200): int
    {
        $count = 0;

        foreach (['sent' => true, 'inbox' => false] as $role => $used) {
            $path = $client->folderByRole($role);
            if (! $path) {
                continue;
            }

            $page = $client->messages($path, 1, $perFolder);
            foreach ($page['data'] as $summary) {
                $addresses = $used ? $summary['to'] : array_filter([$summary['from']]);
                $this->remember($user, array_values($addresses), $used);
                $count += count($addresses);
            }
        }

        $user->forceFill(['contacts_synced_at' => now()])->save();

        return $count;
    }

    /**
     * @return Collection<int, array{email: string, name: string|null, source: string}>
     */
    public function search(User $user, string $query, int $limit = 8): Collection
    {
        $q = strtolower(trim($query));
        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%';

        $personal = Contact::query()
            ->where('user_id', $user->id)
            ->when($q !== '', fn ($b) => $b->where(fn ($w) => $w->where('email', 'like', $like)->orWhere('name', 'like', $like)))
            ->orderByDesc('times_used')
            ->orderByDesc('last_used_at')
            ->limit($limit)
            ->get()
            ->map(fn (Contact $c) => ['email' => $c->email, 'name' => $c->name, 'source' => 'personal']);

        $directory = collect();
        if ($user->organization_id) {
            $directory = Mailbox::query()
                ->where('organization_id', $user->organization_id)
                ->where('status', 'active')
                ->where('address', '!=', strtolower($user->email))
                ->when($q !== '', fn ($b) => $b->where(fn ($w) => $w->where('address', 'like', $like)->orWhere('name', 'like', $like)))
                ->orderBy('name')
                ->limit($limit)
                ->get()
                ->map(fn (Mailbox $m) => ['email' => $m->address, 'name' => $m->name, 'source' => 'directory']);
        }

        return $personal->concat($directory)->unique('email')->take($limit)->values();
    }
}
