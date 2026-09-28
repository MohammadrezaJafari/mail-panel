<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesMailbox;
use App\Http\Controllers\Controller;
use App\Services\ContactService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    use ResolvesMailbox;

    public function __construct(protected ContactService $contacts) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'limit' => ['nullable', 'integer', 'min:1', 'max:25']]);

        return response()->json(['data' => $this->contacts->search($request->user(), $data['q'] ?? '', (int) ($data['limit'] ?? 8))]);
    }

    public function sync(Request $request): JsonResponse
    {
        $user = $request->user();

        // Cheap throttle: a full header scan at most every 15 minutes unless forced.
        if (! $request->boolean('force') && $user->contacts_synced_at && $user->contacts_synced_at->gt(now()->subMinutes(15))) {
            return response()->json(['synced' => false, 'synced_at' => $user->contacts_synced_at->toIso8601String()]);
        }

        $client = $this->client($request);
        try {
            $count = $this->contacts->syncFromMailbox($user, $client);
        } finally {
            $client->disconnect();
        }

        return response()->json(['synced' => true, 'addresses' => $count, 'synced_at' => $user->fresh()->contacts_synced_at?->toIso8601String()]);
    }
}
