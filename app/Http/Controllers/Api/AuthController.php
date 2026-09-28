<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Mail\Client\ImapClientFactory;
use App\Models\Mailbox;
use App\Models\MailboxMember;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        protected ImapClientFactory $imap,
        protected AuditLogger $audit,
    ) {}

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device' => ['nullable', 'string', 'max:100'],
        ]);

        $email = strtolower($data['email']);
        $user = User::where('email', $email)->first();

        $valid = $user && $user->is_active && Hash::check($data['password'], $user->password);

        // Fallback: verify against the mail server and provision a portal user.
        if (! $valid && config('mailprovider.auth_via_imap')) {
            $mailbox = Mailbox::where('address', $email)->first();
            if ($mailbox && $mailbox->isActive() && $this->imap->check($email, $data['password'])) {
                $user ??= new User(['email' => $email, 'name' => $mailbox->name, 'role' => UserRole::User]);
                $user->organization_id = $mailbox->organization_id;
                $user->is_active = true;
                $user->password = $data['password'];
                $user->save();
                MailboxMember::firstOrCreate(['mailbox_id' => $mailbox->id, 'user_id' => $user->id], ['role' => MailboxMember::ROLE_OWNER]);
                $valid = true;
            }
        }

        if (! $valid) {
            $this->audit->log('auth.login_failed', null, ['email' => $email]);

            throw ValidationException::withMessages(['email' => 'These credentials do not match our records.']);
        }

        $user->forceFill([
            'mail_password' => $data['password'],
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        $token = $user->createToken($data['device'] ?? 'web-app')->plainTextToken;
        $this->audit->log('auth.login', $user, [], $user);

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        if (! $request->user()->tokens()->exists()) {
            $request->user()->forceFill(['mail_password' => null])->save();
        }

        return response()->json(['message' => 'Signed out.']);
    }
}
