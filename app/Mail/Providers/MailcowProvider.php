<?php

namespace App\Mail\Providers;

use App\Mail\Contracts\MailProvider;
use App\Mail\Data\MailboxUsage;
use App\Mail\Exceptions\ProviderException;
use App\Mail\Sieve\SieveScriptBuilder;
use App\Models\Alias;
use App\Models\Domain;
use App\Models\Mailbox;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Adapter for the mailcow-dockerized REST API (/api/v1).
 */
class MailcowProvider implements MailProvider
{
    public const NAME = 'mailcow';

    public function __construct(
        protected array $config,
        protected SieveScriptBuilder $sieve,
    ) {}

    public function name(): string
    {
        return self::NAME;
    }

    // ---------------------------------------------------------------- HTTP

    protected function http(): PendingRequest
    {
        $request = Http::baseUrl($this->config['base_url'].'/api/v1')
            ->withHeaders(['X-API-Key' => (string) $this->config['api_key']])
            ->acceptJson()
            ->timeout($this->config['timeout'] ?? 15);

        if (($this->config['verify_ssl'] ?? true) === false) {
            $request = $request->withoutVerifying();
        }

        return $request;
    }

    protected function get(string $path): array
    {
        $response = $this->http()->get($path);
        $this->guard($response, $path);

        return (array) $response->json();
    }

    protected function post(string $path, array $payload): array
    {
        $response = $this->http()->post($path, $payload);
        $this->guard($response, $path);

        return (array) $response->json();
    }

    protected function guard(Response $response, string $path): void
    {
        if ($response->failed()) {
            throw new ProviderException("mailcow {$path} failed with HTTP {$response->status()}: ".$response->body());
        }

        $body = $response->json();

        // mailcow returns a list of {type, msg} objects for write operations.
        if (is_array($body) && array_is_list($body)) {
            foreach ($body as $item) {
                if (is_array($item) && in_array($item['type'] ?? null, ['danger', 'error'], true)) {
                    $msg = is_array($item['msg'] ?? null) ? implode(' ', $item['msg']) : (string) ($item['msg'] ?? 'unknown error');
                    throw new ProviderException("mailcow {$path}: {$msg}");
                }
            }
        }
    }

    // ------------------------------------------------------------- Domains

    public function createDomain(Domain $domain): void
    {
        $this->post('add/domain', [
            'domain' => $domain->name,
            'description' => $domain->description ?? $domain->organization?->name,
            'aliases' => $domain->max_aliases,
            'mailboxes' => $domain->max_mailboxes,
            'defquota' => $domain->default_quota_mb,
            'maxquota' => $domain->max_quota_mb,
            'quota' => $domain->domain_quota_mb,
            'active' => $domain->isActive() ? 1 : 0,
            'restart_sogo' => 1,
        ]);

        $domain->rememberExternalId($domain->name, [], self::NAME);

        // Generate a DKIM key so DNS records can be shown immediately.
        try {
            $this->post('add/dkim', [
                'domains' => $domain->name,
                'dkim_selector' => $this->config['dkim_selector'] ?? 'dkim',
                'key_size' => $this->config['dkim_key_size'] ?? 2048,
            ]);
        } catch (ProviderException) {
            // DKIM key may already exist; not fatal.
        }
    }

    public function updateDomain(Domain $domain): void
    {
        $this->post('edit/domain', [
            'items' => [$domain->externalId(self::NAME) ?? $domain->name],
            'attr' => [
                'description' => $domain->description ?? '',
                'aliases' => $domain->max_aliases,
                'mailboxes' => $domain->max_mailboxes,
                'defquota' => $domain->default_quota_mb,
                'maxquota' => $domain->max_quota_mb,
                'quota' => $domain->domain_quota_mb,
                'active' => $domain->isActive() ? 1 : 0,
            ],
        ]);
    }

    public function deleteDomain(Domain $domain): void
    {
        $this->post('delete/domain', [$domain->externalId(self::NAME) ?? $domain->name]);
    }

    public function getDkim(Domain $domain): ?array
    {
        $data = $this->get('get/dkim/'.$domain->name);

        if (empty($data['dkim_txt'])) {
            return null;
        }

        return [
            'selector' => (string) ($data['dkim_selector'] ?? $this->config['dkim_selector'] ?? 'dkim'),
            'txt' => (string) $data['dkim_txt'],
        ];
    }

    // ----------------------------------------------------------- Mailboxes

    public function createMailbox(Mailbox $mailbox, string $password): void
    {
        $this->post('add/mailbox', [
            'local_part' => $mailbox->local_part,
            'domain' => $mailbox->domain->name,
            'name' => $mailbox->name,
            'quota' => $mailbox->quota_mb,
            'password' => $password,
            'password2' => $password,
            'active' => $mailbox->isActive() ? 1 : 0,
            'force_pw_update' => 0,
            'tls_enforce_in' => 0,
            'tls_enforce_out' => 0,
        ]);

        $mailbox->rememberExternalId($mailbox->address, [], self::NAME);
    }

