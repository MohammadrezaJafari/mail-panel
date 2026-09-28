<?php

namespace Tests\Feature;

use App\Enums\MailboxStatus;
use App\Mail\Contracts\MailProvider;
use App\Mail\Providers\NullProvider;
use App\Models\AuditLog;
use App\Models\Domain;
use App\Models\Organization;
use App\Models\User;
use App\Services\AliasService;
use App\Services\DomainService;
use App\Services\MailboxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MailboxServiceTest extends TestCase
{
    use RefreshDatabase;

    protected NullProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = app(MailProvider::class);
        $this->assertInstanceOf(NullProvider::class, $this->provider);
    }

    public function test_domain_and_mailbox_lifecycle_goes_through_provider_and_audit(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin);

        $org = Organization::factory()->create();
        $domain = app(DomainService::class)->create($org, ['name' => 'Example.COM']);

        $this->assertSame('example.com', $domain->name);
        $this->assertSame('example.com', $domain->externalId('fake'));

        $mailbox = app(MailboxService::class)->create($domain, ['local_part' => 'Ali', 'name' => 'Ali'], 'secret-pass-123');

        $this->assertSame('ali@example.com', $mailbox->address);
        $this->assertSame('secret-pass-123', $this->provider->passwords['ali@example.com']);
        $this->assertDatabaseHas('users', ['email' => 'ali@example.com']);
        $this->assertDatabaseHas('mailbox_members', ['mailbox_id' => $mailbox->id]);

        app(MailboxService::class)->suspend($mailbox);
        $this->assertSame(MailboxStatus::Suspended, $mailbox->fresh()->status);

        app(MailboxService::class)->setQuota($mailbox, 2048);
        $this->assertSame(2048, $mailbox->fresh()->quota_mb);

        $alias = app(AliasService::class)->create($domain, ['address' => 'a.rezaei@example.com'], $mailbox);
        $this->assertSame(['ali@example.com'], $alias->goto);

        $calls = array_column($this->provider->calls, 0);
        $this->assertSame(['createDomain', 'createMailbox', 'suspendMailbox', 'setQuota', 'createAlias'], $calls);

        $this->assertEqualsCanonicalizing(
            ['domain.created', 'mailbox.created', 'mailbox.suspended', 'mailbox.quota_changed', 'alias.created'],
            AuditLog::pluck('action')->all(),
        );
    }

    public function test_quota_cannot_exceed_domain_maximum(): void
    {
        $domain = Domain::factory()->create(['max_quota_mb' => 1000]);
        $mailbox = app(MailboxService::class)->create($domain, ['local_part' => 'x', 'name' => 'X'], 'pw');

        $this->expectException(ValidationException::class);
        app(MailboxService::class)->setQuota($mailbox, 5000);
    }

    public function test_alias_must_belong_to_domain(): void
    {
        $domain = Domain::factory()->create(['name' => 'one.test']);

        $this->expectException(ValidationException::class);
        app(AliasService::class)->create($domain, ['address' => 'x@two.test']);
    }
}
