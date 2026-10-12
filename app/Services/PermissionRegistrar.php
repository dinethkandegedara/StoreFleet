<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

class PermissionRegistrar
{
    /**
     * Cache version key to support atomic cache invalidation on file/database cache drivers.
     */
    protected const VERSION_KEY = 'storefleet_rbac_version';

    /**
     * Runtime registered module permission catalog.
     *
     * @var array<string, list<array{name: string, slug: string, action: string, description: string}>>
     */
    protected array $registeredPermissions = [];

    public function __construct()
    {
        $this->registerDefaultPermissions();
    }

    /**
     * Default permission catalog grouped by module.
     */
    protected function registerDefaultPermissions(): void
    {
        $this->registeredPermissions = [
            'roles' => [
                ['name' => 'View Roles', 'slug' => 'roles.view', 'action' => 'view', 'description' => 'View system and custom roles'],
                ['name' => 'Create Roles', 'slug' => 'roles.create', 'action' => 'create', 'description' => 'Create new custom roles'],
                ['name' => 'Edit Roles', 'slug' => 'roles.edit', 'action' => 'update', 'description' => 'Edit role definitions and assigned permissions'],
                ['name' => 'Delete Roles', 'slug' => 'roles.delete', 'action' => 'delete', 'description' => 'Delete custom roles'],
                ['name' => 'Assign Roles', 'slug' => 'roles.assign', 'action' => 'manage', 'description' => 'Assign roles to users within branch scope'],
            ],
            'branches' => [
                ['name' => 'View Branches', 'slug' => 'branches.view', 'action' => 'view', 'description' => 'View business branches and status'],
                ['name' => 'Create Branches', 'slug' => 'branches.create', 'action' => 'create', 'description' => 'Create new branch locations'],
                ['name' => 'Edit Branches', 'slug' => 'branches.edit', 'action' => 'update', 'description' => 'Update branch configuration and operating details'],
                ['name' => 'Manage Branches', 'slug' => 'branches.manage', 'action' => 'manage', 'description' => 'Deactivate or reconfigure branches'],
            ],
            'users' => [
                ['name' => 'View Users', 'slug' => 'users.view', 'action' => 'view', 'description' => 'View operational users and branch assignments'],
                ['name' => 'Create Users', 'slug' => 'users.create', 'action' => 'create', 'description' => 'Add new operational users'],
                ['name' => 'Edit Users', 'slug' => 'users.edit', 'action' => 'update', 'description' => 'Update operational user details and status'],
                ['name' => 'Deactivate Users', 'slug' => 'users.deactivate', 'action' => 'delete', 'description' => 'Suspend or deactivate user accounts'],
            ],
            'catalog' => [
                ['name' => 'View Products', 'slug' => 'catalog.products.view', 'action' => 'view', 'description' => 'Browse products and inventory items'],
                ['name' => 'Create Products', 'slug' => 'catalog.products.create', 'action' => 'create', 'description' => 'Add new products and variants'],
                ['name' => 'Edit Products', 'slug' => 'catalog.products.edit', 'action' => 'update', 'description' => 'Modify prices, barcodes, and product details'],
                ['name' => 'Delete Products', 'slug' => 'catalog.products.delete', 'action' => 'delete', 'description' => 'Remove products from the catalog'],
                ['name' => 'Manage Categories', 'slug' => 'catalog.categories.manage', 'action' => 'manage', 'description' => 'Configure categories and taxonomy'],
            ],
            'pos' => [
                ['name' => 'Access POS Terminal', 'slug' => 'pos.terminal.access', 'action' => 'view', 'description' => 'Launch register terminal interface'],
                ['name' => 'Process Checkout', 'slug' => 'pos.orders.checkout', 'action' => 'create', 'description' => 'Process transactions and record customer payments'],
                ['name' => 'Apply Discounts', 'slug' => 'pos.discounts.apply', 'action' => 'manage', 'description' => 'Apply custom line-item or basket discounts'],
                ['name' => 'Void Transactions', 'slug' => 'pos.orders.void', 'action' => 'approve', 'description' => 'Void or cancel active cart orders'],
            ],
            'sales' => [
                ['name' => 'View Sales History', 'slug' => 'sales.orders.view', 'action' => 'view', 'description' => 'View orders and branch transaction history'],
                ['name' => 'Process Refunds', 'slug' => 'sales.refunds.process', 'action' => 'approve', 'description' => 'Approve and issue payment refunds'],
                ['name' => 'View Reports', 'slug' => 'sales.reports.view', 'action' => 'view', 'description' => 'View sales analytics and revenue summaries'],
            ],
            'inventory' => [
                ['name' => 'View Stock Levels', 'slug' => 'inventory.stock.view', 'action' => 'view', 'description' => 'Inspect on-hand stock per branch'],
                ['name' => 'Adjust Inventory', 'slug' => 'inventory.stock.adjust', 'action' => 'update', 'description' => 'Perform cycle counts and stock level adjustments'],
                ['name' => 'Manage Transfers', 'slug' => 'inventory.transfers.manage', 'action' => 'manage', 'description' => 'Initiate and receive branch stock transfers'],
            ],
            'settings' => [
                ['name' => 'View Settings', 'slug' => 'settings.view', 'action' => 'view', 'description' => 'View business configuration and branding'],
                ['name' => 'Edit Settings', 'slug' => 'settings.edit', 'action' => 'update', 'description' => 'Update business configuration, currency, tax, and receipts'],
            ],
        ];
    }

