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
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function __construct(
        protected PermissionRegistrar $permissionRegistrar
    ) {}

    /**
     * Display a listing of staff and system users with filters.
     */
    public function index(Request $request): Response
    {
        $currentUser = $request->user();

        $query = User::query()
            ->with([
                'branchUserRoles.branch:id,name,code',
                'branchUserRoles.role:id,name,slug',
            ]);

        // Search: Name or Email
        if ($search = trim((string) $request->input('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filter: Status (all, active, inactive)
        $status = $request->input('status');
        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        // Filter: Branch
        if ($branchId = $request->input('branch_id')) {
            $query->whereHas('branchUserRoles', function ($q) use ($branchId) {
                $q->where('branch_id', (int) $branchId)
                    ->orWhereNull('branch_id'); // Global assignments apply
            });
        }

        // Filter: Role
        if ($roleId = $request->input('role_id')) {
            $query->whereHas('branchUserRoles', function ($q) use ($roleId) {
                $q->where('role_id', (int) $roleId);
            });
        }

        // Order by name
        $query->orderBy('name', 'asc');

        $users = $query->paginate(15)->withQueryString();

        // Transform user list for clean UI
        $users->through(function (User $user) use ($currentUser) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_active' => (bool) $user->is_active,
                'is_super_admin' => (bool) $user->is_super_admin,
                'has_2fa' => ! empty($user->two_factor_confirmed_at),
                'theme_preference' => $user->theme_preference,
                'created_at' => $user->created_at?->format('M d, Y'),
                'is_self' => $user->id === $currentUser->id,
                'assignments' => $user->branchUserRoles->map(fn ($bur) => [
                    'id' => $bur->id,
                    'branch_id' => $bur->branch_id,
                    'branch_name' => $bur->branch?->name ?? 'All Branches (Global)',
                    'branch_code' => $bur->branch?->code ?? 'GLOBAL',
                    'role_id' => $bur->role_id,
                    'role_name' => $bur->role?->name ?? 'Custom',
                    'role_slug' => $bur->role?->slug ?? '',
                ])->values()->all(),
            ];
        });

        $branches = Branch::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']);
        $roles = Role::where('is_active', true)->orderBy('name')->get(['id', 'name', 'slug', 'is_system']);

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'filters' => [
                'search' => $request->input('search', ''),
                'status' => $request->input('status', 'all'),
                'branch_id' => $request->input('branch_id', ''),
                'role_id' => $request->input('role_id', ''),
            ],
            'branches' => $branches,
            'roles' => $roles,
            'canCreate' => $currentUser->hasPermission('users.create'),
            'canEdit' => $currentUser->hasPermission('users.edit'),
            'canManage' => $currentUser->hasPermission('users.deactivate'),
        ]);
    }

    /**
     * Show form for creating a new staff account.
     */
    public function create(Request $request): Response
    {
        $branches = Branch::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']);
        $roles = Role::where('is_active', true)->orderBy('name')->get(['id', 'name', 'slug', 'is_system']);

        return Inertia::render('Admin/Users/Form', [
            'user' => null,
            'branches' => $branches,
            'roles' => $roles,
        ]);
    }

    /**
     * Store a newly created staff account.
     */
    public function store(Request $request): RedirectResponse
    {
        $currentUser = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
            'is_active' => ['required', 'boolean'],
            'assignments' => ['nullable', 'array'],
            'assignments.*.branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'assignments.*.role_id' => ['required', 'integer', 'exists:roles,id'],
        ]);

        // Prevent privilege escalation: actor must be able to delegate every role
        $this->validateAssignmentsAndDelegation($currentUser, $validated['assignments'] ?? []);

        DB::transaction(function () use ($validated, $request) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'is_active' => $validated['is_active'],
            ]);

            if (! empty($validated['assignments'])) {
                foreach ($validated['assignments'] as $assign) {
                    BranchUserRole::firstOrCreate([
                        'user_id' => $user->id,
                        'branch_id' => ! empty($assign['branch_id']) ? (int) $assign['branch_id'] : null,
                        'role_id' => (int) $assign['role_id'],
                    ]);
                }
            }

            AuditLog::record('user.created', [
                'created_user_id' => $user->id,
                'email' => $user->email,
                'is_active' => $user->is_active,
                'assignments_count' => count($validated['assignments'] ?? []),
            ], $request->user());
        });

        return redirect()->route('admin.users.index')
            ->with('success', "Staff account for \"{$validated['name']}\" created successfully.");
    }

    /**
     * Show form for editing an existing staff account.
     */
    public function edit(Request $request, User $user): Response
    {
        $currentUser = $request->user();

        // Prevent non-super admins from modifying a super admin account
        if ($user->isSuperAdmin() && ! $currentUser->isSuperAdmin()) {
            abort(403, 'Only a Super Admin can edit Super Admin accounts.');
        }

        $user->load([
            'branchUserRoles.branch:id,name,code',
            'branchUserRoles.role:id,name,slug',
        ]);

        $branches = Branch::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']);
        $roles = Role::where('is_active', true)->orderBy('name')->get(['id', 'name', 'slug', 'is_system']);

        return Inertia::render('Admin/Users/Form', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_active' => (bool) $user->is_active,
                'is_super_admin' => (bool) $user->is_super_admin,
                'assignments' => $user->branchUserRoles->map(fn ($bur) => [
                    'id' => $bur->id,
                    'branch_id' => $bur->branch_id,
                    'role_id' => $bur->role_id,
                ])->values()->all(),
            ],
            'branches' => $branches,
            'roles' => $roles,
        ]);
    }

    /**
     * Update an existing staff account.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $currentUser = $request->user();

        // Prevent non-super admins from modifying a super admin account
        if ($user->isSuperAdmin() && ! $currentUser->isSuperAdmin()) {
            abort(403, 'Only a Super Admin can edit Super Admin accounts.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => ['nullable', 'string', Password::defaults(), 'confirmed'],
            'is_active' => ['required', 'boolean'],
            'assignments' => ['nullable', 'array'],
            'assignments.*.branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'assignments.*.role_id' => ['required', 'integer', 'exists:roles,id'],
        ]);

        // Guard: Prevent self-deactivation
        if ($user->id === $currentUser->id && ! $validated['is_active']) {
            return back()->with('error', 'You cannot deactivate your own active session account.');
        }

        // Prevent privilege escalation: actor must be able to delegate every role
        $this->validateAssignmentsAndDelegation($currentUser, $validated['assignments'] ?? [], $user);

        // Protection against removing or deactivating the last active business owner
        $this->validateLastBusinessOwnerPreservation($user, (bool) $validated['is_active'], $validated['assignments'] ?? []);

        DB::transaction(function () use ($user, $validated, $request) {
            $user->name = $validated['name'];
            $user->email = $validated['email'];
            $user->is_active = $validated['is_active'];

            if (! empty($validated['password'])) {
                $user->password = Hash::make($validated['password']);
            }

            $user->save();

            // Sync assignments
            BranchUserRole::where('user_id', $user->id)->delete();

            if (! empty($validated['assignments'])) {
                foreach ($validated['assignments'] as $assign) {
                    BranchUserRole::firstOrCreate([
                        'user_id' => $user->id,
                        'branch_id' => ! empty($assign['branch_id']) ? (int) $assign['branch_id'] : null,
                        'role_id' => (int) $assign['role_id'],
                    ]);
                }
            }

            $this->permissionRegistrar->clearUserPermissionCache($user->id);

            AuditLog::record('user.updated', [
                'target_user_id' => $user->id,
                'email' => $user->email,
                'is_active' => $user->is_active,
                'password_updated' => ! empty($validated['password']),
            ], $request->user());
        });

        return redirect()->route('admin.users.index')
            ->with('success', "Staff account for \"{$user->name}\" updated successfully.");
    }

    /**
     * Fast toggle of active lifecycle status.
     */
    public function toggleActive(Request $request, User $user): RedirectResponse
    {
        $currentUser = $request->user();

        // Guard: Cannot deactivate self
        if ($user->id === $currentUser->id) {
            return back()->with('error', 'You cannot change the active status of your own account.');
        }

        // Guard: Cannot deactivate super admin unless caller is super admin
        if ($user->isSuperAdmin() && ! $currentUser->isSuperAdmin()) {
            abort(403, 'Only a Super Admin can modify the status of Super Admin accounts.');
        }

        // Guard: Cannot deactivate the last active business owner
        if ($user->is_active && $user->branchUserRoles()->whereHas('role', fn ($q) => $q->where('slug', 'business-owner'))->exists()) {
            $ownerRole = Role::where('slug', 'business-owner')->first();
            $otherActiveOwnersCount = BranchUserRole::where('role_id', $ownerRole?->id)
                ->where('user_id', '!=', $user->id)
                ->whereHas('user', fn ($q) => $q->where('is_active', true))
                ->count();

            if ($otherActiveOwnersCount === 0) {
                return back()->with('error', 'Cannot deactivate the last active Business Owner. Essential administrative access must be preserved.');
            }
        }

        $user->is_active = ! $user->is_active;
        $user->save();

        $this->permissionRegistrar->clearUserPermissionCache($user->id);

        $statusStr = $user->is_active ? 'activated' : 'deactivated';

        AuditLog::record('user.status_toggled', [
            'target_user_id' => $user->id,
            'email' => $user->email,
            'is_active' => $user->is_active,
        ], $currentUser);

        return back()->with('success', "Staff account for \"{$user->name}\" has been {$statusStr}.");
    }

    /**
     * Validate that the current user has authority to delegate each assigned role.
     *
     * @param  array<int, array<string, mixed>>  $assignments
     *
     * @throws ValidationException
     */
    protected function validateAssignmentsAndDelegation(User $currentUser, array $assignments, ?User $targetUser = null): void
    {
        if ($targetUser?->isSuperAdmin() && ! empty($assignments)) {
            throw ValidationException::withMessages([
                'assignments' => 'Platform Super Admins cannot be assigned business-level branch roles.',
            ]);
        }

        foreach ($assignments as $idx => $assign) {
            $role = Role::with('permissions')->find($assign['role_id']);
            if (! $role) {
                continue;
            }

            $branchId = ! empty($assign['branch_id']) ? (int) $assign['branch_id'] : null;
            $rolePermissions = $role->permissions->pluck('slug')->all();

            if (! $this->permissionRegistrar->canDelegate($currentUser, $rolePermissions, $branchId)) {
                AuditLog::record('unauthorized_role_delegation_attempt', [
                    'target_user_id' => $targetUser?->id,
                    'role_slug' => $role->slug,
                    'branch_id' => $branchId,
                ], $currentUser);

                throw ValidationException::withMessages([
                    "assignments.{$idx}.role_id" => "Privilege escalation rejected: You cannot assign role '{$role->name}' with permissions you do not possess.",
                ]);
            }
        }
    }

    /**
     * Validate that changes do not leave the system without any active Business Owner.
     *
     * @param  array<int, array<string, mixed>>  $newAssignments
     *
     * @throws ValidationException
     */
    protected function validateLastBusinessOwnerPreservation(User $targetUser, bool $targetWillBeActive, array $newAssignments): void
    {
        $isCurrentlyActiveOwner = $targetUser->is_active && $targetUser->branchUserRoles()
            ->whereHas('role', fn ($q) => $q->where('slug', 'business-owner'))
            ->exists();

        if (! $isCurrentlyActiveOwner) {
            return;
        }

        $willStillBeOwner = false;
        if ($targetWillBeActive) {
            $ownerRole = Role::where('slug', 'business-owner')->first();
            if ($ownerRole) {
                foreach ($newAssignments as $assign) {
                    if ((int) $assign['role_id'] === $ownerRole->id) {
                        $willStillBeOwner = true;
                        break;
                    }
                }
            }
        }

        if (! $willStillBeOwner) {
            $ownerRole = Role::where('slug', 'business-owner')->first();
            $otherActiveOwnersCount = BranchUserRole::where('role_id', $ownerRole?->id)
                ->where('user_id', '!=', $targetUser->id)
                ->whereHas('user', fn ($q) => $q->where('is_active', true))
                ->count();

            if ($otherActiveOwnersCount === 0) {
                throw ValidationException::withMessages([
                    'is_active' => 'Cannot deactivate or remove the role of the last active Business Owner. Essential administrative access must be preserved.',
                ]);
            }
        }
    }
}
