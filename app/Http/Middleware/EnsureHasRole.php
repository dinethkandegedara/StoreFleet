<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureHasRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401, 'Unauthenticated.');
        }

        if (! $user->isActive()) {
            abort(403, 'Your account is inactive.');
        }

        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        $branchId = $request->route('branch')
            ?? $request->route('branch_id')
            ?? $request->input('branch_id')
            ?? $request->header('X-Branch-ID')
            ?? $request->session()->get('active_branch_id');

        $branchId = is_numeric($branchId) ? (int) $branchId : null;

        if (! $user->hasRole($role, $branchId)) {
            AuditLog::record(
                'unauthorized_role_access_attempt',
                [
                    'role' => $role,
                    'branch_id' => $branchId,
                    'path' => $request->path(),
                    'method' => $request->method(),
                ],
                $user
            );

            abort(403, "You do not have the required role [{$role}] to perform this action.");
        }

        return $next($request);
    }
}
