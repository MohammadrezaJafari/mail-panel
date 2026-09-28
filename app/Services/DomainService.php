<?php

namespace App\Services;

use App\Enums\DomainStatus;
use App\Mail\Contracts\MailProvider;
use App\Models\Domain;
use App\Models\Organization;
use Illuminate\Support\Facades\DB;

class DomainService
{
    public function __construct(
        protected MailProvider $provider,
        protected AuditLogger $audit,
        protected DnsChecker $dns,
    ) {}

    public function create(Organization $organization, array $attributes): Domain
    {
        return DB::transaction(function () use ($organization, $attributes) {
            $domain = $organization->domains()->create($attributes + ['status' => DomainStatus::Active])->refresh();
            $this->provider->createDomain($domain);
            $this->audit->log('domain.created', $domain, ['name' => $domain->name]);

            return $domain;
        });
    }

    public function update(Domain $domain, array $attributes): Domain
    {
        return DB::transaction(function () use ($domain, $attributes) {
            $domain->fill($attributes)->save();
            $this->provider->updateDomain($domain);
            $this->audit->log('domain.updated', $domain, $domain->getChanges());

            return $domain;
        });
    }

    public function setStatus(Domain $domain, DomainStatus $status): Domain
    {
        return $this->update($domain, ['status' => $status]);
    }

    public function delete(Domain $domain): void
    {
        DB::transaction(function () use ($domain) {
            $this->provider->deleteDomain($domain);
            $this->audit->log('domain.deleted', $domain, ['name' => $domain->name]);
            $domain->delete();
        });
    }

    public function verifyDns(Domain $domain): array
    {
        $dkim = $this->provider->getDkim($domain);
        $status = $this->dns->check($domain->name, $dkim);

        $domain->forceFill(['dns_status' => $status, 'dns_checked_at' => now()])->save();
        $this->audit->log('domain.dns_checked', $domain, ['summary' => collect($status)->map->status->all()]);

        return $status;
    }
}
