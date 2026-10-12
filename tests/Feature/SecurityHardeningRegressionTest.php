<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\BranchUserRole;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SecurityHardeningRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    public function test_password_reset_requests_are_throttled_after_five_attempts(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $user = User::factory()->create([
                'email' => "staff{$i}@branchwise.local",
            ]);

            $response = $this->post('/forgot-password', [
                'email' => "staff{$i}@branchwise.local",
            ]);
            $response->assertSessionHas('status');
        }

        // 6th attempt from same IP should hit our RateLimiter throttle
        $anotherUser = User::factory()->create([
            'email' => 'staff6@branchwise.local',
        ]);

        $response = $this->post('/forgot-password', [
            'email' => 'staff6@branchwise.local',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'auth.password_reset_throttled',
        ]);
    }

    public function test_user_creation_rejects_privilege_escalation_when_actor_lacks_delegated_permissions(): void
    {
        $branch = Branch::first();

        // Limited manager who only has users.create and users.view
        $limitedRole = Role::create([
            'name' => 'Limited Staff Admin',
            'slug' => 'limited-staff-admin',
            'is_system' => false,
            'is_active' => true,
        ]);
        $limitedRole->permissions()->attach(
            Permission::whereIn('slug', ['users.view', 'users.create'])->pluck('id')
        );

        $manager = User::factory()->create(['is_active' => true]);
        BranchUserRole::create([
            'user_id' => $manager->id,
            'role_id' => $limitedRole->id,
            'branch_id' => $branch->id,
        ]);

        // Target role has permissions the manager does not possess (e.g. settings.edit)
        $powerfulRole = Role::create([
            'name' => 'High Privilege Role',
            'slug' => 'high-privilege-role',
            'is_system' => false,
            'is_active' => true,
        ]);
        $powerfulRole->permissions()->attach(
            Permission::whereIn('slug', ['users.view', 'settings.edit'])->pluck('id')
        );

        $response = $this->actingAs($manager)->post('/admin/users', [
            'name' => 'Escalated Employee',
            'email' => 'escalated@example.com',
            'password' => 'SecurePass123!@#',
            'password_confirmation' => 'SecurePass123!@#',
            'is_active' => true,
            'assignments' => [
                [
                    'branch_id' => $branch->id,
                    'role_id' => $powerfulRole->id,
                ],
            ],
        ]);

        $response->assertSessionHasErrors('assignments.0.role_id');
        $this->assertDatabaseMissing('users', [
            'email' => 'escalated@example.com',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'unauthorized_role_delegation_attempt',
        ]);
    }

    public function test_user_update_rejects_privilege_escalation_when_actor_lacks_delegated_permissions(): void
    {
        $branch = Branch::first();

        $limitedRole = Role::create([
            'name' => 'Staff Editor',
            'slug' => 'staff-editor',
            'is_system' => false,
            'is_active' => true,
        ]);
        $limitedRole->permissions()->attach(
            Permission::whereIn('slug', ['users.view', 'users.edit'])->pluck('id')
        );

        $manager = User::factory()->create(['is_active' => true]);
        BranchUserRole::create([
            'user_id' => $manager->id,
            'role_id' => $limitedRole->id,
            'branch_id' => $branch->id,
        ]);

        $targetUser = User::factory()->create(['is_active' => true]);
        $ownerRole = Role::where('slug', 'business-owner')->firstOrFail();

        // Attempt to grant targetUser the Business Owner role
        $response = $this->actingAs($manager)->put("/admin/users/{$targetUser->id}", [
            'name' => $targetUser->name,
            'email' => $targetUser->email,
            'is_active' => true,
            'assignments' => [
                [
                    'branch_id' => null,
                    'role_id' => $ownerRole->id,
                ],
            ],
        ]);

        $response->assertSessionHasErrors('assignments.0.role_id');
        $this->assertDatabaseMissing('branch_user_role', [
            'user_id' => $targetUser->id,
            'role_id' => $ownerRole->id,
        ]);
    }

    public function test_cannot_assign_branch_roles_to_platform_super_admin(): void
    {
        $superAdmin = User::factory()->create([
            'is_super_admin' => true,
            'is_active' => true,
        ]);

        $role = Role::where('slug', 'cashier')->firstOrFail();

        $response = $this->actingAs($superAdmin)->put("/admin/users/{$superAdmin->id}", [
            'name' => 'Super Admin',
            'email' => $superAdmin->email,
            'is_active' => true,
            'assignments' => [
                [
                    'branch_id' => null,
                    'role_id' => $role->id,
                ],
            ],
        ]);

        $response->assertSessionHasErrors('assignments');
    }

    public function test_cannot_deactivate_or_strip_role_from_last_active_business_owner(): void
    {
        $owner = User::factory()->create(['is_active' => true]);
        $ownerRole = Role::where('slug', 'business-owner')->firstOrFail();
        BranchUserRole::create([
            'user_id' => $owner->id,
            'role_id' => $ownerRole->id,
            'branch_id' => null,
        ]);

        $cashierRole = Role::where('slug', 'cashier')->firstOrFail();

        // 1. Attempt to strip business owner role via update
        $response = $this->actingAs($owner)->put("/admin/users/{$owner->id}", [
            'name' => $owner->name,
            'email' => $owner->email,
            'is_active' => true,
            'assignments' => [
                [
                    'branch_id' => null,
                    'role_id' => $cashierRole->id,
                ],
            ],
        ]);

        $response->assertSessionHasErrors('is_active');

        // 2. Attempt to deactivate via toggle endpoint as super admin
        $superAdmin = User::factory()->create(['is_super_admin' => true, 'is_active' => true]);
        $toggleResponse = $this->actingAs($superAdmin)->post("/admin/users/{$owner->id}/toggle-active");
        $toggleResponse->assertSessionHas('error');
        $this->assertTrue($owner->fresh()->is_active);
    }

    public function test_pos_checkout_rejects_disabled_payment_method(): void
    {
        $branch = Branch::first();
        $user = User::factory()->create(['is_active' => true]);
        $cashierRole = Role::where('slug', 'cashier')->firstOrFail();
        BranchUserRole::create([
            'user_id' => $user->id,
            'role_id' => $cashierRole->id,
            'branch_id' => $branch->id,
        ]);

        // Explicitly disable card payments in settings
        Setting::set('payment_card_enabled', false, 'business', null);

        $product = Product::create([
            'name' => 'Sample POS Item',
            'sku' => 'SAMPLE-001',
            'selling_price' => 20.00,
            'is_active' => true,
            'is_taxable' => false,
        ]);

        $payload = [
            'branch_id' => $branch->id,
            'idempotency_key' => (string) Str::uuid(),
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                ],
            ],
            'payments' => [
                [
                    'payment_method' => 'card',
                    'amount' => 20.00,
                    'reference_note' => 'AUTH-1234',
                ],
            ],
        ];

        $response = $this->actingAs($user)->postJson('/pos/checkout', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('payments.0.payment_method');
    }

    public function test_audit_log_redacts_sensitive_keys_from_payload(): void
    {
        $log = AuditLog::log('test.event', null, [
            'username' => 'testuser',
            'password' => 'plain_secret_password',
            'two_factor_secret' => 'SECRETKEY123',
            'two_factor_recovery_codes' => ['code1', 'code2'],
            'token' => 'bearer_token_abc',
            'remember_token' => 'remember_token_xyz',
            'secret' => 'api_secret_hash',
            'safe_param' => 'visible_value',
        ]);

        $this->assertArrayNotHasKey('password', $log->payload);
        $this->assertArrayNotHasKey('two_factor_secret', $log->payload);
        $this->assertArrayNotHasKey('two_factor_recovery_codes', $log->payload);
        $this->assertArrayNotHasKey('token', $log->payload);
        $this->assertArrayNotHasKey('remember_token', $log->payload);
        $this->assertArrayNotHasKey('secret', $log->payload);
        $this->assertEquals('visible_value', $log->payload['safe_param']);
    }

    public function test_user_model_mass_assignment_does_not_fill_2fa_secrets(): void
    {
        $user = new User;
        $user->fill([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'two_factor_secret' => 'injected_secret',
            'two_factor_recovery_codes' => ['injected_code'],
        ]);

        $this->assertNull($user->two_factor_secret);
        $this->assertNull($user->two_factor_recovery_codes);
    }
}
