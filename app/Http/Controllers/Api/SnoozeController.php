<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesMailbox;
use App\Http\Controllers\Controller;
use App\Models\Snooze;
use App\Services\ScheduledMailService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SnoozeController extends Controller
{
    use ResolvesMailbox;

    public function __construct(protected ScheduledMailService $service) {}

    public function index(Request $request): JsonResponse
    {
        $items = Snooze::query()
            ->where('user_id', $request->user()->id)
            ->where('status', Snooze::STATUS_PENDING)
            ->orderBy('wake_at')
            ->get()
            ->map(fn (Snooze $s) => ['id' => $s->id, 'subject' => $s->subject, 'wake_at' => $s->wake_at?->toIso8601String(), 'origin_folder' => $s->origin_folder]);

        return response()->json(['data' => $items]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'folder' => ['required', 'string'],
            'messages' => ['required', 'array', 'min:1', 'max:50'],
            'messages.*.uid' => ['required', 'integer'],
            'messages.*.message_id' => ['nullable', 'string', 'max:998'],
            'messages.*.subject' => ['nullable', 'string', 'max:998'],
            'until' => ['required', 'date', 'after:now'],
        ]);

        $client = $this->client($request);
        try {
            $count = $this->service->snooze($request->user(), $client, $data['folder'], $data['messages'], Carbon::parse($data['until']));
        } finally {
            $client->disconnect();
        }

        return response()->json(['message' => 'Snoozed.', 'count' => $count], 201);
    }
}
