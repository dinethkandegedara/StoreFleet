<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdmin
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isSuperAdmin() || ! $user->isActive()) {
            AuditLog::log('auth.unauthorized_super_admin_attempt', $user?->id, [
                'target_url' => $request->fullUrl(),
            ], $request);

            abort(403, 'Super Administrator privileges are strictly required to access this resource.');
        }

        return $next($request);
    }
}
