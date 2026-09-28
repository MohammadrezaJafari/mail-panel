<?php

namespace App\Services;

/**
 * Verifies MX / SPF / DKIM / DMARC records for a domain against what the
 * mail platform expects. Purely DNS based, so it works for any provider.
 */
class DnsChecker
{
    /**
     * @param  array{selector: string, txt: string}|null  $dkim
     * @return array<string, array{status: string, expected: string, found: array<int, string>}>
     */
    public function check(string $domain, ?array $dkim = null): array
    {
        $mailHost = rtrim((string) config('mailprovider.mail_hostname'), '.');

        $mx = $this->records($domain, DNS_MX, 'target');
        $txt = $this->records($domain, DNS_TXT, 'txt');
        $spf = array_values(array_filter($txt, fn ($t) => str_starts_with(strtolower($t), 'v=spf1')));
        $dmarc = $this->records('_dmarc.'.$domain, DNS_TXT, 'txt');

        $result = [
            'mx' => [
                'status' => $this->has($mx, $mailHost) ? 'ok' : ($mx ? 'warning' : 'missing'),
                'expected' => "MX 10 {$mailHost}.",
                'found' => $mx,
            ],
            'spf' => [
                'status' => $spf ? ($this->has($spf, $mailHost) || $this->has($spf, 'mx') ? 'ok' : 'warning') : 'missing',
                'expected' => "v=spf1 mx a:{$mailHost} -all",
                'found' => $spf,
            ],
            'dmarc' => [
                'status' => $this->has($dmarc, 'v=dmarc1') ? 'ok' : 'missing',
                'expected' => 'v=DMARC1; p=quarantine; rua=mailto:'.(config('mailprovider.dmarc_rua') ?: "postmaster@{$domain}"),
                'found' => $dmarc,
            ],
        ];

        if ($dkim) {
            $host = "{$dkim['selector']}._domainkey.{$domain}";
            $found = $this->records($host, DNS_TXT, 'txt');
            $expectedKey = $this->dkimPublicKey($dkim['txt']);
            $ok = $expectedKey && collect($found)->contains(fn ($t) => str_contains(str_replace(' ', '', $t), $expectedKey));

            $result['dkim'] = [
                'status' => $ok ? 'ok' : ($found ? 'warning' : 'missing'),
                'expected' => "{$host} TXT {$dkim['txt']}",
                'found' => $found,
            ];
        } else {
            $result['dkim'] = ['status' => 'unknown', 'expected' => 'DKIM key not generated yet', 'found' => []];
        }

        return $result;
    }

    protected function records(string $host, int $type, string $field): array
    {
        $records = @dns_get_record($host, $type) ?: [];

        return collect($records)->pluck($field)->filter()->map(fn ($v) => rtrim((string) $v, '.'))->values()->all();
    }

    protected function has(array $values, string $needle): bool
    {
        $needle = strtolower($needle);

        return collect($values)->contains(fn ($v) => str_contains(strtolower($v), $needle));
    }

    protected function dkimPublicKey(string $txt): ?string
    {
        return preg_match('/p=([A-Za-z0-9+\/=]+)/', str_replace(' ', '', $txt), $m) ? $m[1] : null;
    }
}
