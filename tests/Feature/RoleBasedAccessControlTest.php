<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleBasedAccessControlTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $mainBranch;

    protected Branch $secondBranch;

    protected Role $businessOwnerRole;

    protected Role $branchManagerRole;

    protected Role $cashierRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);

        $this->mainBranch = Branch::where('code', 'MAIN')->firstOrFail();
        $this->secondBranch = Branch::create([
            'name' => 'Uptown Store',
            'code' => 'UPTOWN',
            'is_active' => true,
        ]);

        $this->businessOwnerRole = Role::where('slug', 'business-owner')->firstOrFail();
        $this->branchManagerRole = Role::where('slug', 'branch-manager')->firstOrFail();
        $this->cashierRole = Role::where('slug', 'cashier')->firstOrFail();
    }

    /**
     * Test 1: Unauthorized access to protected backend routes.
     */
    public function test_guest_is_redirected_from_protected_rbac_routes(): void
    {
        $response = $this->get('/admin/roles');
        $response->assertRedirect('/login');

        $response = $this->get("/branches/{$this->mainBranch->id}/catalog");
        $response->assertRedirect('/login');
    }

    /**
     * Test 2: Direct API/HTTP request by user lacking permission receives 403 Forbidden.
     */
    public function test_user_without_permission_receives_403_forbidden_on_protected_routes(): void
    {
        $cashier = User::factory()->create(['is_active' => true]);
        $cashier->assignRole($this->cashierRole, $this->mainBranch->id);

        // Cashier lacks 'roles.view'
        $response = $this->actingAs($cashier)->get('/admin/roles');
        $response->assertStatus(403);

        // Security event is logged in audit_logs
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $cashier->id,
            'event' => 'unauthorized_permission_access_attempt',
        ]);
    }

    /**
     * Test 3: Authorized user with role permission can access route.
     */
    public function test_authorized_user_can_access_protected_routes(): void
    {
        $owner = User::factory()->create(['is_active' => true]);
        $owner->assignRole($this->businessOwnerRole); // Global assignment

        $response = $this->actingAs($owner)->get('/admin/roles');
        $response->assertStatus(200);
    }

    /**
     * Test 4: Cross-branch access attempts are strictly rejected.
     */
    public function test_cross_branch_access_attempts_are_rejected(): void
    {
        $manager = User::factory()->create(['is_active' => true]);
        // Manager only assigned to Main Branch
        $manager->assignRole($this->branchManagerRole, $this->mainBranch->id);

        // Accessing catalog for Main Branch succeeds
        $response = $this->actingAs($manager)->get("/branches/{$this->mainBranch->id}/catalog");
        $response->assertStatus(200);
        $response->assertJson(['status' => 'authorized', 'branch_id' => $this->mainBranch->id]);

        // Attempting direct request to Uptown Branch fails with 403
        $crossBranchResponse = $this->actingAs($manager)->get("/branches/{$this->secondBranch->id}/catalog");
        $crossBranchResponse->assertStatus(403);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $manager->id,
            'event' => 'unauthorized_permission_access_attempt',
        ]);
    }

    /**
     * Test 5: Users can hold distinct roles across multiple branches.
     */
    public function test_user_can_hold_different_roles_in_different_branches(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        // Manager at Main Branch, Cashier at Uptown Branch
        $user->assignRole($this->branchManagerRole, $this->mainBranch->id);
        $user->assignRole($this->cashierRole, $this->secondBranch->id);

        // Manager has 'catalog.products.create' at Main Branch
        $this->assertTrue($user->hasPermission('catalog.products.create', $this->mainBranch->id));
        // But Cashier at Uptown Branch does NOT have 'catalog.products.create'
        $this->assertFalse($user->hasPermission('catalog.products.create', $this->secondBranch->id));
    }

    /**
     * Test 6: Attempted Super Admin privilege escalation via role creation or assignment.
     */
    public function test_business_role_cannot_grant_super_admin_capabilities(): void
    {
        $owner = User::factory()->create(['is_active' => true]);
        $owner->assignRole($this->businessOwnerRole);

        $superAdmin = User::factory()->create([
            'is_super_admin' => true,
            'is_active' => true,
        ]);

        // Attempt to assign a business role to a Super Admin fails
        $response = $this->actingAs($owner)->post('/admin/roles/assignments', [
            'user_id' => $superAdmin->id,
            'role_id' => $this->branchManagerRole->id,
            'branch_id' => $this->mainBranch->id,
        ]);

        $response->assertSessionHasErrors(['user_id']);
        $this->assertDatabaseMissing('branch_user_role', [
            'user_id' => $superAdmin->id,
            'role_id' => $this->branchManagerRole->id,
        ]);
    }

    /**
     * Test 7: Unauthorized permission delegation is prevented.
     */
    public function test_administrator_cannot_delegate_permissions_they_do_not_hold(): void
    {
        // Custom role with only role management permissions
        $subAdminRole = Role::create([
            'name' => 'Sub Admin',
            'slug' => 'sub-admin',
            'is_active' => true,
        ]);
        $subAdminRole->permissions()->sync(
            Permission::whereIn('slug', ['roles.view', 'roles.create', 'roles.edit'])->pluck('id')
        );

        $subAdmin = User::factory()->create(['is_active' => true]);
        $subAdmin->assignRole($subAdminRole);

        // SubAdmin attempts to create a role with 'sales.refunds.process' (which they lack)
        $response = $this->actingAs($subAdmin)->post('/admin/roles', [
            'name' => 'Malicious Role',
            'permissions' => ['roles.view', 'sales.refunds.process'],
        ]);

        $response->assertSessionHasErrors(['permissions']);
        $this->assertDatabaseMissing('roles', ['name' => 'Malicious Role']);

        // Escalation attempt is recorded in audit logs
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $subAdmin->id,
            'event' => 'unauthorized_permission_delegation_attempt',
        ]);
    }

    /**
     * Test 8: Role changes and cache invalidation.
     */
    public function test_role_permission_changes_immediately_invalidate_cache(): void
    {
        $customRole = Role::create([
            'name' => 'Inventory Assistant',
            'slug' => 'inventory-assistant',
            'is_active' => true,
        ]);
        $customRole->permissions()->sync(
            Permission::where('slug', 'inventory.stock.view')->pluck('id')
        );

        $staff = User::factory()->create(['is_active' => true]);
        $staff->assignRole($customRole, $this->mainBranch->id);

        $this->assertTrue($staff->hasPermission('inventory.stock.view', $this->mainBranch->id));
        $this->assertFalse($staff->hasPermission('inventory.stock.adjust', $this->mainBranch->id));

        // Owner updates role to grant 'inventory.stock.adjust'
        $owner = User::factory()->create(['is_active' => true]);
        $owner->assignRole($this->businessOwnerRole);

        $updateResponse = $this->actingAs($owner)->put("/admin/roles/{$customRole->id}", [
            'name' => 'Inventory Assistant',
            'permissions' => ['inventory.stock.view', 'inventory.stock.adjust'],
        ]);
        $updateResponse->assertRedirect('/admin/roles');

        // Without waiting or manual cache clearing, cache is invalid and staff now has new permission
        $this->assertTrue($staff->hasPermission('inventory.stock.adjust', $this->mainBranch->id));
    }

    /**
     * Test 9: Deactivation of roles that are still assigned.
     */
    public function test_deactivation_of_role_immediately_revokes_permissions_from_assigned_users(): void
    {
        $customRole = Role::create([
            'name' => 'Temporary Clerk',
            'slug' => 'temp-clerk',
            'is_active' => true,
        ]);
        $customRole->permissions()->sync(
            Permission::where('slug', 'catalog.products.view')->pluck('id')
        );

        $clerk = User::factory()->create(['is_active' => true]);
        $clerk->assignRole($customRole, $this->mainBranch->id);

        $this->assertTrue($clerk->hasPermission('catalog.products.view', $this->mainBranch->id));

        // Owner deactivates the role
        $owner = User::factory()->create(['is_active' => true]);
        $owner->assignRole($this->businessOwnerRole);

        $this->actingAs($owner)->post("/admin/roles/{$customRole->id}/toggle-active");

        $customRole->refresh();
        $this->assertFalse($customRole->is_active);

        // Clerk immediately loses access
        $this->assertFalse($clerk->hasPermission('catalog.products.view', $this->mainBranch->id));
    }

    /**
     * Test 10: Protection against deleting or deactivating system roles.
     */
    public function test_system_roles_cannot_be_deleted_or_deactivated(): void
    {
        $owner = User::factory()->create(['is_active' => true]);
        $owner->assignRole($this->businessOwnerRole);

        // Attempt to deactivate business-owner system role
        $deactivateResponse = $this->actingAs($owner)->post("/admin/roles/{$this->businessOwnerRole->id}/toggle-active");
        $deactivateResponse->assertSessionHas('error');
        $this->assertTrue($this->businessOwnerRole->fresh()->is_active);

        // Attempt to delete business-owner system role
        $deleteResponse = $this->actingAs($owner)->delete("/admin/roles/{$this->businessOwnerRole->id}");
        $deleteResponse->assertSessionHas('error');
        $this->assertDatabaseHas('roles', ['id' => $this->businessOwnerRole->id]);
    }

    /**
     * Test 11: Protection against removing the last active Business Owner.
     */
    public function test_cannot_remove_the_last_active_business_owner(): void
    {
        $soleOwner = User::factory()->create(['is_active' => true]);
        $assignment = $soleOwner->assignRole($this->businessOwnerRole);

        $response = $this->actingAs($soleOwner)->delete("/admin/roles/assignments/{$assignment->id}");
        $response->assertSessionHas('error', 'Cannot remove the last active Business Owner. Essential administrative access must be preserved.');

        // Assignment still exists
        $this->assertDatabaseHas('branch_user_role', ['id' => $assignment->id]);
    }

    /**
     * Test 12: Role duplication works with permission cloning.
     */
    public function test_role_can_be_duplicated(): void
    {
        $owner = User::factory()->create(['is_active' => true]);
        $owner->assignRole($this->businessOwnerRole);

        $response = $this->actingAs($owner)->post("/admin/roles/{$this->cashierRole->id}/duplicate");
        $response->assertStatus(302);

        $this->assertDatabaseHas('roles', [
            'name' => 'Cashier (Copy)',
            'is_system' => false,
        ]);

        $copy = Role::where('name', 'Cashier (Copy)')->firstOrFail();
        $this->assertEquals(
            $this->cashierRole->permissions()->pluck('id')->sort()->values()->all(),
            $copy->permissions()->pluck('id')->sort()->values()->all()
        );
    }
}
