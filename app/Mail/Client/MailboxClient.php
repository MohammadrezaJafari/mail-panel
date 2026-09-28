<?php

namespace App\Mail\Client;

use Carbon\Carbon;
use Illuminate\Support\Str;
use Webklex\PHPIMAP\Attachment;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\Folder;
use Webklex\PHPIMAP\Message;

/**
 * Thin, transport-agnostic facade over one user's IMAP account.
 * Everything the web app shows (folders, lists, messages) comes from here.
 */
class MailboxClient
{
    public const ROLES = [
        'inbox' => ['inbox'],
        'drafts' => ['drafts', 'draft'],
        'sent' => ['sent', 'sent items', 'sent messages', 'sent mail'],
        'junk' => ['junk', 'spam', 'junk e-mail', 'junk email'],
        'trash' => ['trash', 'deleted', 'deleted items', 'deleted messages', 'bin'],
        'archive' => ['archive', 'archives', 'all mail'],
    ];

    protected bool $connected = false;

    public function __construct(protected Client $client) {}

    protected function connect(): Client
    {
        if (! $this->connected) {
            $this->client->connect();
            $this->connected = true;
        }

        return $this->client;
    }

    public function disconnect(): void
    {
        if ($this->connected) {
            $this->client->disconnect();
            $this->connected = false;
        }
    }

    // ------------------------------------------------------------- Folders

    /** @return array<int, array{path: string, name: string, role: string|null, unread: int, total: int, delimiter: string}> */
    public function folders(): array
    {
        $folders = $this->connect()->getFolders(false);
        $out = [];

        foreach ($folders as $folder) {
            /** @var Folder $folder */
            if ($folder->no_select ?? false) {
                continue;
            }

            $status = [];
            try {
                $status = $folder->status();
            } catch (\Throwable) {
                // Some folders (e.g. containers) can't be STATUS'ed.
            }

            $out[] = [
                'path' => $folder->path,
                'name' => $this->displayName($folder),
                'role' => $this->role($folder),
                'unread' => (int) ($status['unseen'] ?? $status['UNSEEN'] ?? 0),
                'total' => (int) ($status['messages'] ?? $status['MESSAGES'] ?? 0),
                'uidnext' => (int) ($status['uidnext'] ?? $status['UIDNEXT'] ?? 0),
                'uidvalidity' => (int) ($status['uidvalidity'] ?? $status['UIDVALIDITY'] ?? 0),
                'delimiter' => $folder->delimiter,
            ];
        }

        usort($out, function ($a, $b) {
            $order = ['inbox' => 0, 'drafts' => 1, 'sent' => 2, 'archive' => 3, 'junk' => 4, 'trash' => 5];
            $ra = $order[$a['role']] ?? 10;
            $rb = $order[$b['role']] ?? 10;

            return $ra <=> $rb ?: strcasecmp($a['name'], $b['name']);
        });

        return $out;
    }

    public function folderByRole(string $role): ?string
    {
        foreach ($this->folders() as $folder) {
            if ($folder['role'] === $role) {
                return $folder['path'];
            }
        }

        return null;
    }

    public function createFolder(string $name): void
    {
        $this->connect()->createFolder($name, false);
    }

    protected function displayName(Folder $folder): string
    {
        $name = $folder->name;

        return match ($this->role($folder)) {
            'inbox' => 'Inbox',
            default => $name,
        };
    }

    protected function role(Folder $folder): ?string
    {
        $name = strtolower($folder->name);
        $full = strtolower($folder->full_name);

        if ($full === 'inbox') {
            return 'inbox';
        }

        foreach (self::ROLES as $role => $names) {
            if (in_array($name, $names, true)) {
                return $role;
            }
        }

        return null;
    }

    // ------------------------------------------------------------ Messages

