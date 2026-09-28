<?php

namespace App\Providers;

use App\Models\Alias;
use App\Models\AuditLog;
use App\Models\Domain;
use App\Models\Mailbox;
use App\Models\Organization;
use App\Models\User;
use App\Policies\AliasPolicy;
use App\Policies\AuditLogPolicy;
use App\Policies\DomainPolicy;
use App\Policies\MailboxPolicy;
use App\Policies\OrganizationPolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(Organization::class, OrganizationPolicy::class);
        Gate::policy(Domain::class, DomainPolicy::class);
        Gate::policy(Mailbox::class, MailboxPolicy::class);
        Gate::policy(Alias::class, AliasPolicy::class);
        Gate::policy(AuditLog::class, AuditLogPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
    }
}
