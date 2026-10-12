<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureHasPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401, 'Unauthenticated.');
        }

        // Active account check
        if (! $user->isActive()) {
            abort(403, 'Your account is inactive.');
        }

        // Super Admin has platform-level override
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        // Determine branch context from route param, query, header, or session
        $branchId = $request->route('branch')
            ?? $request->route('branch_id')
            ?? $request->input('branch_id')
            ?? $request->header('X-Branch-ID')
            ?? $request->session()->get('active_branch_id');

        $branchId = is_numeric($branchId) ? (int) $branchId : null;

        if (! $user->hasPermission($permission, $branchId)) {
            AuditLog::record(
                'unauthorized_permission_access_attempt',
                [
                    'permission' => $permission,
                    'branch_id' => $branchId,
                    'path' => $request->path(),
                    'method' => $request->method(),
                ],
                $user
            );

            abort(403, "You do not have permission [{$permission}] to perform this action.");
        }

        return $next($request);
    }
}