    /**
     * @return array{data: array<int, array>, page: int, per_page: int, total: int}
     */
    public function messages(string $folderPath, int $page = 1, int $perPage = 25, ?string $search = null, ?string $filter = null, ?int $sinceUid = null): array
    {
        $folder = $this->connect()->getFolderByPath($folderPath);
        $query = $folder->query()->setFetchBody(false)->leaveUnread()->softFail();

        if ($sinceUid !== null) {
            // Only messages that arrived after the client's last known UIDNEXT.
            $query->whereUid($sinceUid.':*');
        } elseif (filled($search)) {
            $query->whereText($search);
        } else {
            $query->whereAll();
        }

        match ($filter) {
            'unread' => $query->whereUnseen(),
            'flagged' => $query->where('FLAGGED'),
            'attachments' => $query->whereText('Content-Disposition: attachment'),
            default => null,
        };

        $total = $query->count();
        $messages = $query->fetchOrderDesc()->limit($perPage, $page)->get();

        $data = [];
        foreach ($messages as $message) {
            // "N:*" always matches the last message even when its UID < N; drop it.
            if ($sinceUid !== null && (int) $message->uid < $sinceUid) {
                continue;
            }
            $data[] = $this->summarize($message, $folderPath);
        }

        return ['data' => $data, 'page' => $page, 'per_page' => $perPage, 'total' => $total];
    }

    public function message(string $folderPath, int $uid, bool $markSeen = true): array
    {
        $folder = $this->connect()->getFolderByPath($folderPath);
        $message = $folder->query()->setFetchBody(true)->getMessageByUid($uid);

        if ($markSeen && ! $this->hasFlag($message, 'seen')) {
            $message->setFlag('Seen');
        }

        return $this->detail($message, $folderPath);
    }

    public function rawMessage(string $folderPath, int $uid): string
    {
        $folder = $this->connect()->getFolderByPath($folderPath);
        $message = $folder->query()->setFetchBody(true)->getMessageByUid($uid);

        return $message->getHeader()->raw."\r\n\r\n".$message->getRawBody();
    }

    /** @return array{name: string, content_type: string, content: string}|null */
    public function attachment(string $folderPath, int $uid, string $attachmentId): ?array
    {
        $folder = $this->connect()->getFolderByPath($folderPath);
        $message = $folder->query()->setFetchBody(true)->leaveUnread()->getMessageByUid($uid);

        foreach ($message->getAttachments() as $index => $attachment) {
            /** @var Attachment $attachment */
            if ($this->attachmentId($attachment, (int) $index) === $attachmentId) {
                return [
                    'name' => $attachment->name ?: 'attachment',
                    'content_type' => $attachment->content_type ?: 'application/octet-stream',
                    'content' => $attachment->getContent(),
                ];
            }
        }

        return null;
    }

    public function setFlags(string $folderPath, array $uids, array $flags): void
    {
        $folder = $this->connect()->getFolderByPath($folderPath);

        foreach ($uids as $uid) {
            $message = $folder->query()->setFetchBody(false)->leaveUnread()->getMessageByUid((int) $uid);
            foreach ($flags as $flag => $state) {
                $imapFlag = ucfirst(strtolower($flag));
                $state ? $message->setFlag($imapFlag) : $message->unsetFlag($imapFlag);
            }
        }
    }

    public function move(string $folderPath, array $uids, string $targetPath): void
    {
        $folder = $this->connect()->getFolderByPath($folderPath);

        foreach ($uids as $uid) {
            $folder->query()->setFetchBody(false)->leaveUnread()->getMessageByUid((int) $uid)->move($targetPath, true);
        }
    }

    /**
     * Delete: moves to Trash unless already in Trash/Junk, then expunges.
     */
    public function delete(string $folderPath, array $uids): void
    {
        $trash = $this->folderByRole('trash');
        $current = $this->role($this->connect()->getFolderByPath($folderPath));

        if ($trash && ! in_array($current, ['trash', 'junk'], true) && $trash !== $folderPath) {
            $this->move($folderPath, $uids, $trash);

            return;
        }

        $folder = $this->connect()->getFolderByPath($folderPath);
        foreach ($uids as $uid) {
            $folder->query()->setFetchBody(false)->leaveUnread()->getMessageByUid((int) $uid)->delete(true, null, true);
        }
    }

    public function append(string $folderPath, string $rawMessage, array $flags = ['\\Seen']): void
    {
        $folder = $this->connect()->getFolderByPath($folderPath);
        $folder->appendMessage($rawMessage, $flags);
    }

    // ----------------------------------------------------------- Transform

