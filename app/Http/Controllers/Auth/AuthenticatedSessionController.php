<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ]);

        $throttleKey = Str::transliterate(Str::lower($request->input('email')).'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            AuditLog::log('auth.throttled', null, [
                'email' => $request->input('email'),
                'available_in_seconds' => $seconds,
            ], $request);

            throw ValidationException::withMessages([
                'email' => trans('auth.throttle', [
                    'seconds' => $seconds,
                    'minutes' => ceil($seconds / 60),
                ]),
            ]);
        }

        $credentials = $request->only('email', 'password');
        $remember = (bool) $request->boolean('remember');

        if (! Auth::validate($credentials)) {
            RateLimiter::hit($throttleKey, 60);

            AuditLog::log('auth.failed', null, [
                'email' => $request->input('email'),
            ], $request);

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        /** @var User $user */
        $user = User::where('email', $request->input('email'))->firstOrFail();

        if (! $user->isActive()) {
            RateLimiter::hit($throttleKey, 60);

            AuditLog::log('auth.deactivated_login_attempt', $user->id, [
                'email' => $user->email,
            ], $request);

            throw ValidationException::withMessages([
                'email' => 'Your account has been deactivated. Please contact an administrator.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        // Enforce Two-Factor Authentication for Super Admins or users with 2FA enabled
        if ($user->isSuperAdmin() || $user->hasTwoFactorEnabled()) {
            $request->session()->put([
                'auth.2fa.user_id' => $user->id,
                'auth.2fa.remember' => $remember,
            ]);

            AuditLog::log('auth.2fa_challenge', $user->id, [
                'is_super_admin' => $user->isSuperAdmin(),
            ], $request);

            return redirect()->route('two-factor.challenge');
        }

        Auth::login($user, $remember);

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        $request->session()->regenerate();

        AuditLog::log('auth.login', $user->id, [
            'role' => $user->role,
        ], $request);

        return redirect()->intended(route('welcome'));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user) {
            AuditLog::log('auth.logout', $user->id, [], $request);
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
