<?php

namespace App\Http\Controllers;

use App\Models\TransactionLog;
use App\Models\User;
use App\Support\RegistrarSession;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function handle(Request $request)
    {
        if ($request->isMethod('GET')) {
            return response()->json(['ok' => true, 'user' => RegistrarSession::user()]);
        }

        $action = (string) $request->input('action', 'login');
        if ($action === 'logout') {
            RegistrarSession::logout();

            return response()->json(['ok' => true, 'user' => null]);
        }
        if ($action === 'lookup') {
            return response()->json([
                'ok' => true,
                'found' => $this->emailHasActiveAccount((string) $request->input('email', '')),
            ]);
        }
        if ($action !== 'login') {
            return response()->json(['ok' => false, 'error' => 'Unknown auth action'], 400);
        }

        $email = strtolower(trim((string) $request->input('email', '')));
        $password = (string) $request->input('password', '');
        if ($email === '' || $password === '') {
            return response()->json(['ok' => false, 'error' => 'Enter your email and password.'], 400);
        }

        $account = User::query()->where('email', $email)->first();
        if (! $account || ! password_verify($password, (string) $account->password_hash)) {
            return response()->json(['ok' => false, 'error' => 'Invalid email or password.'], 401);
        }
        if (! $account->is_active) {
            return response()->json(['ok' => false, 'error' => 'This account is inactive. Ask Super Admin to turn it back on.'], 403);
        }

        RegistrarSession::login((int) $account->user_id);
        $user = RegistrarSession::user();
        if (! $user) {
            return response()->json(['ok' => false, 'error' => 'Could not start a session for this account.'], 500);
        }

        $this->recordLogin();

        return response()->json(['ok' => true, 'user' => $user]);
    }

    private function emailHasActiveAccount(string $email): bool
    {
        $email = strtolower(trim($email));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        return User::query()->where('email', $email)->where('is_active', 1)->exists();
    }

    private function recordLogin(): void
    {
        $id = RegistrarSession::id();
        $account = $id ? User::query()->find($id) : null;
        $name = trim((string) ($account->full_name ?? ''));
        TransactionLog::query()->create([
            'user_id' => $id,
            'actor_name' => $name !== '' ? $name : 'Unknown',
            'actor_role' => trim((string) ($account->role ?? '')) !== '' ? (string) $account->role : 'unknown',
            'action' => 'Login',
            'area' => 'login',
            'summary' => 'Signed in.',
        ]);
    }
}
