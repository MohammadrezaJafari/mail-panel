<?php

namespace App\Dav;

use App\Models\User;
use Illuminate\Support\Str;
use Sabre\VObject\Component\VCard;
use Sabre\VObject\Reader;

class AddressBookService
{
    public function __construct(protected DavClient $dav) {}

    public static function forUser(User $user): self
    {
        return new self(DavClient::forUser($user));
    }

    /** @return array<int, array{id: string, href: string, name: string}> */
    public function books(): array
    {
        $home = $this->dav->principalUrl().'Contacts/';
        $items = $this->dav->propfind($home, ['d:resourcetype', 'd:displayname']);

        $out = [];
        foreach ($items as $href => $item) {
            if (! in_array('addressbook', $item['props']['resourcetype'] ?? [], true)) {
                continue;
            }
            $out[] = ['id' => CalendarService::encode($href), 'href' => $href, 'name' => $item['props']['displayname'] ?: basename(rtrim($href, '/'))];
        }
        usort($out, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

        return $out;
    }

    /** @return array<int, array<string, mixed>> */
    public function contacts(string $bookHref): array
    {
        $body = '<?xml version="1.0" encoding="utf-8"?>'
            .'<card:addressbook-query xmlns:d="DAV:" xmlns:card="urn:ietf:params:xml:ns:carddav">'
            .'<d:prop><d:getetag/><card:address-data/></d:prop>'
            .'<card:filter/></card:addressbook-query>';

        $items = $this->dav->report($this->dav->absolute($bookHref), $body);
        $bookId = CalendarService::encode($bookHref);
        $out = [];

        foreach ($items as $href => $item) {
            $vcf = $item['props']['address-data'] ?? null;
            if (! $vcf) {
                continue;
            }
            try {
                $card = Reader::read($vcf, Reader::OPTION_FORGIVING);
            } catch (\Throwable) {
                continue;
            }
            if (! $card instanceof VCard || (isset($card->KIND) && strtolower((string) $card->KIND) === 'group')) {
                continue;
            }
            $out[] = $this->normalize($card) + ['id' => CalendarService::encode($href), 'href' => $href, 'etag' => $item['props']['getetag'] ?? null, 'book_id' => $bookId];
        }

        usort($out, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

        return $out;
    }

    public function normalize(VCard $card): array
    {
        $n = $card->N ? $card->N->getParts() : [];
        $emails = [];
        foreach ($card->select('EMAIL') as $email) {
            $emails[] = ['value' => (string) $email, 'type' => strtolower((string) ($email['TYPE'] ?? 'other'))];
        }
        $phones = [];
        foreach ($card->select('TEL') as $tel) {
            $phones[] = ['value' => (string) $tel, 'type' => strtolower((string) ($tel['TYPE'] ?? 'other'))];
        }

        return [
            'uid' => (string) $card->UID,
            'name' => (string) ($card->FN ?? trim(($n[1] ?? '').' '.($n[0] ?? ''))),
            'first_name' => $n[1] ?? '',
            'last_name' => $n[0] ?? '',
            'emails' => $emails,
            'phones' => $phones,
            'org' => (string) ($card->ORG ?? ''),
            'title' => (string) ($card->TITLE ?? ''),
            'note' => (string) ($card->NOTE ?? ''),
        ];
    }

    public function create(string $bookHref, array $data): array
    {
        $uid = (string) Str::uuid();
        $href = rtrim($bookHref, '/').'/'.$uid.'.vcf';
        $card = new VCard(['UID' => $uid]);
        $this->apply($card, $data);
        $vcf = $card->serialize();
        $etag = $this->dav->put($this->dav->absolute($href), $vcf, 'text/vcard; charset=utf-8', null, true);

        return $this->normalize($card) + ['id' => CalendarService::encode($href), 'href' => $href, 'etag' => $etag, 'book_id' => CalendarService::encode($bookHref)];
    }

    public function update(string $href, array $data): array
    {
        $url = $this->dav->absolute($href);
        $current = $this->dav->get($url);
        /** @var VCard $card */
        $card = Reader::read($current['body'], Reader::OPTION_FORGIVING);
        $this->apply($card, $data);
        $vcf = $card->serialize();
        $etag = $this->dav->put($url, $vcf, 'text/vcard; charset=utf-8', $current['etag']);
        $bookHref = substr($href, 0, strrpos($href, '/') + 1);

        return $this->normalize($card) + ['id' => CalendarService::encode($href), 'href' => $href, 'etag' => $etag, 'book_id' => CalendarService::encode($bookHref)];
    }

    public function delete(string $href): void
    {
        $this->dav->delete($this->dav->absolute($href));
    }

    protected function apply(VCard $card, array $data): void
    {
        $first = trim((string) ($data['first_name'] ?? ''));
        $last = trim((string) ($data['last_name'] ?? ''));
        $fn = trim((string) ($data['name'] ?? '')) ?: trim("{$first} {$last}");

        $card->FN = $fn;
        $card->N = [$last, $first, '', '', ''];

        foreach (['org' => 'ORG', 'title' => 'TITLE', 'note' => 'NOTE'] as $key => $prop) {
            if (array_key_exists($key, $data)) {
                if (filled($data[$key])) {
                    $card->$prop = (string) $data[$key];
                } else {
                    unset($card->$prop);
                }
            }
        }

        if (array_key_exists('emails', $data)) {
            unset($card->EMAIL);
            foreach ($data['emails'] ?? [] as $email) {
                if (filled($email['value'] ?? null)) {
                    $card->add('EMAIL', $email['value'], ['TYPE' => strtoupper($email['type'] ?? 'work')]);
                }
            }
        }
        if (array_key_exists('phones', $data)) {
            unset($card->TEL);
            foreach ($data['phones'] ?? [] as $phone) {
                if (filled($phone['value'] ?? null)) {
                    $card->add('TEL', $phone['value'], ['TYPE' => strtoupper($phone['type'] ?? 'cell')]);
                }
            }
        }

        $card->REV = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Ymd\THis\Z');
    }
}
