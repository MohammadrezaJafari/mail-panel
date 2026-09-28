<?php

namespace Tests\Feature\Api;

use App\Models\Contact;
use App\Models\Domain;
use App\Models\User;
use App\Services\ContactService;
use App\Services\MailboxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_merges_personal_contacts_and_directory(): void
    {
        $domain = Domain::factory()->create(['name' => 'acme.test']);
        app(MailboxService::class)->create($domain, ['local_part' => 'ali', 'name' => 'Ali Rezaei'], 'Passw0rd-long');
        app(MailboxService::class)->create($domain, ['local_part' => 'sara', 'name' => 'Sara Ahmadi'], 'Passw0rd-long');
        $user = User::where('email', 'ali@acme.test')->firstOrFail();

        app(ContactService::class)->remember($user, [['email' => 'Bob@Example.com', 'name' => 'Bob'], ['email' => 'ali@acme.test']]);
        app(ContactService::class)->remember($user, [['email' => 'bob@example.com']]);

        $this->assertSame(2, Contact::where('email', 'bob@example.com')->value('times_used'));
        $this->assertDatabaseMissing('contacts', ['email' => 'ali@acme.test']);

        $this->actingAs($user, 'sanctum');

        $this->getJson('/api/v1/contacts?q=sa')->assertOk()
            ->assertJsonPath('data.0.email', 'sara@acme.test')
            ->assertJsonPath('data.0.source', 'directory');

        $this->getJson('/api/v1/contacts?q=bo')->assertOk()
            ->assertJsonPath('data.0.email', 'bob@example.com')
            ->assertJsonPath('data.0.name', 'Bob');

        $this->getJson('/api/v1/contacts')->assertOk()->assertJsonCount(2, 'data');
    }
}