    /**
     * Register permissions from future modules without altering core RBAC files.
     *
     * @param  list<array{name: string, slug: string, action: string, description: string}>  $permissions
     */
    public function registerModulePermissions(string $module, array $permissions): void
    {
        $this->registeredPermissions[$module] = array_merge(
            $this->registeredPermissions[$module] ?? [],
            $permissions
        );
    }

    /**
     * Retrieve complete permission catalog.
     *
     * @return array<string, list<array{name: string, slug: string, action: string, description: string}>>
     */
    public function getCatalog(): array
    {
        return $this->registeredPermissions;
    }

    /**
     * Synchronize in-memory catalog with database permissions table.
     */
    public function syncCatalogToDatabase(): void
    {
        foreach ($this->registeredPermissions as $module => $permissions) {
            foreach ($permissions as $p) {
                Permission::updateOrCreate(
                    ['slug' => $p['slug']],
                    [
                        'name' => $p['name'],
                        'module' => $module,
                        'action' => $p['action'],
                        'description' => $p['description'],
                    ]
                );
            }
        }
    }

    /**
     * Get the active RBAC cache generation version.
     */
    public function getCacheVersion(): int
    {
        return (int) Cache::get(self::VERSION_KEY, 1);
    }

    /**
     * Invalidate all RBAC permission caches globally.
     */
    public function clearPermissionCache(): void
    {
        Cache::increment(self::VERSION_KEY);
    }

    /**
     * Clear cached permissions for a specific user.
     */
    public function clearUserPermissionCache(int $userId): void
    {
        // Increment global version ensures instant invalidation across all branch combinations
        $this->clearPermissionCache();
    }

    /**
     * Get effective permission slugs for a user within a branch context.
     *
     * @return list<string>
     */
    public function getUserPermissions(User $user, ?int $branchId = null): array
    {
        if (! $user->isActive()) {
            return [];
        }

        $version = $this->getCacheVersion();
        $branchKey = $branchId ? (string) $branchId : 'global';
        $cacheKey = "user_perms_v{$version}_{$user->id}_{$branchKey}";

        return Cache::remember($cacheKey, 3600, function () use ($user, $branchId) {
            // Retrieve active roles assigned either globally (branch_id IS NULL) or to the target branch
            $query = $user->branchUserRoles()
                ->whereHas('role', function ($q) {
                    $q->where('is_active', true);
                })
                ->where(function ($q) use ($branchId) {
                    $q->whereNull('branch_id');
                    if ($branchId !== null) {
                        $q->orWhere('branch_id', $branchId);
                    }
                })
                ->with(['role.permissions']);

            $permissions = [];
            foreach ($query->get() as $assignment) {
                if ($assignment->role && $assignment->role->isActive()) {
                    foreach ($assignment->role->permissions as $perm) {
                        $permissions[$perm->slug] = true;
                    }
                }
            }

            return array_keys($permissions);
        });
    }

    /**
     * Check if user holds a specific permission within branch context.
     */
    public function userHasPermission(User $user, string $permissionSlug, ?int $branchId = null): bool
    {
        if (! $user->isActive()) {
            return false;
        }

        // Super Admin has platform-level authority
        if ($user->isSuperAdmin()) {
            return true;
        }

        $permissions = $this->getUserPermissions($user, $branchId);

        return in_array($permissionSlug, $permissions, true);
    }

    /**
     * Check if user holds a specific role within branch context.
     */
    public function userHasRole(User $user, string $roleSlug, ?int $branchId = null): bool
    {
        if (! $user->isActive()) {
            return false;
        }

        return $user->branchUserRoles()
            ->whereHas('role', function ($q) use ($roleSlug) {
                $q->where('slug', $roleSlug)->where('is_active', true);
            })
            ->where(function ($q) use ($branchId) {
                $q->whereNull('branch_id');
                if ($branchId !== null) {
                    $q->orWhere('branch_id', $branchId);
                }
            })
            ->exists();
    }

    /**
     * Verify if the acting user can delegate the specified permissions.
     * Prevents privilege escalation: an administrator can only grant permissions they personally hold.
     *
     * @param  list<string>  $permissionSlugs
     */
    public function canDelegate(User $actor, array $permissionSlugs, ?int $branchId = null): bool
    {
        if ($actor->isSuperAdmin()) {
            return true;
        }

        // If actor has Business Owner role globally, they can delegate any business permission
        if ($this->userHasRole($actor, 'business-owner', null)) {
            return true;
        }

        $actorPermissions = $this->getUserPermissions($actor, $branchId);

        foreach ($permissionSlugs as $slug) {
            if (! in_array($slug, $actorPermissions, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Register Laravel Gate callbacks.
     */
    public function registerGates(): void
    {
        Gate::before(function (User $user, string $ability, array $arguments = []) {
            // Super Admin passes all gates
            if ($user->isSuperAdmin()) {
                return true;
            }

            // Extract branchId from arguments if provided
            $branchId = null;
            if (isset($arguments[0]) && is_numeric($arguments[0])) {
                $branchId = (int) $arguments[0];
            }

            // Check if ability exists in registered permissions
            if ($this->isRegisteredPermission($ability)) {
                return $this->userHasPermission($user, $ability, $branchId);
            }

            return null; // Fall through to standard policies
        });
    }

    /**
     * Check if an ability slug is part of the registered catalog.
     */
    public function isRegisteredPermission(string $slug): bool
    {
        foreach ($this->registeredPermissions as $perms) {
            foreach ($perms as $p) {
                if ($p['slug'] === $slug) {
                    return true;
                }
            }
        }

        return false;
    }
}
