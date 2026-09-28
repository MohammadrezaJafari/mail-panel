<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesMailbox;
use App\Http\Controllers\Controller;
use App\Mail\Client\SmtpSender;
use App\Services\AuditLogger;
use App\Services\ContactService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class MailSendController extends Controller
{
    use ResolvesMailbox;

    public function __construct(protected SmtpSender $smtp, protected AuditLogger $audit, protected ContactService $contacts) {}

    protected function rules(): array
    {
        return [
            'to' => ['required', 'array', 'min:1', 'max:50'],
            'to.*' => ['email'],
            'cc' => ['nullable', 'array', 'max:50'],
            'cc.*' => ['email'],
            'bcc' => ['nullable', 'array', 'max:50'],
            'bcc.*' => ['email'],
            'subject' => ['nullable', 'string', 'max:998'],
            'html' => ['nullable', 'string', 'max:2000000'],
            'text' => ['nullable', 'string', 'max:2000000'],
            'from_alias' => ['nullable', 'email'],
            'in_reply_to' => ['nullable', 'string', 'max:998'],
            'references' => ['nullable', 'string', 'max:5000'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'max:25600'],
            // when replying, flag the original as answered
            'reply_folder' => ['nullable', 'string'],
            'reply_uid' => ['nullable', 'integer'],
        ];
    }

    public function send(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules());
        $user = $request->user();
        $mailbox = $this->currentMailbox($request);

        $from = ['email' => $mailbox->address, 'name' => $user->name];
        if (! empty($data['from_alias']) && $mailbox->aliases()->where('address', strtolower($data['from_alias']))->exists()) {
            $from['email'] = strtolower($data['from_alias']);
        }

        $email = $this->smtp->build($user, [
            'from' => $from,
            'to' => $data['to'],
            'cc' => $data['cc'] ?? [],
            'bcc' => $data['bcc'] ?? [],
            'subject' => $data['subject'] ?? '',
            'html' => $data['html'] ?? null,
            'text' => $data['text'] ?? null,
            'in_reply_to' => $data['in_reply_to'] ?? null,
            'references' => $data['references'] ?? null,
            'attachments' => $this->uploads($request),
        ]);

        $this->smtp->send($user, $email);

        $this->contacts->remember($user, array_map(fn ($e) => ['email' => $e], [...$data['to'], ...($data['cc'] ?? []), ...($data['bcc'] ?? [])]));

        $client = $this->client($request);
        try {
            if ($sent = $client->folderByRole('sent')) {
                $client->append($sent, $email->toString(), ['\\Seen']);
            }
            if (! empty($data['reply_uid']) && ! empty($data['reply_folder'])) {
                $client->setFlags($data['reply_folder'], [(int) $data['reply_uid']], ['answered' => true]);
            }
        } catch (\Throwable) {
            // Message was sent; a Sent-folder copy failure must not surface as a send failure.
        } finally {
            $client->disconnect();
        }

        $this->audit->log('mail.sent', $mailbox, ['to' => count($data['to']), 'subject' => mb_substr((string) ($data['subject'] ?? ''), 0, 120)]);

        return response()->json(['message' => 'Sent.', 'message_id' => $email->getHeaders()->get('Message-ID')?->getBodyAsString()]);
    }

    public function saveDraft(Request $request): JsonResponse
    {
        $rules = $this->rules();
        $rules['to'] = ['nullable', 'array', 'max:50'];
        $data = $request->validate($rules);
        $user = $request->user();
        $mailbox = $this->currentMailbox($request);

        $email = $this->smtp->build($user, [
            'from' => ['email' => $mailbox->address, 'name' => $user->name],
            'to' => $data['to'] ?? [],
            'cc' => $data['cc'] ?? [],
            'bcc' => $data['bcc'] ?? [],
            'subject' => $data['subject'] ?? '',
            'html' => $data['html'] ?? null,
            'text' => $data['text'] ?? null,
            'attachments' => $this->uploads($request),
        ]);

        $client = $this->client($request);
        try {
            $drafts = $client->folderByRole('drafts');
            if (! $drafts) {
                $client->createFolder('Drafts');
                $drafts = $client->folderByRole('drafts') ?? 'Drafts';
            }
            $client->append($drafts, $email->toString(), ['\\Seen', '\\Draft']);

            return response()->json(['message' => 'Draft saved.', 'folder' => $drafts], 201);
        } finally {
            $client->disconnect();
        }
    }

    /** @return array<int, array{name: string, content: string, content_type: string}> */
    protected function uploads(Request $request): array
    {
        return collect($request->file('attachments', []))
            ->filter(fn ($f) => $f instanceof UploadedFile)
            ->map(fn (UploadedFile $f) => [
                'name' => $f->getClientOriginalName(),
                'content' => $f->getContent(),
                'content_type' => $f->getMimeType() ?: 'application/octet-stream',
            ])->values()->all();
    }
}
