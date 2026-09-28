<?php

namespace App\Services;

use App\Mail\Client\ImapClientFactory;
use App\Mail\Client\MailboxClient;
use App\Mail\Client\SmtpSender;
use App\Models\ScheduledMessage;
use App\Models\Snooze;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Deferred mail operations: scheduled sends and snoozed messages.
 * Both rely on the user's cached mailbox credentials (present while signed in).
 */
class ScheduledMailService
{
    public const SNOOZE_FOLDER = 'Snoozed';

    public function __construct(
        protected SmtpSender $smtp,
        protected ImapClientFactory $imap,
        protected ContactService $contacts,
        protected AuditLogger $audit,
    ) {}

    // ------------------------------------------------------------ scheduling

    /**
     * @param  array<int, array{name: string, content: string, content_type: string}>  $attachments
     */
    public function schedule(User $user, array $payload, array $attachments, \DateTimeInterface $sendAt): ScheduledMessage
    {
        $stored = [];
        foreach ($attachments as $a) {
            $path = 'scheduled/'.$user->id.'/'.Str::uuid().'.bin';
            Storage::disk('local')->put($path, $a['content']);
            $stored[] = ['name' => $a['name'], 'content_type' => $a['content_type'], 'path' => $path];
        }

        $mailbox = $user->primaryMailbox();

        return ScheduledMessage::create([
            'user_id' => $user->id,
            'mailbox_id' => $mailbox?->id,
            'payload' => $payload,
            'attachments' => $stored,
            'send_at' => $sendAt,
        ]);
    }

    public function cancel(ScheduledMessage $message): void
    {
        $this->deleteAttachments($message);
        $message->delete();
    }

    public function sendDue(): int
    {
        $count = 0;

        ScheduledMessage::query()
            ->where('status', ScheduledMessage::STATUS_PENDING)
            ->where('send_at', '<=', now())
            ->orderBy('send_at')
            ->with('user')
            ->each(function (ScheduledMessage $message) use (&$count) {
                $this->sendNow($message) && $count++;
            });

        return $count;
    }

    public function sendNow(ScheduledMessage $message): bool
    {
        $user = $message->user;

        try {
            if (! $user || blank($user->mail_password)) {
                throw new \RuntimeException('Mailbox credentials are not available; sign in to the web app to send this message.');
            }

            $attachments = collect($message->attachments ?? [])->map(fn ($a) => [
                'name' => $a['name'],
                'content_type' => $a['content_type'],
                'content' => Storage::disk('local')->get($a['path']) ?? '',
            ])->all();

            $email = $this->smtp->build($user, $message->payload + ['attachments' => $attachments]);
            $this->smtp->send($user, $email);
            $this->contacts->remember($user, array_map(fn ($e) => ['email' => $e], [...$message->payload['to'], ...($message->payload['cc'] ?? []), ...($message->payload['bcc'] ?? [])]));

            try {
                $client = new MailboxClient($this->imap->forUser($user));
                try {
                    if ($sent = $client->folderByRole('sent')) {
                        $client->append($sent, $email->toString(), ['\\Seen']);
                    }
                } finally {
                    $client->disconnect();
                }
            } catch (\Throwable) {
                // Sent copy is best effort.
            }

            $message->forceFill(['status' => ScheduledMessage::STATUS_SENT, 'sent_at' => now(), 'error' => null])->save();
            $this->deleteAttachments($message);
            $this->audit->log('mail.scheduled_sent', $message->mailbox, ['subject' => mb_substr((string) ($message->payload['subject'] ?? ''), 0, 120)], $user);

            return true;
        } catch (\Throwable $e) {
            $message->forceFill(['status' => ScheduledMessage::STATUS_FAILED, 'error' => mb_substr($e->getMessage(), 0, 1000)])->save();

            return false;
        }
    }

    protected function deleteAttachments(ScheduledMessage $message): void
    {
        foreach ($message->attachments ?? [] as $a) {
            Storage::disk('local')->delete($a['path']);
        }
    }

    // --------------------------------------------------------------- snooze

    /**
     * Move messages to the Snoozed folder and remember when to bring them back.
     *
     * @param  array<int, array{uid: int, message_id?: string|null, subject?: string|null}>  $messages
     */
    public function snooze(User $user, MailboxClient $client, string $folder, array $messages, \DateTimeInterface $wakeAt): int
    {
        $snoozeFolder = $client->folderByRole('snoozed') ?? $this->ensureSnoozeFolder($client);
        $uids = array_map(fn ($m) => (int) $m['uid'], $messages);
        $client->move($folder, $uids, $snoozeFolder);

        foreach ($messages as $m) {
            Snooze::create([
                'user_id' => $user->id,
                'origin_folder' => $folder,
                'snooze_folder' => $snoozeFolder,
                'uid' => $m['uid'],
                'message_id' => $m['message_id'] ?? null,
                'subject' => isset($m['subject']) ? mb_substr($m['subject'], 0, 255) : null,
                'wake_at' => $wakeAt,
            ]);
        }

        return count($messages);
    }

    public function wakeDue(): int
    {
        $count = 0;

        Snooze::query()
            ->where('status', Snooze::STATUS_PENDING)
            ->where('wake_at', '<=', now())
            ->with('user')
            ->get()
            ->groupBy('user_id')
            ->each(function ($snoozes) use (&$count) {
                $user = $snoozes->first()->user;
                if (! $user || blank($user->mail_password)) {
                    return; // retry on a later run once the user signs in again
                }

                $client = new MailboxClient($this->imap->forUser($user));
                try {
                    foreach ($snoozes as $snooze) {
                        try {
                            $uid = $this->locate($client, $snooze);
                            if ($uid !== null) {
                                $client->setFlags($snooze->snooze_folder, [$uid], ['seen' => false]);
                                $client->move($snooze->snooze_folder, [$uid], $snooze->origin_folder);
                            }
                            $snooze->forceFill(['status' => Snooze::STATUS_DONE])->save();
                            $count++;
                        } catch (\Throwable $e) {
                            $snooze->forceFill(['status' => Snooze::STATUS_FAILED, 'error' => mb_substr($e->getMessage(), 0, 1000)])->save();
                        }
                    }
                } finally {
                    $client->disconnect();
                }
            });

        return $count;
    }

    /** UIDs change on move; find the message again by Message-ID inside the snooze folder. */
    protected function locate(MailboxClient $client, Snooze $snooze): ?int
    {
        if (! $snooze->message_id) {
            return null;
        }

        $page = $client->messages($snooze->snooze_folder, 1, 100, null, null, null, ['subject' => $snooze->subject]);
        foreach ($page['data'] as $summary) {
            if ($summary['message_id'] === $snooze->message_id) {
                return $summary['uid'];
            }
        }

        return null;
    }

    protected function ensureSnoozeFolder(MailboxClient $client): string
    {
        $client->createFolder(self::SNOOZE_FOLDER);

        return $client->folderByRole('snoozed') ?? self::SNOOZE_FOLDER;
    }
}
