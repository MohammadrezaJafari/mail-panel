<?php

namespace App\Services;

use App\Mail\Contracts\MailProvider;
use App\Models\Alias;
use App\Models\Domain;
use App\Models\Mailbox;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AliasService
{
    public function __construct(
        protected MailProvider $provider,
        protected AuditLogger $audit,
    ) {}

    /**
     * @param  array{address: string, goto?: array<int, string>, is_active?: bool}  $attributes
     */
    public function create(Domain $domain, array $attributes, ?Mailbox $mailbox = null): Alias
    {
        $address = strtolower(trim($attributes['address']));

        if (! str_ends_with($address, '@'.$domain->name)) {
            throw ValidationException::withMessages(['address' => "Alias must belong to {$domain->name}."]);
        }

        if ($domain->aliases()->count() >= $domain->max_aliases) {
            throw ValidationException::withMessages(['address' => "Domain {$domain->name} reached its alias limit ({$domain->max_aliases})."]);
        }

        if (Mailbox::where('address', $address)->exists() || Alias::where('address', $address)->exists()) {
            throw ValidationException::withMessages(['address' => 'This address is already in use.']);
        }

        return DB::transaction(function () use ($domain, $attributes, $mailbox, $address) {
            $alias = new Alias([
                'address' => $address,
                'goto' => $attributes['goto'] ?? [$mailbox?->address],
                'is_active' => $attributes['is_active'] ?? true,
            ]);
            $alias->organization_id = $domain->organization_id;
            $alias->domain_id = $domain->id;
            $alias->mailbox_id = $mailbox?->id;
            $alias->save();
            $alias->refresh();

            $this->provider->createAlias($alias);
            $this->audit->log('alias.created', $alias, ['address' => $alias->address, 'goto' => $alias->goto]);

            return $alias;
        });
    }

    public function update(Alias $alias, array $attributes): Alias
    {
        return DB::transaction(function () use ($alias, $attributes) {
            $alias->fill($attributes)->save();
            $this->provider->updateAlias($alias);
            $this->audit->log('alias.updated', $alias, $alias->getChanges());

            return $alias;
        });
    }

    public function delete(Alias $alias): void
    {
        DB::transaction(function () use ($alias) {
            $this->provider->deleteAlias($alias);
            $this->audit->log('alias.deleted', $alias, ['address' => $alias->address]);
            $alias->delete();
        });
    }
}
