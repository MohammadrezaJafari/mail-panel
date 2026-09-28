<?php

namespace Tests\Feature\Api;

use App\Mail\Contracts\MailProvider;
use App\Models\Domain;
use App\Models\User;
use App\Services\MailboxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountApiTest extends TestCase
{
    use RefreshDatabase;

    protected function mailboxUser(): array
    {
        $domain = Domain::factory()->create(['name' => 'acme.test']);
        $mailbox = app(MailboxService::class)->create($domain, ['local_part' => 'ali', 'name' => 'Ali'], 'Passw0rd-long');
        $user = User::where('email', 'ali@acme.test')->firstOrFail();

        return [$mailbox, $user];
    }

    public function test_login_returns_token_and_caches_mail_credentials(): void
    {
        [, $user] = $this->mailboxUser();

        $response = $this->postJson('/api/v1/auth/login', ['email' => 'ali@acme.test', 'password' => 'Passw0rd-long']);

        $response->assertOk()->assertJsonStructure(['token', 'user' => ['email']]);
        $this->assertSame('Passw0rd-long', $user->fresh()->mail_password);
    }

    public function test_login_rejects_bad_password(): void
    {
        $this->mailboxUser();

        $this->postJson('/api/v1/auth/login', ['email' => 'ali@acme.test', 'password' => 'nope'])
            ->assertStatus(422);
    }

    public function test_me_returns_mailbox_and_settings_can_be_updated(): void
    {
        [$mailbox, $user] = $this->mailboxUser();

        $this->actingAs($user, 'sanctum');

        $this->getJson('/api/v1/me')->assertOk()
            ->assertJsonPath('mailbox.address', 'ali@acme.test')
            ->assertJsonPath('user.email', 'ali@acme.test');

        $this->putJson('/api/v1/me/mailbox/settings', [
            'forwarding_to' => ['backup@example.com'],
            'forwarding_keep_copy' => false,
            'auto_reply_enabled' => true,
            'auto_reply_subject' => 'Away',
            'auto_reply_body' => 'Back soon',
            'signature' => 'Ali',
        ])->assertOk()->assertJsonPath('data.forwarding_to.0', 'backup@example.com');

        $this->assertTrue($mailbox->fresh()->auto_reply_enabled);
        $this->assertContains('syncMailboxRules', array_column(app(MailProvider::class)->calls, 0));
    }

    public function test_user_can_manage_own_aliases(): void
    {
        [, $user] = $this->mailboxUser();
        $this->actingAs($user, 'sanctum');

        $created = $this->postJson('/api/v1/me/aliases', ['local_part' => 'a.rezaei'])
            ->assertCreated()->assertJsonPath('data.address', 'a.rezaei@acme.test');

        $this->getJson('/api/v1/me/aliases')->assertOk()->assertJsonCount(1, 'data');

        $this->deleteJson('/api/v1/me/aliases/'.$created->json('data.id'))->assertOk();
        $this->assertDatabaseCount('aliases', 0);
    }

    public function test_password_change_requires_current_password(): void
    {
        [, $user] = $this->mailboxUser();
        $this->actingAs($user, 'sanctum');

        $this->putJson('/api/v1/me/password', [
            'current_password' => 'wrong',
            'password' => 'NewPassw0rd-long',
            'password_confirmation' => 'NewPassw0rd-long',
        ])->assertStatus(422);

        $this->putJson('/api/v1/me/password', [
            'current_password' => 'Passw0rd-long',
            'password' => 'NewPassw0rd-long',
            'password_confirmation' => 'NewPassw0rd-long',
        ])->assertOk();

        $this->assertSame('NewPassw0rd-long', app(MailProvider::class)->passwords['ali@acme.test']);
    }

    public function test_mail_endpoints_require_cached_credentials(): void
    {
        [, $user] = $this->mailboxUser();
        $user->forceFill(['mail_password' => null])->save();
        $this->actingAs($user, 'sanctum');

        $this->getJson('/api/v1/mail/folders')->assertStatus(409)->assertJsonPath('code', 'mail_credentials_missing');
    }
}
