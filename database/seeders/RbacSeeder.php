<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Permission;
use App\Models\Role;
use App\Services\PermissionRegistrar;
use Illuminate\Database\Seeder;

class RbacSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Create Default Branch
        $mainBranch = Branch::firstOrCreate(
            ['code' => 'MAIN'],
            [
                'name' => 'Main Store',
                'address' => 'Flagship Location, 100 Main Street',
                'phone' => '+1 (555) 010-0100',
                'is_active' => true,
            ]
        );

        // 2. Synchronize Permission Catalog
        $registrar = app(PermissionRegistrar::class);
        $registrar->syncCatalogToDatabase();

        $allPermissions = Permission::all();

        // 3. Create / Sync System Roles

        // Business Owner: All permissions
        $businessOwner = Role::updateOrCreate(
            ['slug' => 'business-owner'],
            [
                'name' => 'Business Owner',
                'description' => 'Full administrative control over business settings, roles, branches, and all operations',
                'is_system' => true,
                'is_active' => true,
            ]
        );
        $businessOwner->permissions()->sync($allPermissions->pluck('id'));

        // Branch Manager: Operations, Catalog, POS, Sales, Stock
        $branchManager = Role::updateOrCreate(
            ['slug' => 'branch-manager'],
            [
                'name' => 'Branch Manager',
                'description' => 'Supervises branch transactions, stock adjustments, pricing, and operational staff',
                'is_system' => true,
                'is_active' => true,
            ]
        );
        $managerPermissions = Permission::whereIn('slug', [
            'branches.view',
            'users.view',
            'catalog.products.view',
            'catalog.products.create',
            'catalog.products.edit',
            'catalog.categories.manage',
            'pos.terminal.access',
            'pos.orders.checkout',
            'pos.discounts.apply',
            'pos.orders.void',
            'sales.orders.view',
            'sales.refunds.process',
            'sales.reports.view',
            'inventory.stock.view',
            'inventory.stock.adjust',
            'inventory.transfers.manage',
        ])->pluck('id');
        $branchManager->permissions()->sync($managerPermissions);

        // Cashier: Register checkout, view catalog & orders
        $cashier = Role::updateOrCreate(
            ['slug' => 'cashier'],
            [
                'name' => 'Cashier',
                'description' => 'Front-of-house register operations, taking payments, and scanning items',
                'is_system' => true,
                'is_active' => true,
            ]
        );
        $cashierPermissions = Permission::whereIn('slug', [
            'pos.terminal.access',
            'pos.orders.checkout',
            'catalog.products.view',
            'sales.orders.view',
        ])->pluck('id');
        $cashier->permissions()->sync($cashierPermissions);

        $registrar->clearPermissionCache();
    }
}
