<?php

use App\Mail\Providers\MailcowProvider;
use App\Mail\Providers\NullProvider;

return [
    /*
    |--------------------------------------------------------------------------
    | Default Mail Provider
    |--------------------------------------------------------------------------
    | "mailcow" talks to a real mailcow instance through its REST API.
    | "fake" is an in-memory adapter for local development and tests.
    | A future "stalwart" provider only needs a new class in the drivers list.
    */
    'default' => env('MAIL_PROVIDER', 'fake'),

    'drivers' => [
        'fake' => NullProvider::class,
        'mailcow' => MailcowProvider::class,
    ],

    'mailcow' => [
        'base_url' => rtrim(env('MAILCOW_URL', 'https://mail.example.com'), '/'),
        'api_key' => env('MAILCOW_API_KEY'),
        'verify_ssl' => (bool) env('MAILCOW_VERIFY_SSL', true),
        'timeout' => (int) env('MAILCOW_TIMEOUT', 15),
        'dkim_selector' => env('MAILCOW_DKIM_SELECTOR', 'dkim'),
        'dkim_key_size' => (int) env('MAILCOW_DKIM_KEY_SIZE', 2048),
    ],

    /*
    |--------------------------------------------------------------------------
    | End-user mail access (IMAP / SMTP / Webmail)
    |--------------------------------------------------------------------------
    | Used by the API that powers the Quasar web app. Users authenticate with
    | their own mailbox credentials; nothing here is provider specific.
    */
    'imap' => [
        'host' => env('USER_IMAP_HOST', 'mail.example.com'),
        'port' => (int) env('USER_IMAP_PORT', 993),
        'encryption' => env('USER_IMAP_ENCRYPTION', 'ssl'),
        'validate_cert' => (bool) env('USER_IMAP_VALIDATE_CERT', true),
    ],

    'smtp' => [
        'host' => env('USER_SMTP_HOST', 'mail.example.com'),
        'port' => (int) env('USER_SMTP_PORT', 587),
        'encryption' => env('USER_SMTP_ENCRYPTION', 'tls'),
    ],

    'webmail_url' => env('WEBMAIL_URL', 'https://mail.example.com/SOGo/'),

    /*
    | CalDAV / CardDAV endpoint used for calendar and contacts. SOGo exposes
    | both under /SOGo/dav/{user}/ and accepts the mailbox credentials.
    */
    'dav' => [
        'base_url' => rtrim(env('DAV_BASE_URL', 'https://mail.example.com/SOGo/dav/'), '/').'/',
        'verify_ssl' => (bool) env('DAV_VERIFY_SSL', true),
        'timeout' => (int) env('DAV_TIMEOUT', 20),
    ],

    /*
    | When true, users that do not exist locally yet may sign in to the
    | web app with their mailbox credentials (verified against IMAP) and a
    | local user record is provisioned for them on first login.
    */
    'auth_via_imap' => (bool) env('AUTH_VIA_IMAP', false),

    /*
    | Public hostname of the mail server, used for DNS verification hints.
    */
    'mail_hostname' => env('MAIL_HOSTNAME', 'mail.example.com'),
    'dmarc_rua' => env('DMARC_RUA'),
];
