<?php

namespace App\Mail\Client;

use App\Models\User;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Sends mail through the platform's submission port using the user's own
 * credentials, so the message is authenticated exactly like a desktop client.
 */
class SmtpSender
{
    public function transportFor(User $user): TransportInterface
    {
        if (blank($user->mail_password)) {
            throw new MailCredentialsMissingException('Mailbox credentials are not available. Please sign in again.');
        }

        $cfg = config('mailprovider.smtp');
        $scheme = match (strtolower((string) $cfg['encryption'])) {
            'ssl', 'smtps' => 'smtps',
            default => 'smtp',
        };

        $dsn = sprintf(
            '%s://%s:%s@%s:%d',
            $scheme,
            rawurlencode($user->email),
            rawurlencode($user->mail_password),
            $cfg['host'],
            $cfg['port'],
        );

        return Transport::fromDsn($dsn);
    }

    /**
     * @param  array{
     *   from?: array{name?: string, email: string},
     *   to: array<int, string>, cc?: array<int, string>, bcc?: array<int, string>,
     *   subject: string, html?: string|null, text?: string|null,
     *   in_reply_to?: string|null, references?: string|null,
     *   attachments?: array<int, array{name: string, content: string, content_type?: string}>
     * }  $data
     */
    public function build(User $user, array $data): Email
    {
        $from = $data['from'] ?? ['email' => $user->email, 'name' => $user->name];

        $email = (new Email)
            ->from(new Address($from['email'], $from['name'] ?? ''))
            ->subject($data['subject'] ?? '')
            ->to(...array_map(fn ($a) => Address::create($a), $data['to']));

        if (! empty($data['cc'])) {
            $email->cc(...array_map(fn ($a) => Address::create($a), $data['cc']));
        }
        if (! empty($data['bcc'])) {
            $email->bcc(...array_map(fn ($a) => Address::create($a), $data['bcc']));
        }

        if (filled($data['html'] ?? null)) {
            $email->html($data['html']);
            $email->text($data['text'] ?? trim(strip_tags(preg_replace('/<br\s*\/?>/i', "\n", $data['html']))));
        } else {
            $email->text($data['text'] ?? '');
        }

        if (filled($data['in_reply_to'] ?? null)) {
            $parent = trim($data['in_reply_to'], ' <>');
            $email->getHeaders()->addIdHeader('In-Reply-To', $parent);

            preg_match_all('/[^\s<>,]+@[^\s<>,]+/', (string) ($data['references'] ?? ''), $m);
            $refs = array_values(array_unique([...$m[0], $parent]));
            $email->getHeaders()->addIdHeader('References', $refs);
        }

        foreach ($data['attachments'] ?? [] as $attachment) {
            $email->attach($attachment['content'], $attachment['name'], $attachment['content_type'] ?? null);
        }

        return $email;
    }

    public function send(User $user, Email $email): void
    {
        $this->transportFor($user)->send($email);
    }
}
