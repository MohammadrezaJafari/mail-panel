<?php

namespace App\Dav;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Component\VEvent;
use Sabre\VObject\Reader;

class CalendarService
{
    public function __construct(protected DavClient $dav) {}

    public static function forUser(User $user): self
    {
        return new self(DavClient::forUser($user));
    }

    // ------------------------------------------------------------ calendars

    /** @return array<int, array{id: string, href: string, name: string, color: string|null, description: string|null}> */
    public function calendars(): array
    {
        $home = $this->dav->principalUrl().'Calendar/';
        $items = $this->dav->propfind($home, ['d:resourcetype', 'd:displayname', 'ical:calendar-color', 'c:calendar-description', 'c:supported-calendar-component-set']);

        $out = [];
        foreach ($items as $href => $item) {
            $types = $item['props']['resourcetype'] ?? [];
            if (! in_array('calendar', $types, true)) {
                continue;
            }
            $components = $item['props']['supported-calendar-component-set'] ?? ['VEVENT'];
            if ($components && ! in_array('VEVENT', $components, true)) {
                continue;
            }
            $out[] = [
                'id' => self::encode($href),
                'href' => $href,
                'name' => $item['props']['displayname'] ?: basename(rtrim($href, '/')),
                'color' => $this->normalizeColor($item['props']['calendar-color'] ?? null),
                'description' => $item['props']['calendar-description'] ?? null,
            ];
        }

        usort($out, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

        return $out;
    }

    // --------------------------------------------------------------- events

    /** @return array<int, array<string, mixed>> */
    public function events(string $calendarHref, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $body = '<?xml version="1.0" encoding="utf-8"?>'
            .'<c:calendar-query xmlns:d="DAV:" xmlns:c="urn:ietf:params:xml:ns:caldav">'
            .'<d:prop><d:getetag/><c:calendar-data/></d:prop>'
            .'<c:filter><c:comp-filter name="VCALENDAR"><c:comp-filter name="VEVENT">'
            .'<c:time-range start="'.$start->utc()->format('Ymd\THis\Z').'" end="'.$end->utc()->format('Ymd\THis\Z').'"/>'
            .'</c:comp-filter></c:comp-filter></c:filter></c:calendar-query>';

        $items = $this->dav->report($this->dav->absolute($calendarHref), $body);
        $calendarId = self::encode($calendarHref);
        $events = [];

        foreach ($items as $href => $item) {
            $ics = $item['props']['calendar-data'] ?? null;
            if (! $ics) {
                continue;
            }
            foreach ($this->parseEvents($ics, $start, $end) as $event) {
                $events[] = $event + ['id' => self::encode($href), 'href' => $href, 'etag' => $item['props']['getetag'] ?? null, 'calendar_id' => $calendarId];
            }
        }

        usort($events, fn ($a, $b) => strcmp($a['start'], $b['start']));

        return $events;
    }

    /** @return array<int, array<string, mixed>> */
    public function parseEvents(string $ics, ?CarbonImmutable $start = null, ?CarbonImmutable $end = null): array
    {
        /** @var VCalendar $vcal */
        $vcal = Reader::read($ics, Reader::OPTION_FORGIVING);
        $recurring = false;

        foreach ($vcal->select('VEVENT') as $vevent) {
            if (isset($vevent->RRULE) || isset($vevent->RDATE)) {
                $recurring = true;
                break;
            }
        }

        if ($recurring && $start && $end) {
            try {
                $vcal = $vcal->expand($start->toDateTimeImmutable(), $end->toDateTimeImmutable());
            } catch (\Throwable) {
                // fall back to the master event
            }
        }

        $out = [];
        foreach ($vcal->select('VEVENT') as $vevent) {
            /** @var VEvent $vevent */
            $out[] = $this->normalize($vevent, $recurring);
        }

        return $out;
    }

    protected function normalize(VEvent $vevent, bool $recurring): array
    {
        $dtstart = $vevent->DTSTART;
        $allDay = $dtstart && ! $dtstart->hasTime();
        $startDt = $dtstart?->getDateTime();
        $endDt = $vevent->DTEND?->getDateTime();

        if (! $endDt && $startDt) {
            $duration = $vevent->DURATION?->getDateInterval();
            $endDt = $duration ? $startDt->add($duration) : ($allDay ? $startDt->modify('+1 day') : $startDt->modify('+1 hour'));
        }

        $fmt = fn (?\DateTimeInterface $d) => $d ? ($allDay ? $d->format('Y-m-d') : CarbonImmutable::instance($d)->utc()->toIso8601String()) : null;

        return [
            'uid' => (string) $vevent->UID,
            'summary' => (string) ($vevent->SUMMARY ?? ''),
            'description' => (string) ($vevent->DESCRIPTION ?? ''),
            'location' => (string) ($vevent->LOCATION ?? ''),
            'start' => $fmt($startDt),
            'end' => $fmt($endDt),
            'all_day' => $allDay,
            'recurring' => $recurring,
            'recurrence_id' => isset($vevent->{'RECURRENCE-ID'}) ? (string) $vevent->{'RECURRENCE-ID'} : null,
            'status' => (string) ($vevent->STATUS ?? ''),
        ];
    }

    /**
     * @param  array{summary: string, description?: string|null, location?: string|null, start: string, end: string, all_day: bool}  $data
     */
    public function create(string $calendarHref, array $data): array
    {
        $uid = (string) Str::uuid();
        $href = rtrim($calendarHref, '/').'/'.$uid.'.ics';
        $ics = $this->buildIcs($uid, $data);
        $etag = $this->dav->put($this->dav->absolute($href), $ics, 'text/calendar; charset=utf-8', null, true);

        return $this->parseEvents($ics)[0] + ['id' => self::encode($href), 'href' => $href, 'etag' => $etag, 'calendar_id' => self::encode($calendarHref)];
    }

    public function update(string $href, array $data): array
    {
        $url = $this->dav->absolute($href);
        $current = $this->dav->get($url);
        /** @var VCalendar $vcal */
        $vcal = Reader::read($current['body'], Reader::OPTION_FORGIVING);
        $vevent = $vcal->select('VEVENT')[0] ?? null;
        if (! $vevent) {
            throw new \RuntimeException('Event not found');
        }

        $this->applyData($vevent, $data);
        $vevent->{'LAST-MODIFIED'} = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $vevent->SEQUENCE = (int) ((string) ($vevent->SEQUENCE ?? 0)) + 1;

        $ics = $vcal->serialize();
        $etag = $this->dav->put($url, $ics, 'text/calendar; charset=utf-8', $current['etag']);
        $calendarHref = substr($href, 0, strrpos($href, '/') + 1);

        return $this->parseEvents($ics)[0] + ['id' => self::encode($href), 'href' => $href, 'etag' => $etag, 'calendar_id' => self::encode($calendarHref)];
    }

    public function delete(string $href): void
    {
        $this->dav->delete($this->dav->absolute($href));
    }

    protected function buildIcs(string $uid, array $data): string
    {
        $vcal = new VCalendar;
        $vcal->PRODID = '-//mail-panel//Calendar//EN';
        $vevent = $vcal->add('VEVENT', ['UID' => $uid]);
        $vevent->DTSTAMP = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $vevent->CREATED = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->applyData($vevent, $data);

        return $vcal->serialize();
    }

    protected function applyData(VEvent $vevent, array $data): void
    {
        $vevent->SUMMARY = (string) ($data['summary'] ?? '');
        foreach (['description' => 'DESCRIPTION', 'location' => 'LOCATION'] as $key => $prop) {
            if (array_key_exists($key, $data)) {
                if (filled($data[$key])) {
                    $vevent->$prop = (string) $data[$key];
                } else {
                    unset($vevent->$prop);
                }
            }
        }

        if (isset($data['start'], $data['end'])) {
            unset($vevent->DTSTART, $vevent->DTEND, $vevent->DURATION);
            if (! empty($data['all_day'])) {
                $vevent->add('DTSTART', new \DateTimeImmutable(substr($data['start'], 0, 10)), ['VALUE' => 'DATE']);
                $vevent->add('DTEND', new \DateTimeImmutable(substr($data['end'], 0, 10)), ['VALUE' => 'DATE']);
            } else {
                $vevent->DTSTART = CarbonImmutable::parse($data['start'])->utc()->toDateTimeImmutable();
                $vevent->DTEND = CarbonImmutable::parse($data['end'])->utc()->toDateTimeImmutable();
            }
        }
    }

    protected function normalizeColor(?string $color): ?string
    {
        if (! $color) {
            return null;
        }

        // Apple-style #RRGGBBAA -> #RRGGBB
        return preg_match('/^#([0-9a-f]{6})/i', $color, $m) ? '#'.strtolower($m[1]) : null;
    }

    public static function encode(string $href): string
    {
        return rtrim(strtr(base64_encode($href), '+/', '-_'), '=');
    }

    public static function decode(string $id): string
    {
        return base64_decode(strtr($id, '-_', '+/')) ?: '';
    }
}
