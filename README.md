# Mail Panel — Product Core, API & Backoffice

Laravel application that is the **product core** of the Mail platform:

- **Filament backoffice** (`/admin`) for super admins, organization owners and mail admins.
- **REST API** (`/api/v1`) consumed by the Quasar user portal / webmail (`mail-web-app`).
- **Mail provider abstraction**: business logic never talks to mailcow directly.

```
Quasar / Filament  →  Laravel (services, policies, audit)  →  MailProvider  →  mailcow
                                                                    └── (future) Stalwart
```

## Stack

| Layer         | Tech                                              |
| ------------- | ------------------------------------------------- |
| Framework     | Laravel 13, PHP ≥ 8.3                             |
| Backoffice    | Filament 4                                        |
| API auth      | Laravel Sanctum (bearer tokens)                   |
| Mail backend  | mailcow REST API (`MailcowProvider`)              |
| User mail I/O | IMAP via `webklex/laravel-imap`, SMTP via Symfony |

## Domain model

```
Organization ─┬─ Users (role: super_admin | organization_owner | mail_admin | user)
              ├─ Domains ─┬─ Mailboxes ─┬─ MailboxMembers (portal access, shared mailboxes)
              │           │             └─ Aliases
              │           └─ Aliases
              └─ AuditLogs
ProviderResource  (local record ⇄ provider external id, e.g. mailcow alias id)
```

Tables: `organizations`, `users`, `domains`, `mailboxes`, `aliases`, `mailbox_members`, `audit_logs`, `provider_resources`.

## Mail provider layer

`App\Mail\Contracts\MailProvider` is the single contract for domains, mailboxes, aliases,
quota, suspend/activate, password, usage, forwarding/auto-reply rules (rendered as Sieve
scripts by `SieveScriptBuilder`) and health.

Drivers (`config/mailprovider.php`):

- `fake` — in-memory `NullProvider` for local development and tests.
- `mailcow` — `MailcowProvider` (mailcow-dockerized `/api/v1`).

Services (`App\Services`) wrap every write: local DB → provider → audit log, inside a transaction.
Filament pages and API controllers only call services.

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed          # creates the super admin (ADMIN_EMAIL / ADMIN_PASSWORD)
php artisan serve                   # http://localhost:8000/admin
```

With `APP_ENV=local` the seeder also creates a demo organization (`acme.test`) with mailboxes
`ali@acme.test`, `sara@acme.test` (password `password`), a shared `support@acme.test`, and aliases.

### Environment

| Variable                        | Purpose                                                         |
| ------------------------------- | --------------------------------------------------------------- |
| `MAIL_PROVIDER`                 | `fake` or `mailcow`                                             |
| `MAILCOW_URL`, `MAILCOW_API_KEY` | mailcow instance + read/write API key                          |
| `MAIL_HOSTNAME`                 | Public mail host used for DNS verification hints (MX/SPF)       |
| `USER_IMAP_*`, `USER_SMTP_*`    | Where the API opens IMAP/SMTP sessions on behalf of users       |
| `WEBMAIL_URL`                   | SOGo URL shown as "Open webmail"                                |
| `DAV_BASE_URL`                  | CalDAV/CardDAV root (SOGo: `https://host/SOGo/dav/`)             |
| `AUTH_VIA_IMAP`                 | Allow first login of a mailbox without a local user (IMAP auth) |
| `FRONTEND_URLS`                 | Comma separated CORS origins of the Quasar app                  |
| `ADMIN_EMAIL`, `ADMIN_PASSWORD` | Seeded super admin                                              |

## Backoffice features

- Dashboard: domains / mailboxes / aliases / storage stats, provider health (SMTP, IMAP, anti-spam, webmail).
- Organizations with limits.
- Domains: create, limits & quotas, enable/disable, **DNS verification** (MX, SPF, DKIM, DMARC), mailboxes & aliases tabs.
- Mailboxes: create (with portal login), suspend/activate, reset password, change quota, refresh usage,
  forwarding, auto reply, signature, aliases, portal access (shared mailbox members).
- Aliases (incl. multi-target distribution lists), Users (roles), Audit log (read-only).
- Non-super-admins are scoped to their own organization (policies + query scopes).

## API (`/api/v1`)

| Method          | Path                                          | Description                                |
| --------------- | --------------------------------------------- | ------------------------------------------ |
| POST            | `auth/login`                                  | e-mail + mailbox password → bearer token   |
| POST            | `auth/logout`                                 |                                            |
| GET             | `me`                                          | user, primary mailbox, shared mailboxes    |
| GET             | `me/mailbox`                                  | mailbox + usage (synced from provider)     |
| PUT             | `me/mailbox/settings`                         | forwarding, auto reply, signature          |
| PUT             | `me/password`                                 | change mailbox password                    |
| GET/POST/DELETE | `me/aliases`                                  | manage own aliases                         |
| GET             | `me/webmail`                                  | SOGo URL                                   |
| GET / POST      | `mail/folders`                                | IMAP folders with unread counts / create   |
| GET             | `mail/messages?folder=&page=&search=&filter=` | paginated list                             |
| GET             | `mail/messages/{uid}?folder=`                 | full message (sanitised HTML, attachments) |
| GET             | `mail/messages/{uid}/attachments/{id}`        | download                                   |
| POST            | `mail/messages/flags` · `move` · `delete`     | bulk operations                            |
| POST            | `mail/send` · `mail/drafts`                   | send via SMTP (+copy to Sent) / save draft |

Users authenticate with their mailbox password; it is stored encrypted on the user record
while a token is active so the API can open IMAP/SMTP sessions, and cleared on logout.

## Scheduler

Scheduled sends and snoozed messages are processed by `mail:process-deferred`,
which the Laravel scheduler runs every minute. Add the usual cron entry:

```
* * * * * cd /path/to/mail-panel && php artisan schedule:run >> /dev/null 2>&1
```

Both features use the user's cached mailbox credentials, so they only run while
the user has an active web-app session; otherwise the item is marked failed and
shown to the user.

## Tests

```bash
php artisan test        # or: vendor/bin/phpunit
vendor/bin/pint         # code style
```

Feature tests cover the service layer (through the fake provider), the API and the Filament panel.
