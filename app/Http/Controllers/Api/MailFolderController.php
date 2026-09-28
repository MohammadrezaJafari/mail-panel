<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesMailbox;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MailFolderController extends Controller
{
    use ResolvesMailbox;

    public function index(Request $request): JsonResponse
    {
        $client = $this->client($request);

        try {
            return response()->json(['data' => $client->folders()]);
        } finally {
            $client->disconnect();
        }
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'path' => ['required', 'string'],
            'name' => ['required', 'string', 'max:200', 'regex:/^[^\/\\\\]+$/'],
        ]);
        $client = $this->client($request);

        try {
            $folders = $client->folders();
            $current = collect($folders)->firstWhere('path', $data['path']);
            abort_unless($current, 404);
            abort_if($current['role'] !== null, 422, 'System folders cannot be renamed.');

            $parent = str_contains($data['path'], $current['delimiter']) ? substr($data['path'], 0, strrpos($data['path'], $current['delimiter']) + 1) : '';
            $client->renameFolder($data['path'], $parent.$data['name']);

            return response()->json(['data' => $client->folders()]);
        } finally {
            $client->disconnect();
        }
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate(['path' => ['required', 'string']]);
        $client = $this->client($request);

        try {
            $client->deleteFolder($data['path']);

            return response()->json(['data' => $client->folders()]);
        } catch (\InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        } finally {
            $client->disconnect();
        }
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'regex:/^[^\/\\\\]+$/'],
            'parent' => ['nullable', 'string'],
        ]);
        $client = $this->client($request);

        try {
            $path = $data['name'];
            if (! empty($data['parent'])) {
                $parent = collect($client->folders())->firstWhere('path', $data['parent']);
                abort_unless($parent, 404);
                $path = $parent['path'].$parent['delimiter'].$data['name'];
            }
            $client->createFolder($path);

            return response()->json(['data' => $client->folders()], 201);
        } finally {
            $client->disconnect();
        }
    }
}
