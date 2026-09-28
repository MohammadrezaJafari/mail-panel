<?php

namespace Tests\Feature\Api;

use App\Mail\Client\ImapClientFactory;
use App\Mail\Client\SmtpSender;
use App\Models\Domain;
use App\Models\ScheduledMessage;
use App\Models\User;
use App\Services\MailboxService;
use App\Services\ScheduledMailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class DeferredMailTest extends TestCase
{
    use RefreshDatabase;

    protected function user(): User
    {
        $domain = Domain::factory()->create(['name' => 'acme.test']);
        app(MailboxService::class)->create($domain, ['local_part' => 'ali', 'name' => 'Ali'], 'Passw0rd-long');

        return User::where('email', 'ali@acme.test')->firstOrFail();
    }

    public function test_message_can_be_scheduled_listed_and_cancelled(): void
    {
        Storage::fake('local');
        $user = $this->user();
        $this->actingAs($user, 'sanctum');

        $this->postJson('/api/v1/mail/scheduled', ['to' => ['bob@example.com'], 'subject' => 'Later', 'send_at' => now()->subMinute()->toIso8601String()])
            ->assertStatus(422);

        $response = $this->post('/api/v1/mail/scheduled', [
            'to' => ['bob@example.com'],
            'subject' => 'Later',
            'html' => '<p>hi</p>',
            'send_at' => now()->addHour()->toIso8601String(),
            'attachments' => [UploadedFile::fake()->create('a.txt', 1, 'text/plain')],
        ], ['Accept' => 'application/json']);
        $response->assertCreated()->assertJsonPath('data.subject', 'Later')->assertJsonPath('data.attachments', 1);

        $id = $response->json('data.id');
        $this->assertCount(1, Storage::disk('local')->allFiles('scheduled'));

        $this->getJson('/api/v1/mail/scheduled')->assertOk()->assertJsonCount(1, 'data');

        $this->deleteJson("/api/v1/mail/scheduled/{$id}")->assertOk();
        $this->assertDatabaseCount('scheduled_messages', 0);
        $this->assertCount(0, Storage::disk('local')->allFiles('scheduled'));
    }

    public function test_due_messages_are_sent_through_smtp(): void
    {
        Storage::fake('local');
        $user = $this->user();
        $user->forceFill(['mail_password' => 'Passw0rd-long'])->save();

        $sent = [];
        $this->mock(SmtpSender::class, function ($mock) use (&$sent) {
            $mock->shouldReceive('build')->once()->andReturnUsing(fn (User $u, array $data) => (new Email)->from($u->email)->to(...$data['to'])->subject($data['subject'])->text('x'));
            $mock->shouldReceive('send')->once()->andReturnUsing(function (User $u, Email $email) use (&$sent) {
                $sent[] = $email->getSubject();
            });
        });
        $this->mock(ImapClientFactory::class, function ($mock) {
            $mock->shouldReceive('forUser')->andThrow(new \RuntimeException('no imap in tests'));
        });

        $due = app(ScheduledMailService::class)->schedule($user, ['to' => ['bob@example.com'], 'subject' => 'Due now'], [], now()->subMinute());
        app(ScheduledMailService::class)->schedule($user, ['to' => ['bob@example.com'], 'subject' => 'Future'], [], now()->addDay());

        $this->artisan('mail:process-deferred')->assertSuccessful();

        $this->assertSame(['Due now'], $sent);
        $this->assertSame(ScheduledMessage::STATUS_SENT, $due->fresh()->status);
        $this->assertSame(1, ScheduledMessage::where('status', ScheduledMessage::STATUS_PENDING)->count());
    }

    public function test_send_fails_gracefully_without_credentials(): void
    {
        $user = $this->user();
        $user->forceFill(['mail_password' => null])->save();
        $due = app(ScheduledMailService::class)->schedule($user, ['to' => ['bob@example.com'], 'subject' => 'x'], [], now()->subMinute());

        $this->artisan('mail:process-deferred')->assertSuccessful();

        $this->assertSame(ScheduledMessage::STATUS_FAILED, $due->fresh()->status);
        $this->assertStringContainsString('credentials', $due->fresh()->error);
    }
}
