<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\User;
use App\Services\AliasService;
use App\Services\DomainService;
use App\Services\MailboxService;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seeds a super admin plus a demo organization. Uses the configured
     * MailProvider, so with MAIL_PROVIDER=fake this is safe to run locally.
     */
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@example.com')],
            [
                'name' => 'Super Admin',
                'password' => env('ADMIN_PASSWORD', 'password'),
                'role' => UserRole::SuperAdmin,
                'is_active' => true,
            ],
        );

        if (! app()->environment('local', 'testing')) {
            return;
        }

        $org = Organization::firstOrCreate(['slug' => 'acme'], ['name' => 'ACME Inc.', 'status' => 'active']);
        $admin->forceFill(['organization_id' => $org->id])->save();

        $domains = app(DomainService::class);
        $mailboxes = app(MailboxService::class);
        $aliases = app(AliasService::class);

        $domain = $org->domains()->where('name', 'acme.test')->first()
            ?? $domains->create($org, ['name' => 'acme.test', 'description' => 'Demo domain']);

        if (! $domain->mailboxes()->exists()) {
            $ali = $mailboxes->create($domain, ['local_part' => 'ali', 'name' => 'Ali Rezaei'], 'password');
            $mailboxes->create($domain, ['local_part' => 'sara', 'name' => 'Sara Ahmadi'], 'password');
            $support = $mailboxes->create($domain, ['local_part' => 'support', 'name' => 'Support', 'is_shared' => true], 'password', createUser: false);

            $aliases->create($domain, ['address' => 'a.rezaei@acme.test'], $ali);
            $aliases->create($domain, ['address' => 'help@acme.test', 'goto' => [$support->address]]);

            $mailboxes->addMember($support, $ali->users()->first());
        }
    }
}
