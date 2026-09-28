<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesMailbox;
use App\Http\Controllers\Controller;
use App\Models\Alias;
use App\Services\AliasService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AliasController extends Controller
{
    use ResolvesMailbox;

    public function __construct(protected AliasService $aliases) {}

    public function index(Request $request): JsonResponse
    {
        $mailbox = $this->currentMailbox($request);

        return response()->json(['data' => $mailbox->aliases()->orderBy('address')->get()->map($this->present(...))]);
    }

    public function store(Request $request): JsonResponse
    {
        $mailbox = $this->currentMailbox($request);
        $domain = $mailbox->domain;

        $data = $request->validate([
            'local_part' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9._+-]+$/i'],
        ]);

        if ($mailbox->aliases()->count() >= 10) {
            throw ValidationException::withMessages(['local_part' => 'You reached the maximum number of aliases (10).']);
        }

        $alias = $this->aliases->create($domain, ['address' => strtolower($data['local_part']).'@'.$domain->name], $mailbox);

        return response()->json(['data' => $this->present($alias)], 201);
    }

    public function destroy(Request $request, Alias $alias): JsonResponse
    {
        $mailbox = $this->currentMailbox($request);

        abort_unless($alias->mailbox_id === $mailbox->id, 403);

        $this->aliases->delete($alias);

        return response()->json(['message' => 'Alias removed.']);
    }

    protected function present(Alias $alias): array
    {
        return [
            'id' => $alias->id,
            'address' => $alias->address,
            'goto' => $alias->goto,
            'is_active' => $alias->is_active,
            'created_at' => $alias->created_at?->toIso8601String(),
        ];
    }
}
