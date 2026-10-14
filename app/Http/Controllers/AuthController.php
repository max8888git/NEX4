<?php

namespace App\Http\Controllers;

use App\Models\TransactionLog;
use App\Models\User;
use App\Support\ProfilePhoto;
use App\Support\RegistrarSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        if ($action === 'change_password') {
            return $this->changePassword($request);
        }
        if ($action === 'set_photo' || $action === 'remove_photo') {
            return $this->updatePhoto($request, $action === 'remove_photo');
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
            return response()->json(['ok' => false, 'error' => 'This account is inactive. Ask the University Registrar to turn it back on.'], 403);
        }

        RegistrarSession::login((int) $account->user_id);
        $user = RegistrarSession::user();
        if (! $user) {
            return response()->json(['ok' => false, 'error' => 'Could not start a session for this account.'], 500);
        }

        $this->recordLogin();

        return response()->json(['ok' => true, 'user' => $user]);
    }

    /**
     * Any signed-in user changes their own password (current password required).
     * Same 5-character minimum as accounts created on Account management.
     */
    private function changePassword(Request $request)
    {
        $id = RegistrarSession::id();
        $account = $id ? User::query()->find($id) : null;
        if (! $account || ! $account->is_active) {
            return response()->json(['ok' => false, 'error' => 'Log in again, then change your password.'], 401);
        }

        $current = (string) $request->input('currentPassword', '');
        $new = (string) $request->input('newPassword', '');
        $confirm = (string) $request->input('newPasswordConfirm', '');
        if ($current === '' || ! password_verify($current, (string) $account->password_hash)) {
            return response()->json(['ok' => false, 'error' => 'Your current password is incorrect.'], 400);
        }
        if (strlen($new) < 5) {
            return response()->json(['ok' => false, 'error' => 'Use a new password of at least 5 characters.'], 400);
        }
        if ($new !== $confirm) {
            return response()->json(['ok' => false, 'error' => 'The new passwords do not match.'], 400);
        }
        if (password_verify($new, (string) $account->password_hash)) {
            return response()->json(['ok' => false, 'error' => 'Choose a password different from your current one.'], 400);
        }

        DB::table('users')->where('user_id', $id)->update(['password_hash' => password_hash($new, PASSWORD_DEFAULT)]);
        $this->record('Account', 'Changed their password.');

        return response()->json(['ok' => true]);
    }

    /** Any signed-in user sets or removes their own profile photo. */
    private function updatePhoto(Request $request, bool $remove)
    {
        $id = RegistrarSession::id();
        if (! $id || ! RegistrarSession::user()) {
            return response()->json(['ok' => false, 'error' => 'Log in again, then update your photo.'], 401);
        }
        if ($remove) {
            ProfilePhoto::delete($id);
        } else {
            try {
                ProfilePhoto::store($id, (string) $request->input('photo', ''));
            } catch (\InvalidArgumentException $e) {
                return response()->json(['ok' => false, 'error' => $e->getMessage()], 400);
            }
        }

        return response()->json(['ok' => true, 'user' => RegistrarSession::user()]);
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
        $this->record('Login', 'Signed in.', 'login');
    }

    /** Add a Transaction log entry for the signed-in user. */
    private function record(string $action, string $summary, string $area = 'account'): void
    {
        $id = RegistrarSession::id();
        $account = $id ? User::query()->find($id) : null;
        $name = trim((string) ($account->full_name ?? ''));
        TransactionLog::query()->create([
            'user_id' => $id,
            'actor_name' => $name !== '' ? $name : 'Unknown',
            'actor_role' => trim((string) ($account->role ?? '')) !== '' ? (string) $account->role : 'unknown',
            'action' => $action,
            'area' => $area,
            'summary' => $summary,
        ]);
    }
}
