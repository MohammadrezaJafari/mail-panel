<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesMailbox;
use App\Http\Controllers\Controller;
use App\Models\ScheduledMessage;
use App\Services\ScheduledMailService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class ScheduledMailController extends Controller
{
    use ResolvesMailbox;

    public function __construct(protected ScheduledMailService $service) {}

    public function index(Request $request): JsonResponse
    {
        $items = ScheduledMessage::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('status', [ScheduledMessage::STATUS_PENDING, ScheduledMessage::STATUS_FAILED])
            ->orderBy('send_at')
            ->get()
            ->map($this->present(...));

        return response()->json(['data' => $items]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
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
            'send_at' => ['required', 'date', 'after:now'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'max:25600'],
        ]);

        $user = $request->user();
        $mailbox = $this->currentMailbox($request);
        $from = ['email' => $mailbox->address, 'name' => $user->name];
        if (! empty($data['from_alias']) && $mailbox->aliases()->where('address', strtolower($data['from_alias']))->exists()) {
            $from['email'] = strtolower($data['from_alias']);
        }

        $payload = [
            'from' => $from,
            'to' => $data['to'],
            'cc' => $data['cc'] ?? [],
            'bcc' => $data['bcc'] ?? [],
            'subject' => $data['subject'] ?? '',
            'html' => $data['html'] ?? null,
            'text' => $data['text'] ?? null,
            'in_reply_to' => $data['in_reply_to'] ?? null,
            'references' => $data['references'] ?? null,
        ];

        $attachments = collect($request->file('attachments', []))
            ->filter(fn ($f) => $f instanceof UploadedFile)
            ->map(fn (UploadedFile $f) => ['name' => $f->getClientOriginalName(), 'content' => $f->getContent(), 'content_type' => $f->getMimeType() ?: 'application/octet-stream'])
            ->values()->all();

        $scheduled = $this->service->schedule($user, $payload, $attachments, Carbon::parse($data['send_at']));

        return response()->json(['data' => $this->present($scheduled)], 201);
    }

    public function destroy(Request $request, ScheduledMessage $scheduled): JsonResponse
    {
        abort_unless($scheduled->user_id === $request->user()->id, 403);
        $this->service->cancel($scheduled);

        return response()->json(['message' => 'Cancelled.']);
    }

    protected function present(ScheduledMessage $m): array
    {
        return [
            'id' => $m->id,
            'to' => $m->payload['to'] ?? [],
            'subject' => $m->payload['subject'] ?? '',
            'send_at' => $m->send_at?->toIso8601String(),
            'status' => $m->status,
            'error' => $m->error,
            'attachments' => count($m->attachments ?? []),
        ];
    }
}
