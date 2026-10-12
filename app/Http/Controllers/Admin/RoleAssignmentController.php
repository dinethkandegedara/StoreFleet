<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\BranchUserRole;
use App\Models\Role;
use App\Models\User;
use App\Services\PermissionRegistrar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RoleAssignmentController extends Controller
{
    public function __construct(
        protected PermissionRegistrar $registrar
    ) {}

    /**
     * Display listing of user branch role assignments.
     */
    public function index(Request $request): Response
    {
        $users = User::with([
            'branchUserRoles.role',
            'branchUserRoles.branch',
        ])
            ->where('is_super_admin', false)
            ->orderBy('name')
            ->paginate(20)
            ->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_active' => $user->is_active,
                'assignments' => $user->branchUserRoles->map(fn (BranchUserRole $bur) => [
                    'id' => $bur->id,
                    'role_id' => $bur->role_id,
                    'role_name' => $bur->role?->name,
                    'role_slug' => $bur->role?->slug,
                    'role_active' => $bur->role?->is_active,
                    'branch_id' => $bur->branch_id,
                    'branch_name' => $bur->branch?->name ?? 'All Branches (Global)',
                    'branch_code' => $bur->branch?->code ?? 'ALL',
                ]),
            ]);

        $roles = Role::where('is_active', true)->orderBy('name')->get(['id', 'name', 'slug', 'is_system']);
        $branches = Branch::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']);

        return Inertia::render('Admin/Roles/Assignments', [
            'users' => $users,
            'roles' => $roles,
            'branches' => $branches,
        ]);
    }

    /**
     * Assign a role to a user within a branch scope.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'role_id' => ['required', 'exists:roles,id'],
            'branch_id' => ['nullable', 'exists:branches,id'],
        ]);

        $actor = $request->user();
        $targetUser = User::findOrFail($validated['user_id']);
        $role = Role::with('permissions')->findOrFail($validated['role_id']);

        // Prevent target user from being a Super Admin (Super Admins are platform-level)
        if ($targetUser->isSuperAdmin()) {
            throw ValidationException::withMessages([
                'user_id' => 'Platform Super Admins cannot be assigned business-level branch roles.',
            ]);
        }

        // Delegation check: actor must have all permissions contained in this role
        $rolePermissions = $role->permissions->pluck('slug')->all();
        if (! $this->registrar->canDelegate($actor, $rolePermissions, $validated['branch_id'] ?? null)) {
            AuditLog::record('unauthorized_role_delegation_attempt', [
                'target_user_id' => $targetUser->id,
                'role_slug' => $role->slug,
                'branch_id' => $validated['branch_id'] ?? null,
            ], $actor);

            throw ValidationException::withMessages([
                'role_id' => 'Privilege escalation rejected: You cannot assign a role with permissions you do not possess.',
            ]);
        }

        // Avoid duplicate assignment
        $existing = BranchUserRole::where('user_id', $targetUser->id)
            ->where('role_id', $role->id)
            ->where('branch_id', $validated['branch_id'] ?? null)
            ->exists();

        if ($existing) {
            return back()->with('info', "User already has role '{$role->name}' in this scope.");
        }

        DB::transaction(function () use ($targetUser, $role, $validated, $actor) {
            BranchUserRole::create([
                'user_id' => $targetUser->id,
                'role_id' => $role->id,
                'branch_id' => $validated['branch_id'] ?? null,
            ]);

            AuditLog::record('role_assigned_to_user', [
                'target_user_id' => $targetUser->id,
                'target_user_email' => $targetUser->email,
                'role_id' => $role->id,
                'role_slug' => $role->slug,
                'branch_id' => $validated['branch_id'] ?? null,
            ], $actor);
        });

        return back()->with('success', "Role '{$role->name}' assigned to {$targetUser->name}.");
    }

    /**
     * Revoke a role assignment.
     */
    public function destroy(Request $request, BranchUserRole $assignment): RedirectResponse
    {
        $role = $assignment->role;
        $user = $assignment->user;

        // Protection against removing the last active Business Owner
        if ($role && $role->slug === 'business-owner') {
            $otherOwnersCount = BranchUserRole::where('role_id', $role->id)
                ->where('id', '!=', $assignment->id)
                ->whereHas('user', fn ($q) => $q->where('is_active', true))
                ->count();

            if ($otherOwnersCount === 0) {
                return back()->with('error', 'Cannot remove the last active Business Owner. Essential administrative access must be preserved.');
            }
        }

        DB::transaction(function () use ($assignment, $role, $user, $request) {
            $assignmentData = [
                'target_user_id' => $user?->id,
                'role_slug' => $role?->slug,
                'branch_id' => $assignment->branch_id,
            ];

            $assignment->delete();

            AuditLog::record('role_revoked_from_user', $assignmentData, $request->user());
        });

        return back()->with('success', 'Role assignment revoked.');
    }
}