    protected function summarize(Message $message, string $folderPath): array
    {
        $date = $this->date($message);
        $from = $this->addresses($message, 'from');

        return [
            'uid' => (int) $message->uid,
            'folder' => $folderPath,
            'subject' => (string) ($message->subject ?? ''),
            'from' => $from[0] ?? null,
            'to' => $this->addresses($message, 'to'),
            'date' => $date?->toIso8601String(),
            'preview' => '',
            'seen' => $this->hasFlag($message, 'seen'),
            'flagged' => $this->hasFlag($message, 'flagged'),
            'answered' => $this->hasFlag($message, 'answered'),
            'has_attachments' => $this->looksLikeAttachments($message),
            'size' => (int) ($message->size ?? 0),
            'message_id' => $this->messageId((string) ($message->message_id ?? '')),
            'in_reply_to' => $this->messageId((string) ($message->in_reply_to ?? '')),
            'references' => $this->messageIds((string) ($message->references ?? '')),
        ];
    }

    protected function messageId(string $raw): string
    {
        return trim($raw, " \t<>");
    }

    /** @return array<int, string> */
    protected function messageIds(string $raw): array
    {
        preg_match_all('/<([^>]+)>/', $raw, $m);

        return $m[1] ?: array_values(array_filter(preg_split('/\s+/', trim($raw)) ?: []));
    }

    protected function detail(Message $message, string $folderPath): array
    {
        $summary = $this->summarize($message, $folderPath);
        $html = $message->hasHTMLBody() ? $message->getHTMLBody() : null;
        $text = $message->hasTextBody() ? $message->getTextBody() : null;

        $attachments = [];
        foreach ($message->getAttachments() as $index => $attachment) {
            /** @var Attachment $attachment */
            $attachments[] = [
                'id' => $this->attachmentId($attachment, (int) $index),
                'name' => $attachment->name ?: 'attachment',
                'content_type' => $attachment->content_type,
                'size' => (int) ($attachment->size ?? strlen((string) $attachment->getContent())),
                'content_id' => $attachment->content_id ? trim((string) $attachment->content_id, '<>') : null,
                'inline' => strtolower((string) $attachment->disposition) === 'inline',
            ];

            // Replace cid: references with data URIs so inline images render.
            if ($html && $attachment->content_id) {
                $cid = trim((string) $attachment->content_id, '<>');
                $dataUri = 'data:'.$attachment->content_type.';base64,'.base64_encode($attachment->getContent());
                $html = str_replace(['cid:'.$cid, 'CID:'.$cid], $dataUri, $html);
            }
        }

        return $summary + [
            'cc' => $this->addresses($message, 'cc'),
            'bcc' => $this->addresses($message, 'bcc'),
            'reply_to' => $this->addresses($message, 'reply_to'),
            'html' => $html,
            'text' => $text,
            'preview' => Str::limit(trim(strip_tags($text ?? $html ?? '')), 160),
            'attachments' => $attachments,
            'has_attachments' => count(array_filter($attachments, fn ($a) => ! $a['inline'])) > 0,
        ];
    }

    protected function addresses(Message $message, string $header): array
    {
        $attribute = $message->get($header);
        if (! $attribute) {
            return [];
        }

        $out = [];
        foreach ($attribute->all() as $address) {
            if (is_object($address) && isset($address->mail)) {
                $out[] = [
                    'name' => (string) ($address->personal ?? ''),
                    'email' => (string) $address->mail,
                ];
            } elseif (is_string($address) && $address !== '') {
                $out[] = ['name' => '', 'email' => $address];
            }
        }

        return $out;
    }

    protected function date(Message $message): ?Carbon
    {
        try {
            return $message->date?->toDate();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function hasFlag(Message $message, string $flag): bool
    {
        $flags = $message->getFlags();
        $flag = strtolower($flag);

        foreach ($flags as $key => $value) {
            if (strtolower(ltrim((string) $key, '\\')) === $flag || strtolower(ltrim((string) $value, '\\')) === $flag) {
                return true;
            }
        }

        return false;
    }

    protected function looksLikeAttachments(Message $message): bool
    {
        $contentType = strtolower((string) ($message->get('content_type') ?? ''));

        return str_contains($contentType, 'multipart/mixed');
    }

    protected function attachmentId(Attachment $attachment, int $index): string
    {
        return substr(md5($index.'|'.$attachment->name.'|'.$attachment->content_type), 0, 12);
    }
}
