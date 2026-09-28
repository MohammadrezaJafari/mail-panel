<?php

namespace Tests\Feature\Api;

use App\Dav\CalendarService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CalendarApiTest extends TestCase
{
    use RefreshDatabase;

    protected function user(): User
    {
        return User::factory()->create(['email' => 'ali@acme.test', 'mail_password' => 'secret']);
    }

    protected function calendarsXml(): string
    {
        return <<<'XML'
<?xml version="1.0"?>
<D:multistatus xmlns:D="DAV:" xmlns:C="urn:ietf:params:xml:ns:caldav" xmlns:ical="http://apple.com/ns/ical/">
  <D:response><D:href>/SOGo/dav/ali@acme.test/Calendar/</D:href><D:propstat><D:prop><D:resourcetype><D:collection/></D:resourcetype></D:prop><D:status>HTTP/1.1 200 OK</D:status></D:propstat></D:response>
  <D:response><D:href>/SOGo/dav/ali@acme.test/Calendar/personal/</D:href><D:propstat><D:prop>
    <D:resourcetype><D:collection/><C:calendar/></D:resourcetype><D:displayname>Personal Calendar</D:displayname><ical:calendar-color>#0F6CBDFF</ical:calendar-color>
    <C:supported-calendar-component-set><C:comp name="VEVENT"/><C:comp name="VTODO"/></C:supported-calendar-component-set>
  </D:prop><D:status>HTTP/1.1 200 OK</D:status></D:propstat></D:response>
</D:multistatus>
XML;
    }

    protected function eventsXml(): string
    {
        $ics = "BEGIN:VCALENDAR\nVERSION:2.0\nPRODID:test\nBEGIN:VEVENT\nUID:e1\nSUMMARY:Standup\nLOCATION:Room 1\nDTSTART:20260928T090000Z\nDTEND:20260928T091500Z\nRRULE:FREQ=DAILY;COUNT=3\nEND:VEVENT\nEND:VCALENDAR";
        $allDay = "BEGIN:VCALENDAR\nVERSION:2.0\nPRODID:test\nBEGIN:VEVENT\nUID:e2\nSUMMARY:Holiday\nDTSTART;VALUE=DATE:20260929\nDTEND;VALUE=DATE:20260930\nEND:VEVENT\nEND:VCALENDAR";

        return '<?xml version="1.0"?><D:multistatus xmlns:D="DAV:" xmlns:C="urn:ietf:params:xml:ns:caldav">'
            .'<D:response><D:href>/SOGo/dav/ali@acme.test/Calendar/personal/e1.ics</D:href><D:propstat><D:prop><D:getetag>"1"</D:getetag><C:calendar-data>'.htmlspecialchars($ics).'</C:calendar-data></D:prop><D:status>HTTP/1.1 200 OK</D:status></D:propstat></D:response>'
            .'<D:response><D:href>/SOGo/dav/ali@acme.test/Calendar/personal/e2.ics</D:href><D:propstat><D:prop><D:getetag>"2"</D:getetag><C:calendar-data>'.htmlspecialchars($allDay).'</C:calendar-data></D:prop><D:status>HTTP/1.1 200 OK</D:status></D:propstat></D:response>'
            .'</D:multistatus>';
    }

    public function test_lists_calendars_and_expands_events(): void
    {
        config(['mailprovider.dav.base_url' => 'https://mail.example.com/SOGo/dav/']);
        Http::fake([
            'mail.example.com/SOGo/dav/ali%40acme.test/Calendar/' => Http::response($this->calendarsXml(), 207),
            'mail.example.com/SOGo/dav/ali@acme.test/Calendar/personal/' => Http::response($this->eventsXml(), 207),
        ]);

        $this->actingAs($this->user(), 'sanctum');

        $this->getJson('/api/v1/calendar/calendars')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Personal Calendar')
            ->assertJsonPath('data.0.color', '#0f6cbd');

        $response = $this->getJson('/api/v1/calendar/events?start=2026-09-27T00:00:00Z&end=2026-10-05T00:00:00Z')->assertOk();
        $events = collect($response->json('data'));

        $this->assertCount(4, $events); // 3 daily occurrences + 1 all-day
        $this->assertSame(3, $events->where('summary', 'Standup')->count());
        $this->assertTrue($events->firstWhere('summary', 'Holiday')['all_day']);
        $this->assertSame('2026-09-29', $events->firstWhere('summary', 'Holiday')['start']);
        $this->assertSame('#0f6cbd', $events->first()['color']);

        Http::assertSent(fn ($request) => $request->method() === 'REPORT' && str_contains($request->body(), 'time-range'));
    }

    public function test_creates_and_deletes_an_event(): void
    {
        config(['mailprovider.dav.base_url' => 'https://mail.example.com/SOGo/dav/']);
        Http::fake(fn ($request) => Http::response('', $request->method() === 'PUT' ? 201 : 204, ['ETag' => '"new"']));

        $this->actingAs($this->user(), 'sanctum');
        $calendarId = CalendarService::encode('/SOGo/dav/ali@acme.test/Calendar/personal/');

        $created = $this->postJson('/api/v1/calendar/events', [
            'calendar_id' => $calendarId,
            'summary' => 'Planning',
            'start' => '2026-10-01T10:00:00+03:30',
            'end' => '2026-10-01T11:00:00+03:30',
            'all_day' => false,
        ])->assertCreated();

        $created->assertJsonPath('data.summary', 'Planning')->assertJsonPath('data.start', '2026-10-01T06:30:00+00:00')->assertJsonPath('data.etag', '"new"');

        Http::assertSent(fn ($request) => $request->method() === 'PUT' && str_contains($request->body(), 'SUMMARY:Planning') && $request->hasHeader('If-None-Match', '*'));

        $this->deleteJson('/api/v1/calendar/events/'.$created->json('data.id'))->assertOk();
        Http::assertSent(fn ($request) => $request->method() === 'DELETE' && str_ends_with($request->url(), '.ics'));
    }

    public function test_requires_mail_credentials(): void
    {
        $user = User::factory()->create(['mail_password' => null]);
        $this->actingAs($user, 'sanctum');

        $this->getJson('/api/v1/calendar/calendars')->assertStatus(409)->assertJsonPath('code', 'mail_credentials_missing');
    }
}
