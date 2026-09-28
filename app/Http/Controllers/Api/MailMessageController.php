<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesMailbox;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MailMessageController extends Controller
{
    use ResolvesMailbox;

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'folder' => ['required', 'string'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
            'search' => ['nullable', 'string', 'max:200'],
            'filter' => ['nullable', 'in:unread,flagged,attachments'],
            'since_uid' => ['nullable', 'integer', 'min:1'],
            'from' => ['nullable', 'string', 'max:200'],
            'to' => ['nullable', 'string', 'max:200'],
            'subject' => ['nullable', 'string', 'max:200'],
            'since' => ['nullable', 'date'],
            'before' => ['nullable', 'date'],
        ]);

        $client = $this->client($request);

        try {
            return response()->json($client->messages(
                $data['folder'],
                (int) ($data['page'] ?? 1),
                (int) ($data['per_page'] ?? 25),
                $data['search'] ?? null,
                $data['filter'] ?? null,
                isset($data['since_uid']) ? (int) $data['since_uid'] : null,
                array_intersect_key($data, array_flip(['from', 'to', 'subject', 'since', 'before'])),
            ));
        } finally {
            $client->disconnect();
        }
    }

    public function show(Request $request, int $uid): JsonResponse
    {
        $data = $request->validate(['folder' => ['required', 'string'], 'mark_seen' => ['nullable', 'boolean']]);
        $client = $this->client($request);

        try {
            return response()->json(['data' => $client->message($data['folder'], $uid, $request->boolean('mark_seen', true))]);
        } finally {
            $client->disconnect();
        }
    }

    public function attachment(Request $request, int $uid, string $attachment): Response
    {
        $data = $request->validate(['folder' => ['required', 'string']]);
        $client = $this->client($request);

        try {
            $file = $client->attachment($data['folder'], $uid, $attachment);
            abort_unless($file, 404);

            return response($file['content'], 200, [
                'Content-Type' => $file['content_type'],
                'Content-Disposition' => 'attachment; filename="'.addslashes($file['name']).'"',
            ]);
        } finally {
            $client->disconnect();
        }
    }

    public function flags(Request $request): JsonResponse
    {
        $data = $request->validate([
            'folder' => ['required', 'string'],
            'uids' => ['required', 'array', 'min:1', 'max:200'],
            'uids.*' => ['integer'],
            'seen' => ['nullable', 'boolean'],
            'flagged' => ['nullable', 'boolean'],
        ]);

        $flags = array_filter(['seen' => $data['seen'] ?? null, 'flagged' => $data['flagged'] ?? null], fn ($v) => $v !== null);
        $client = $this->client($request);

        try {
            $client->setFlags($data['folder'], $data['uids'], $flags);

            return response()->json(['message' => 'Updated.']);
        } finally {
            $client->disconnect();
        }
    }

    public function move(Request $request): JsonResponse
    {
        $data = $request->validate([
            'folder' => ['required', 'string'],
            'uids' => ['required', 'array', 'min:1', 'max:200'],
            'uids.*' => ['integer'],
            'to' => ['required', 'string'],
        ]);

        $client = $this->client($request);

        try {
            $client->move($data['folder'], $data['uids'], $data['to']);

            return response()->json(['message' => 'Moved.']);
        } finally {
            $client->disconnect();
        }
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'folder' => ['required', 'string'],
            'uids' => ['required', 'array', 'min:1', 'max:200'],
            'uids.*' => ['integer'],
        ]);

        $client = $this->client($request);

        try {
            $client->delete($data['folder'], $data['uids']);

            return response()->json(['message' => 'Deleted.']);
        } finally {
            $client->disconnect();
        }
    }
}
