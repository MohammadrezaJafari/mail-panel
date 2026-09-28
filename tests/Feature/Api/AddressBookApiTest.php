<?php

namespace Tests\Feature\Api;

use App\Dav\CalendarService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AddressBookApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_searches_and_creates_contacts(): void
    {
        config(['mailprovider.dav.base_url' => 'https://mail.example.com/SOGo/dav/']);
        $books = '<?xml version="1.0"?><D:multistatus xmlns:D="DAV:" xmlns:card="urn:ietf:params:xml:ns:carddav">'
            .'<D:response><D:href>/SOGo/dav/ali@acme.test/Contacts/personal/</D:href><D:propstat><D:prop><D:resourcetype><D:collection/><card:addressbook/></D:resourcetype><D:displayname>Personal Address Book</D:displayname></D:prop><D:status>HTTP/1.1 200 OK</D:status></D:propstat></D:response>'
            .'</D:multistatus>';
        $vcf = "BEGIN:VCARD\nVERSION:3.0\nUID:c1\nFN:Sara Ahmadi\nN:Ahmadi;Sara;;;\nEMAIL;TYPE=WORK:sara@acme.test\nTEL;TYPE=CELL:+98912\nORG:ACME\nEND:VCARD";
        $vcf2 = "BEGIN:VCARD\nVERSION:3.0\nUID:c2\nFN:Bob Builder\nEMAIL:bob@example.com\nEND:VCARD";
        $contacts = '<?xml version="1.0"?><D:multistatus xmlns:D="DAV:" xmlns:card="urn:ietf:params:xml:ns:carddav">'
            .'<D:response><D:href>/SOGo/dav/ali@acme.test/Contacts/personal/c1.vcf</D:href><D:propstat><D:prop><D:getetag>"a"</D:getetag><card:address-data>'.htmlspecialchars($vcf).'</card:address-data></D:prop><D:status>HTTP/1.1 200 OK</D:status></D:propstat></D:response>'
            .'<D:response><D:href>/SOGo/dav/ali@acme.test/Contacts/personal/c2.vcf</D:href><D:propstat><D:prop><D:getetag>"b"</D:getetag><card:address-data>'.htmlspecialchars($vcf2).'</card:address-data></D:prop><D:status>HTTP/1.1 200 OK</D:status></D:propstat></D:response>'
            .'</D:multistatus>';

        Http::fake(function ($request) use ($books, $contacts) {
            return match (true) {
                $request->method() === 'PROPFIND' => Http::response($books, 207),
                $request->method() === 'REPORT' => Http::response($contacts, 207),
                $request->method() === 'PUT' => Http::response('', 201, ['ETag' => '"c"']),
                default => Http::response('', 204),
            };
        });

        $user = User::factory()->create(['email' => 'ali@acme.test', 'mail_password' => 'secret']);
        $this->actingAs($user, 'sanctum');

        $this->getJson('/api/v1/addressbook/contacts')->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Bob Builder')
            ->assertJsonPath('data.1.emails.0.value', 'sara@acme.test')
            ->assertJsonPath('data.1.phones.0.type', 'cell')
            ->assertJsonPath('books.0.name', 'Personal Address Book');

        $this->getJson('/api/v1/addressbook/contacts?q=acme')->assertOk()->assertJsonCount(1, 'data');

        $bookId = CalendarService::encode('/SOGo/dav/ali@acme.test/Contacts/personal/');
        $this->postJson('/api/v1/addressbook/contacts', [
            'book_id' => $bookId,
            'first_name' => 'Nima',
            'last_name' => 'Rad',
            'emails' => [['value' => 'nima@example.com', 'type' => 'home']],
        ])->assertCreated()->assertJsonPath('data.name', 'Nima Rad')->assertJsonPath('data.last_name', 'Rad');

        Http::assertSent(fn ($r) => $r->method() === 'PUT' && str_contains($r->body(), 'FN:Nima Rad') && str_contains($r->body(), 'EMAIL;TYPE=HOME:nima@example.com'));
    }
}
