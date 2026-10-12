<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\TwoFactorAuthenticationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TwoFactorChallengeController extends Controller
{
    public function __construct(
        protected TwoFactorAuthenticationService $twoFactorService
    ) {}

    /**
     * Display the 2FA challenge view.
     */
    public function create(Request $request): Response|RedirectResponse
    {
        if (! $request->session()->has('auth.2fa.user_id')) {
            return redirect()->route('login');
        }

        return Inertia::render('Auth/TwoFactorChallenge');
    }

    /**
     * Handle the 2FA challenge authentication.
     */
    public function store(Request $request): RedirectResponse
    {
        $userId = $request->session()->get('auth.2fa.user_id');

        if (! $userId) {
            return redirect()->route('login');
        }

        /** @var User $user */
        $user = User::findOrFail($userId);

        $throttleKey = '2fa|'.$user->id.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            AuditLog::log('auth.2fa_throttled', $user->id, [
                'available_in_seconds' => $seconds,
            ], $request);

            throw ValidationException::withMessages([
                'code' => "Too many two-factor attempts. Please try again in {$seconds} seconds.",
            ]);
        }

        $request->validate([
            'code' => ['nullable', 'string', 'size:6'],
            'recovery_code' => ['nullable', 'string'],
        ]);

        $recoveryCode = $request->input('recovery_code');
        $code = $request->input('code');

        if (empty($code) && empty($recoveryCode)) {
            throw ValidationException::withMessages([
                'code' => 'Please provide your 6-digit authentication code or a recovery code.',
            ]);
        }

        $authenticated = false;
        $authMethod = 'totp';

        if (! empty($recoveryCode)) {
            $authenticated = $user->consumeRecoveryCode($recoveryCode);
            $authMethod = 'recovery_code';
        } elseif (! empty($code) && $user->two_factor_secret) {
            $authenticated = $this->twoFactorService->verifyKey($user->two_factor_secret, $code);
        }

        if (! $authenticated) {
            RateLimiter::hit($throttleKey, 60);

            AuditLog::log('auth.2fa_failed', $user->id, [
                'method' => $authMethod,
            ], $request);

            $errorField = ! empty($recoveryCode) ? 'recovery_code' : 'code';

            throw ValidationException::withMessages([
                $errorField => 'The provided authentication code is invalid.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        $remember = (bool) $request->session()->get('auth.2fa.remember', false);
        $request->session()->forget(['auth.2fa.user_id', 'auth.2fa.remember']);

        Auth::login($user, $remember);

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        $request->session()->regenerate();

        AuditLog::log('auth.login', $user->id, [
            '2fa_verified' => true,
            '2fa_method' => $authMethod,
            'is_super_admin' => $user->isSuperAdmin(),
        ], $request);

        return redirect()->intended(route('welcome'));
    }
}