    public function updateMailbox(Mailbox $mailbox): void
    {
        $this->editMailbox($mailbox, [
            'name' => $mailbox->name,
            'quota' => $mailbox->quota_mb,
            'active' => $mailbox->isActive() ? 1 : 0,
        ]);
    }

    public function deleteMailbox(Mailbox $mailbox): void
    {
        $this->post('delete/mailbox', [$mailbox->externalId(self::NAME) ?? $mailbox->address]);
    }

    public function setMailboxPassword(Mailbox $mailbox, string $password): void
    {
        $this->editMailbox($mailbox, ['password' => $password, 'password2' => $password]);
    }

    public function setQuota(Mailbox $mailbox, int $quotaMb): void
    {
        $this->editMailbox($mailbox, ['quota' => $quotaMb]);
    }

    public function suspendMailbox(Mailbox $mailbox): void
    {
        $this->editMailbox($mailbox, ['active' => 0]);
    }

    public function activateMailbox(Mailbox $mailbox): void
    {
        $this->editMailbox($mailbox, ['active' => 1]);
    }

    public function getMailboxUsage(Mailbox $mailbox): ?MailboxUsage
    {
        $data = $this->get('get/mailbox/'.$mailbox->address);

        if (! isset($data['quota_used'])) {
            return null;
        }

        return new MailboxUsage(
            usedBytes: (int) $data['quota_used'],
            quotaBytes: (int) ($data['quota'] ?? $mailbox->quotaBytes()),
            messages: (int) ($data['messages'] ?? 0),
        );
    }

    public function syncMailboxRules(Mailbox $mailbox): void
    {
        $desc = 'managed-by-mail-panel';
        $script = $this->sieve->build($mailbox);

        $existing = collect($this->get('get/filters/'.$mailbox->address))
            ->first(fn ($f) => is_array($f) && ($f['script_desc'] ?? null) === $desc);

        if ($script === null) {
            if ($existing) {
                $this->post('delete/filter', [(string) $existing['id']]);
            }

            return;
        }

        if ($existing) {
            $this->post('edit/filter', [
                'items' => [(string) $existing['id']],
                'attr' => [
                    'active' => 1,
                    'filter_type' => 'prefilter',
                    'script_data' => $script,
                    'script_desc' => $desc,
                ],
            ]);

            return;
        }

        $this->post('add/filter', [
            'username' => $mailbox->address,
            'active' => 1,
            'filter_type' => 'prefilter',
            'script_data' => $script,
            'script_desc' => $desc,
        ]);
    }

    protected function editMailbox(Mailbox $mailbox, array $attr): void
    {
        $this->post('edit/mailbox', [
            'items' => [$mailbox->externalId(self::NAME) ?? $mailbox->address],
            'attr' => $attr,
        ]);
    }

    // ------------------------------------------------------------- Aliases

    public function createAlias(Alias $alias): void
    {
        $this->post('add/alias', [
            'address' => $alias->address,
            'goto' => implode(',', $alias->goto),
            'active' => $alias->is_active ? 1 : 0,
            'sogo_visible' => 1,
        ]);

        // mailcow identifies aliases by numeric id; resolve it after creation.
        $remote = collect($this->get('get/alias/all'))
            ->first(fn ($a) => is_array($a) && strcasecmp($a['address'] ?? '', $alias->address) === 0);

        $alias->rememberExternalId((string) ($remote['id'] ?? $alias->address), [], self::NAME);
    }

    public function updateAlias(Alias $alias): void
    {
        $this->post('edit/alias', [
            'items' => [$alias->externalId(self::NAME) ?? $alias->address],
            'attr' => [
                'address' => $alias->address,
                'goto' => implode(',', $alias->goto),
                'active' => $alias->is_active ? 1 : 0,
            ],
        ]);
    }

    public function deleteAlias(Alias $alias): void
    {
        $this->post('delete/alias', [$alias->externalId(self::NAME) ?? $alias->address]);
    }

    // -------------------------------------------------------------- Health

    public function health(): array
    {
        try {
            $containers = $this->get('get/status/containers');
        } catch (\Throwable $e) {
            return ['api' => ['status' => 'down', 'message' => $e->getMessage()]];
        }

        $map = [
            'smtp' => 'postfix-mailcow',
            'imap' => 'dovecot-mailcow',
            'antispam' => 'rspamd-mailcow',
            'webmail' => 'sogo-mailcow',
            'database' => 'mysql-mailcow',
        ];

        $result = ['api' => ['status' => 'ok']];
        foreach ($map as $key => $container) {
            $state = $containers[$container]['state'] ?? null;
            $result[$key] = [
                'status' => $state === 'running' ? 'ok' : ($state ? 'down' : 'unknown'),
                'message' => $state ? "{$container}: {$state}" : "{$container} not reported",
            ];
        }

        return $result;
    }
}
