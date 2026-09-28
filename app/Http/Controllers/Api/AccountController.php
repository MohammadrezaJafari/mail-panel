<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesMailbox;
use App\Http\Controllers\Controller;
use App\Http\Resources\MailboxResource;
use App\Http\Resources\UserResource;
use App\Services\MailboxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AccountController extends Controller
{
    use ResolvesMailbox;

    public function __construct(protected MailboxService $mailboxes) {}

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $mailbox = $user->primaryMailbox();

        return response()->json([
            'user' => new UserResource($user),
            'mailbox' => $mailbox ? new MailboxResource($mailbox->load('aliases', 'domain')) : null,
            'shared_mailboxes' => $user->mailboxes()->where('is_shared', true)->get()->map(fn ($m) => [
                'id' => $m->id,
                'address' => $m->address,
                'name' => $m->name,
                'role' => $m->pivot->role,
            ]),
            'webmail_url' => config('mailprovider.webmail_url'),
        ]);
    }

    public function mailbox(Request $request): MailboxResource
    {
        $mailbox = $this->currentMailbox($request);

        if (! $mailbox->usage_synced_at || $mailbox->usage_synced_at->lt(now()->subMinutes(5))) {
            try {
                $this->mailboxes->syncUsage($mailbox);
            } catch (\Throwable) {
                // Usage is informational; never block the portal on it.
            }
        }

        return new MailboxResource($mailbox->load('aliases', 'domain'));
    }

    public function updateSettings(Request $request): MailboxResource
    {
        $mailbox = $this->currentMailbox($request);

        $data = $request->validate([
            'forwarding_to' => ['nullable', 'array', 'max:5'],
            'forwarding_to.*' => ['email'],
            'forwarding_keep_copy' => ['boolean'],
            'auto_reply_enabled' => ['boolean'],
            'auto_reply_subject' => ['nullable', 'string', 'max:255'],
            'auto_reply_body' => ['nullable', 'string', 'max:5000'],
            'auto_reply_starts_at' => ['nullable', 'date'],
            'auto_reply_ends_at' => ['nullable', 'date', 'after_or_equal:auto_reply_starts_at'],
            'signature' => ['nullable', 'string', 'max:5000'],
        ]);

        if (($data['auto_reply_enabled'] ?? false) && blank($data['auto_reply_body'] ?? null)) {
            throw ValidationException::withMessages(['auto_reply_body' => 'An auto reply message is required.']);
        }

        return new MailboxResource($this->mailboxes->updateRules($mailbox, $data)->load('aliases', 'domain'));
    }

    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
        ]);

        $user = $request->user();

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'Current password is incorrect.']);
        }

        $this->mailboxes->resetPassword($this->currentMailbox($request), $data['password'], byUser: true);
        $user->forceFill(['password' => $data['password'], 'mail_password' => $data['password']])->save();

        return response()->json(['message' => 'Password updated.']);
    }

    public function webmail(): JsonResponse
    {
        return response()->json(['url' => config('mailprovider.webmail_url')]);
    }
}
