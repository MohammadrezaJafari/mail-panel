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

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100', 'regex:/^[^\/\\\\]+$/']]);
        $client = $this->client($request);

        try {
            $client->createFolder($data['name']);

            return response()->json(['data' => $client->folders()], 201);
        } finally {
            $client->disconnect();
        }
    }
}
