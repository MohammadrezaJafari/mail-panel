<?php

namespace App\Dav;

use App\Mail\Client\MailCredentialsMissingException;
use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Minimal CalDAV / CardDAV client (PROPFIND, REPORT, GET, PUT, DELETE) built on
 * the Laravel HTTP client. Works against SOGo and any RFC 4791/6352 server.
 */
class DavClient
{
    public const NS_DAV = 'DAV:';

    public const NS_CAL = 'urn:ietf:params:xml:ns:caldav';

    public const NS_CARD = 'urn:ietf:params:xml:ns:carddav';

    public const NS_APPLE = 'http://apple.com/ns/ical/';

    public function __construct(
        protected string $baseUrl,
        protected string $username,
        protected string $password,
        protected bool $verifySsl = true,
        protected int $timeout = 20,
    ) {}

    public static function forUser(User $user): self
    {
        if (blank($user->mail_password)) {
            throw new MailCredentialsMissingException('Mailbox credentials are not available. Please sign in again.');
        }

        $cfg = config('mailprovider.dav');

        return new self($cfg['base_url'], $user->email, $user->mail_password, (bool) $cfg['verify_ssl'], (int) $cfg['timeout']);
    }

    // ------------------------------------------------------------------ URLs

    public function principalUrl(): string
    {
        return $this->baseUrl.rawurlencode($this->username).'/';
    }

    /** Absolute URL for a server href (which may be absolute, root-relative or relative). */
    public function absolute(string $href): string
    {
        if (preg_match('#^https?://#i', $href)) {
            return $href;
        }
        $base = parse_url($this->baseUrl);
        $origin = ($base['scheme'] ?? 'https').'://'.($base['host'] ?? '').(isset($base['port']) ? ':'.$base['port'] : '');

        return str_starts_with($href, '/') ? $origin.$href : $this->baseUrl.$href;
    }

    // -------------------------------------------------------------- requests

    protected function http(): PendingRequest
    {
        $request = Http::withBasicAuth($this->username, $this->password)
            ->timeout($this->timeout)
            ->withUserAgent('mail-panel-dav/1.0');

        return $this->verifySsl ? $request : $request->withoutVerifying();
    }

    protected function xml(string $method, string $url, string $body, array $headers = []): \DOMDocument
    {
        $response = $this->http()
            ->withHeaders($headers + ['Content-Type' => 'application/xml; charset=utf-8'])
            ->withBody($body, 'application/xml; charset=utf-8')
            ->send($method, $url);

        $this->guard($response, "{$method} {$url}");

        $doc = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $ok = $doc->loadXML($response->body(), LIBXML_NONET);
        libxml_use_internal_errors($previous);
        if (! $ok) {
            throw new RuntimeException("DAV {$method} {$url}: response is not valid XML");
        }

        return $doc;
    }

    protected function guard(Response $response, string $what): void
    {
        if ($response->status() === 401) {
            throw new MailCredentialsMissingException('The calendar server rejected the credentials.');
        }
        if ($response->failed()) {
            throw new RuntimeException("DAV {$what} failed with HTTP {$response->status()}");
        }
    }

    /**
     * PROPFIND with depth 1; returns [href => [prop local name => value|DOMElement]].
     *
     * @return array<string, array{href: string, props: array<string, mixed>}>
     */
    public function propfind(string $url, array $props, int $depth = 1): array
    {
        $propXml = implode('', array_map(fn ($p) => "<{$p}/>", $props));
        $body = '<?xml version="1.0" encoding="utf-8"?>'
            .'<d:propfind xmlns:d="DAV:" xmlns:c="urn:ietf:params:xml:ns:caldav" xmlns:card="urn:ietf:params:xml:ns:carddav" xmlns:cs="http://calendarserver.org/ns/" xmlns:ical="http://apple.com/ns/ical/">'
            ."<d:prop>{$propXml}</d:prop></d:propfind>";

        return $this->parseMultistatus($this->xml('PROPFIND', $url, $body, ['Depth' => (string) $depth]));
    }

    /** @return array<string, array{href: string, props: array<string, mixed>}> */
    public function report(string $url, string $body, int $depth = 1): array
    {
        return $this->parseMultistatus($this->xml('REPORT', $url, $body, ['Depth' => (string) $depth]));
    }

    public function get(string $url): array
    {
        $response = $this->http()->get($url);
        $this->guard($response, "GET {$url}");

        return ['body' => $response->body(), 'etag' => $response->header('ETag') ?: null];
    }

    /** @return string|null new ETag when the server reports one */
    public function put(string $url, string $body, string $contentType, ?string $etag = null, bool $create = false): ?string
    {
        $headers = ['Content-Type' => $contentType];
        if ($create) {
            $headers['If-None-Match'] = '*';
        } elseif ($etag) {
            $headers['If-Match'] = $etag;
        }

        $response = $this->http()->withHeaders($headers)->withBody($body, $contentType)->put($url);
        $this->guard($response, "PUT {$url}");

        return $response->header('ETag') ?: null;
    }

    public function delete(string $url, ?string $etag = null): void
    {
        $request = $this->http();
        if ($etag) {
            $request = $request->withHeaders(['If-Match' => $etag]);
        }
        $response = $request->delete($url);
        if ($response->status() !== 404) {
            $this->guard($response, "DELETE {$url}");
        }
    }

    public function mkcol(string $url, string $body): void
    {
        $response = $this->http()->withBody($body, 'application/xml; charset=utf-8')->send('MKCOL', $url);
        $this->guard($response, "MKCOL {$url}");
    }

    // --------------------------------------------------------------- parsing

    /** @return array<string, array{href: string, props: array<string, mixed>}> */
    protected function parseMultistatus(\DOMDocument $doc): array
    {
        $out = [];
        foreach ($doc->getElementsByTagNameNS(self::NS_DAV, 'response') as $response) {
            /** @var \DOMElement $response */
            $hrefNode = $response->getElementsByTagNameNS(self::NS_DAV, 'href')->item(0);
            if (! $hrefNode) {
                continue;
            }
            $href = trim($hrefNode->textContent);
            $props = [];
            foreach ($response->getElementsByTagNameNS(self::NS_DAV, 'propstat') as $propstat) {
                /** @var \DOMElement $propstat */
                $status = $propstat->getElementsByTagNameNS(self::NS_DAV, 'status')->item(0)?->textContent ?? '';
                if ($status && ! str_contains($status, ' 200 ')) {
                    continue;
                }
                $prop = $propstat->getElementsByTagNameNS(self::NS_DAV, 'prop')->item(0);
                if (! $prop) {
                    continue;
                }
                foreach ($prop->childNodes as $child) {
                    if ($child instanceof \DOMElement) {
                        $props[$child->localName] = $this->propValue($child);
                    }
                }
            }
            $out[$href] = ['href' => $href, 'props' => $props];
        }

        return $out;
    }

    protected function propValue(\DOMElement $el): mixed
    {
        return match ($el->localName) {
            'resourcetype' => array_map(fn (\DOMElement $c) => $c->localName, array_values(array_filter(iterator_to_array($el->childNodes), fn ($c) => $c instanceof \DOMElement))),
            'supported-calendar-component-set' => array_map(fn (\DOMElement $c) => $c->getAttribute('name'), array_values(array_filter(iterator_to_array($el->childNodes), fn ($c) => $c instanceof \DOMElement))),
            default => trim($el->textContent),
        };
    }
}
