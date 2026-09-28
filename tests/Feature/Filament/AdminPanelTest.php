<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Aliases\AliasResource;
use App\Filament\Resources\AuditLogs\AuditLogResource;
use App\Filament\Resources\Domains\DomainResource;
use App\Filament\Resources\Domains\Pages\CreateDomain;
use App\Filament\Resources\Mailboxes\MailboxResource;
use App\Filament\Resources\Mailboxes\Pages\CreateMailbox;
use App\Filament\Resources\Organizations\OrganizationResource;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Widgets\MailHealthWidget;
use App\Filament\Widgets\MailStatsOverview;
use App\Models\Alias;
use App\Models\AuditLog;
use App\Models\Domain;
use App\Models\Mailbox;
use App\Models\Organization;
use App\Models\User;
use App\Services\MailboxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->superAdmin()->create();
        $this->actingAs($this->admin);
    }

    public function test_dashboard_and_index_pages_render(): void
    {
        $domain = Domain::factory()->create();
        $mailbox = app(MailboxService::class)->create($domain, ['local_part' => 'ali', 'name' => 'Ali'], 'Passw0rd-long');
        Alias::factory()->create(['domain_id' => $domain->id, 'organization_id' => $domain->organization_id]);

        $this->get('/admin')->assertOk();
        Livewire::test(MailHealthWidget::class)->assertSee('Mail platform health')->assertSee('smtp');
        Livewire::test(MailStatsOverview::class)->assertSee('Mailboxes');

        foreach ([OrganizationResource::class, DomainResource::class, MailboxResource::class, AliasResource::class, AuditLogResource::class, UserResource::class] as $resource) {
            $this->get($resource::getUrl('index'))->assertOk();
        }

        $this->get(DomainResource::getUrl('view', ['record' => $domain]))->assertOk()->assertSee($domain->name);
        $this->get(DomainResource::getUrl('edit', ['record' => $domain]))->assertOk();
        $this->get(MailboxResource::getUrl('view', ['record' => $mailbox]))->assertOk()->assertSee('ali@'.$domain->name);
        $this->get(MailboxResource::getUrl('edit', ['record' => $mailbox]))->assertOk();
        $this->get(MailboxResource::getUrl('create'))->assertOk();
        $this->get(AliasResource::getUrl('create'))->assertOk();
        $this->get(AuditLogResource::getUrl('view', ['record' => AuditLog::firstOrFail()]))->assertOk();
    }

    public function test_domain_and_mailbox_can_be_created_from_the_panel(): void
    {
        $org = Organization::factory()->create();

        Livewire::test(CreateDomain::class)
            ->fillForm([
                'organization_id' => $org->id,
                'name' => 'panel.test',
                'status' => 'active',
                'max_mailboxes' => 10,
                'max_aliases' => 20,
                'domain_quota_mb' => 10000,
                'default_quota_mb' => 1024,
                'max_quota_mb' => 2048,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $domain = Domain::where('name', 'panel.test')->firstOrFail();
        $this->assertSame('panel.test', $domain->externalId('fake'));

        Livewire::test(CreateMailbox::class)
            ->fillForm([
                'domain_id' => $domain->id,
                'local_part' => 'sara',
                'name' => 'Sara',
                'quota_mb' => 1024,
                'password' => 'Passw0rd-long',
                'password_confirmation' => 'Passw0rd-long',
                'is_shared' => false,
                'create_user' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('mailboxes', ['address' => 'sara@panel.test']);
        $this->assertDatabaseHas('users', ['email' => 'sara@panel.test']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'mailbox.created']);
    }

    public function test_org_admin_only_sees_own_organization(): void
    {
        $mine = Domain::factory()->create();
        $other = Domain::factory()->create();
        $owner = User::factory()->organizationOwner()->create(['organization_id' => $mine->organization_id]);

        $this->actingAs($owner);

        $this->get(DomainResource::getUrl('index'))->assertOk()->assertSee($mine->name)->assertDontSee($other->name);
        $this->get(DomainResource::getUrl('view', ['record' => $other]))->assertNotFound();
        $this->assertFalse(Mailbox::query()->exists());
    }

    public function test_regular_users_cannot_access_the_panel(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/admin')->assertForbidden();
    }
}
