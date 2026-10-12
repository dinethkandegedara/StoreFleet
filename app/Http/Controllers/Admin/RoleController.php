<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Services\PermissionRegistrar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    public function __construct(
        protected PermissionRegistrar $registrar
    ) {}

    /**
     * Display a listing of roles.
     */
    public function index(Request $request): Response
    {
        $roles = Role::withCount(['permissions', 'branchUserRoles as users_count'])
            ->orderBy('is_system', 'desc')
            ->orderBy('name', 'asc')
            ->get()
            ->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
                'description' => $role->description,
                'is_system' => $role->is_system,
                'is_active' => $role->is_active,
                'permissions_count' => $role->permissions_count,
                'users_count' => $role->users_count,
            ]);

        return Inertia::render('Admin/Roles/Index', [
            'roles' => $roles,
        ]);
    }

    /**
     * Show the form for creating a new role.
     */
    public function create(Request $request): Response
    {
        $catalog = $this->registrar->getCatalog();
        $permissions = Permission::all()->groupBy('module');

        // Actor's delegatable permissions
        $user = $request->user();
        $delegatableSlugs = $user->isSuperAdmin() || $user->hasRole('business-owner')
            ? Permission::pluck('slug')->all()
            : $user->getPermissions();

        return Inertia::render('Admin/Roles/Form', [
            'role' => null,
            'groupedPermissions' => $permissions,
            'catalog' => $catalog,
            'delegatableSlugs' => $delegatableSlugs,
        ]);
    }

    /**
     * Store a newly created custom role.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'alpha_dash', 'unique:roles,slug'],
            'description' => ['nullable', 'string', 'max:1000'],
            'permissions' => ['required', 'array'],
            'permissions.*' => ['string', 'exists:permissions,slug'],
        ]);

        $slug = ! empty($validated['slug']) ? Str::slug($validated['slug']) : Str::slug($validated['name']);

        // Check unique slug if auto-generated
        if (Role::where('slug', $slug)->exists()) {
            $slug .= '-'.Str::lower(Str::random(4));
        }

        // Delegation security check: cannot grant permissions actor does not possess
        $user = $request->user();
        if (! $this->registrar->canDelegate($user, $validated['permissions'])) {
            AuditLog::record('unauthorized_permission_delegation_attempt', [
                'role_slug' => $slug,
                'attempted_permissions' => $validated['permissions'],
            ], $user);

            throw ValidationException::withMessages([
                'permissions' => 'Privilege escalation rejected: You cannot assign permissions you do not personally hold.',
            ]);
        }

        $role = DB::transaction(function () use ($validated, $slug, $user) {
            $role = Role::create([
                'name' => $validated['name'],
                'slug' => $slug,
                'description' => $validated['description'] ?? null,
                'is_system' => false,
                'is_active' => true,
            ]);

            $permissionIds = Permission::whereIn('slug', $validated['permissions'])->pluck('id');
            $role->permissions()->sync($permissionIds);

            AuditLog::record('role_created', [
                'role_id' => $role->id,
                'role_slug' => $role->slug,
                'role_name' => $role->name,
                'permissions' => $validated['permissions'],
            ], $user);

            return $role;
        });

        return redirect()->route('admin.roles.index')
            ->with('success', "Role '{$role->name}' created successfully.");
    }

    /**
     * Show the form for editing an existing role.
     */
    public function edit(Request $request, Role $role): Response
    {
        $role->load('permissions');
        $permissions = Permission::all()->groupBy('module');
        $catalog = $this->registrar->getCatalog();

        $user = $request->user();
        $delegatableSlugs = $user->isSuperAdmin() || $user->hasRole('business-owner')
            ? Permission::pluck('slug')->all()
            : $user->getPermissions();

        return Inertia::render('Admin/Roles/Form', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
                'description' => $role->description,
                'is_system' => $role->is_system,
                'is_active' => $role->is_active,
                'permission_slugs' => $role->permissions->pluck('slug')->all(),
            ],
            'groupedPermissions' => $permissions,
            'catalog' => $catalog,
            'delegatableSlugs' => $delegatableSlugs,
        ]);
    }

    /**
     * Update an existing role and its permissions.
     */
    public function update(Request $request, Role $role): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'permissions' => ['required', 'array'],
            'permissions.*' => ['string', 'exists:permissions,slug'],
        ]);

        $user = $request->user();

        // Delegation security check: cannot grant permissions actor does not possess
        if (! $this->registrar->canDelegate($user, $validated['permissions'])) {
            AuditLog::record('unauthorized_permission_delegation_attempt', [
                'role_id' => $role->id,
                'role_slug' => $role->slug,
                'attempted_permissions' => $validated['permissions'],
            ], $user);

            throw ValidationException::withMessages([
                'permissions' => 'Privilege escalation rejected: You cannot assign permissions you do not personally hold.',
            ]);
        }

        DB::transaction(function () use ($role, $validated, $user) {
            $role->update([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
            ]);

            $permissionIds = Permission::whereIn('slug', $validated['permissions'])->pluck('id');
            $role->permissions()->sync($permissionIds);

            AuditLog::record('role_updated', [
                'role_id' => $role->id,
                'role_slug' => $role->slug,
                'permissions' => $validated['permissions'],
            ], $user);
        });

        return redirect()->route('admin.roles.index')
            ->with('success', "Role '{$role->name}' updated successfully.");
    }

    /**
     * Duplicate a role to create a new role from an existing template.
     */
    public function duplicate(Request $request, Role $role): RedirectResponse
    {
        $user = $request->user();
        $permissions = $role->permissions()->pluck('slug')->all();

        // Delegation check on duplicated permissions
        if (! $this->registrar->canDelegate($user, $permissions)) {
            throw ValidationException::withMessages([
                'role' => 'You cannot duplicate this role because it contains permissions you cannot delegate.',
            ]);
        }

        $baseName = $role->name.' (Copy)';
        $slug = Str::slug($baseName).'-'.Str::lower(Str::random(4));

        $newRole = DB::transaction(function () use ($role, $baseName, $slug, $user) {
            $new = Role::create([
                'name' => $baseName,
                'slug' => $slug,
                'description' => "Duplicated from {$role->name}",
                'is_system' => false,
                'is_active' => true,
            ]);

            $new->permissions()->sync($role->permissions()->pluck('id'));

            AuditLog::record('role_duplicated', [
                'source_role_id' => $role->id,
                'new_role_id' => $new->id,
                'new_role_slug' => $new->slug,
            ], $user);

            return $new;
        });

        return redirect()->route('admin.roles.edit', $newRole)
            ->with('success', "Role duplicated as '{$newRole->name}'. You may now customize its permissions.");
    }

    /**
     * Toggle active status of a custom role.
     */
    public function toggleActive(Request $request, Role $role): RedirectResponse
    {
        // Protection against modifying system roles
        if ($role->isSystem()) {
            return back()->with('error', 'System roles cannot be deactivated.');
        }

        $role->update([
            'is_active' => ! $role->is_active,
        ]);

        AuditLog::record('role_status_toggled', [
            'role_id' => $role->id,
            'role_slug' => $role->slug,
            'is_active' => $role->is_active,
        ], $request->user());

        $status = $role->is_active ? 'activated' : 'deactivated';

        return back()->with('success', "Role '{$role->name}' {$status}.");
    }

    /**
     * Delete a custom role.
     */
    public function destroy(Request $request, Role $role): RedirectResponse
    {
        // Protection against deleting system roles
        if ($role->isSystem()) {
            return back()->with('error', 'System roles cannot be deleted.');
        }

        // Protection against deleting roles with active user assignments
        if ($role->branchUserRoles()->exists()) {
            return back()->with('error', 'Cannot delete this role because it is currently assigned to users. Reassign them first.');
        }

        DB::transaction(function () use ($role, $request) {
            $roleData = [
                'role_id' => $role->id,
                'role_slug' => $role->slug,
                'role_name' => $role->name,
            ];

            $role->permissions()->detach();
            $role->delete();

            AuditLog::record('role_deleted', $roleData, $request->user());
        });

        return redirect()->route('admin.roles.index')
            ->with('success', "Role '{$role->name}' deleted successfully.");
    }
}
