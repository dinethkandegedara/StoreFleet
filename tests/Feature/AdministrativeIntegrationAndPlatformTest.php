<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\User;
use App\Services\BusinessSettingService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdministrativeIntegrationAndPlatformTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $mainBranch;

    protected Role $businessOwnerRole;

    protected Role $managerRole;

    protected Role $cashierRole;

    protected User $superAdmin;

    protected User $owner;

    protected User $manager;

    protected User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);

        $this->mainBranch = Branch::where('code', 'MAIN')->firstOrFail();
        $this->businessOwnerRole = Role::where('slug', 'business-owner')->firstOrFail();
        $this->managerRole = Role::where('slug', 'branch-manager')->firstOrFail();
        $this->cashierRole = Role::where('slug', 'cashier')->firstOrFail();

        // 1. Super Admin
        $this->superAdmin = User::factory()->create([
            'name' => 'Operator',
            'email' => 'operator@storefleet.test',
            'is_super_admin' => true,
            'is_active' => true,
        ]);

        // 2. Business Owner
        $this->owner = User::factory()->create([
            'name' => 'Owner',
            'email' => 'owner@storefleet.test',
            'is_active' => true,
        ]);
        $this->owner->assignRole($this->businessOwnerRole, null);

        // 3. Manager
        $this->manager = User::factory()->create([
            'name' => 'Manager',
            'email' => 'manager@storefleet.test',
            'is_active' => true,
        ]);
        $this->manager->assignRole($this->managerRole, $this->mainBranch->id);

        // 4. Cashier
        $this->cashier = User::factory()->create([
            'name' => 'Cashier',
            'email' => 'cashier@storefleet.test',
            'is_active' => true,
        ]);
        $this->cashier->assignRole($this->cashierRole, $this->mainBranch->id);
    }

    public function test_guest_is_redirected_from_staff_and_platform_routes(): void
    {
        $this->get('/admin/users')->assertRedirect('/login');
        $this->get('/admin/users/create')->assertRedirect('/login');
        $this->get('/admin/platform')->assertRedirect('/login');
        $this->post('/admin/platform/clear-cache')->assertRedirect('/login');
    }

    public function test_unauthorized_user_cannot_access_staff_accounts(): void
    {
        // Cashier lacks users.view permission
        $this->actingAs($this->cashier)
            ->get('/admin/users')
            ->assertForbidden();
    }

    public function test_authorized_user_can_view_staff_listing_and_filter(): void
    {
        $response = $this->actingAs($this->owner)->get('/admin/users');
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Users/Index')
            ->has('users.data')
            ->has('branches')
            ->has('roles')
        );

        // Filter by search name
        $searchRes = $this->actingAs($this->owner)->get('/admin/users?search=Manager');
        $searchRes->assertOk();
        $this->assertEquals(1, count($searchRes->inertiaProps('users')['data']));
        $this->assertEquals('Manager', $searchRes->inertiaProps('users')['data'][0]['name']);
    }

    public function test_authorized_user_can_create_staff_account(): void
    {
        $payload = [
            'name' => 'Sammy Staff',
            'email' => 'sammy@storefleet.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'is_active' => true,
            'assignments' => [
                [
                    'branch_id' => $this->mainBranch->id,
                    'role_id' => $this->cashierRole->id,
                ],
            ],
        ];

        $response = $this->actingAs($this->owner)->post('/admin/users', $payload);
        $response->assertRedirect('/admin/users');
        $response->assertSessionHas('success');

        $user = User::where('email', 'sammy@storefleet.test')->first();
        $this->assertNotNull($user);
        $this->assertEquals('Sammy Staff', $user->name);
        $this->assertTrue(Hash::check('Password123!', $user->password));
        $this->assertTrue($user->is_active);
        $this->assertFalse($user->is_super_admin); // Privilege escalation prevented

        $this->assertDatabaseHas('branch_user_role', [
            'user_id' => $user->id,
            'branch_id' => $this->mainBranch->id,
            'role_id' => $this->cashierRole->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'user.created',
            'user_id' => $this->owner->id,
        ]);
    }

    public function test_staff_creation_cannot_escalate_super_admin_status(): void
    {
        $payload = [
            'name' => 'Attempted Escalator',
            'email' => 'escalate@storefleet.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'is_active' => true,
            'is_super_admin' => true, // Attempted payload tampering
        ];

        $this->actingAs($this->owner)->post('/admin/users', $payload);

        $user = User::where('email', 'escalate@storefleet.test')->first();
        $this->assertNotNull($user);
        $this->assertFalse((bool) $user->is_super_admin);
    }

    public function test_authorized_user_can_update_staff_account(): void
    {
        $staff = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'old@storefleet.test',
            'is_active' => true,
        ]);

        $updatePayload = [
            'name' => 'Updated Name',
            'email' => 'updated@storefleet.test',
            'is_active' => true,
            'assignments' => [
                [
                    'branch_id' => $this->mainBranch->id,
                    'role_id' => $this->managerRole->id,
                ],
            ],
        ];

        $response = $this->actingAs($this->owner)->put("/admin/users/{$staff->id}", $updatePayload);
        $response->assertRedirect('/admin/users');

        $staff->refresh();
        $this->assertEquals('Updated Name', $staff->name);
        $this->assertEquals('updated@storefleet.test', $staff->email);

        $this->assertDatabaseHas('branch_user_role', [
            'user_id' => $staff->id,
            'branch_id' => $this->mainBranch->id,
            'role_id' => $this->managerRole->id,
        ]);
    }

    public function test_non_super_admin_cannot_edit_or_deactivate_super_admin(): void
    {
        $this->actingAs($this->owner)
            ->get("/admin/users/{$this->superAdmin->id}/edit")
            ->assertForbidden();

        $this->actingAs($this->owner)
            ->put("/admin/users/{$this->superAdmin->id}", [
                'name' => 'Tampered Name',
                'email' => $this->superAdmin->email,
                'is_active' => true,
            ])
            ->assertForbidden();

        $this->actingAs($this->owner)
            ->post("/admin/users/{$this->superAdmin->id}/toggle-active")
            ->assertForbidden();
    }

    public function test_user_cannot_deactivate_own_account(): void
    {
        $response = $this->actingAs($this->owner)
            ->post("/admin/users/{$this->owner->id}/toggle-active");

        $response->assertSessionHas('error');
        $this->owner->refresh();
        $this->assertTrue($this->owner->is_active);
    }

    public function test_authorized_user_can_toggle_staff_active_status(): void
    {
        $staff = User::factory()->create(['is_active' => true]);

        $response = $this->actingAs($this->owner)
            ->post("/admin/users/{$staff->id}/toggle-active");

        $response->assertSessionHas('success');
        $staff->refresh();
        $this->assertFalse($staff->is_active);

        // Toggle back to active
        $this->actingAs($this->owner)
            ->post("/admin/users/{$staff->id}/toggle-active");

        $staff->refresh();
        $this->assertTrue($staff->is_active);
    }

    public function test_non_super_admin_cannot_access_platform_overview(): void
    {
        $this->actingAs($this->owner)
            ->get('/admin/platform')
            ->assertForbidden();

        $this->actingAs($this->manager)
            ->get('/admin/platform')
            ->assertForbidden();

        $this->actingAs($this->owner)
            ->post('/admin/platform/clear-cache')
            ->assertForbidden();
    }

    public function test_super_admin_can_access_platform_overview_and_clear_cache(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->get('/admin/platform');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Platform/Index')
            ->has('system')
            ->has('storage_health')
            ->has('extensions')
            ->has('super_admins')
            ->has('stats')
        );

        // Clear framework cache
        $clearRes = $this->actingAs($this->superAdmin)
            ->post('/admin/platform/clear-cache');

        $clearRes->assertSessionHas('success');
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'platform.cache_cleared',
            'user_id' => $this->superAdmin->id,
        ]);
    }

    public function test_business_payment_configuration_persists_and_reflects_in_pos(): void
    {
        // 1. Update business payment settings
        $settingsPayload = [
            'business_name' => 'StoreFleet Global',
            'primary_color' => '#059669',
            'currency_code' => 'USD',
            'currency_symbol' => '$',
            'currency_precision' => 2,
            'currency_symbol_position' => 'before',
            'decimal_separator' => '.',
            'timezone' => 'America/New_York',
            'date_format' => 'Y-m-d',
            'time_format' => 'H:i',
            'tax_enabled' => true,
            'default_tax_rate' => 10.0,
            'prices_include_tax' => false,
            'receipt_show_logo' => true,
            'payment_cash_enabled' => true,
            'payment_card_enabled' => false, // Disable cards
            'payment_bank_transfer_enabled' => true,
            'payment_other_enabled' => false,
        ];

        $resSettings = $this->actingAs($this->owner)->put('/admin/settings', $settingsPayload);
        $resSettings->assertSessionHas('success');

        $service = app(BusinessSettingService::class);
        $this->assertFalse((bool) $service->get('payment_card_enabled'));
        $this->assertTrue((bool) $service->get('payment_cash_enabled'));

        // 2. Access POS terminal and verify settings
        $resTerminal = $this->actingAs($this->manager)->get('/pos');
        $resTerminal->assertOk();
        $resTerminal->assertInertia(fn ($page) => $page
            ->component('Pos/Terminal')
            ->where('settings.payment_card_enabled', false)
            ->where('settings.payment_cash_enabled', true)
        );
    }

    public function test_branding_changes_do_not_alter_historical_snapshots(): void
    {
        // Create an existing historical sale
        $sale = Sale::create([
            'uuid' => (string) Str::uuid(),
            'sale_number' => 'POS-SNAP-999',
            'branch_id' => $this->mainBranch->id,
            'user_id' => $this->cashier->id,
            'customer_name' => 'Historical Customer',
            'status' => 'completed',
            'subtotal' => 100.00,
            'discount_total' => 0.00,
            'tax_total' => 10.00,
            'total_amount' => 110.00,
            'payment_status' => 'paid',
            'idempotency_key' => (string) Str::uuid(),
            'completed_at' => now()->subMonths(1),
        ]);

        SaleItem::create([
            'uuid' => (string) Str::uuid(),
            'sale_id' => $sale->id,
            'product_name' => 'Original Historical Item',
            'sku' => 'HIST-001',
            'quantity' => 1,
            'unit_price' => 100.00,
            'is_taxable' => true,
            'tax_rate' => 10.00,
            'tax_amount' => 10.00,
            'subtotal' => 100.00,
            'total' => 110.00,
        ]);

        SalePayment::create([
            'uuid' => (string) Str::uuid(),
            'sale_id' => $sale->id,
            'payment_method' => 'cash',
            'amount' => 110.00,
            'tendered_amount' => 110.00,
            'change_amount' => 0.00,
            'status' => 'completed',
        ]);

        // Now modify business settings (change currency, business name, tax rates)
        $this->actingAs($this->owner)->put('/admin/settings', [
            'business_name' => 'Completely New Business Brand Name',
            'primary_color' => '#7c3aed',
            'currency_code' => 'GBP',
            'currency_symbol' => '£',
            'currency_precision' => 2,
            'currency_symbol_position' => 'before',
            'decimal_separator' => '.',
            'timezone' => 'Europe/London',
            'date_format' => 'd/m/Y',
            'time_format' => 'H:i',
            'tax_enabled' => true,
            'default_tax_rate' => 20.0, // Changed tax
            'prices_include_tax' => false,
            'receipt_show_logo' => true,
        ]);

        // Historical sale in database remains completely unmodified
        $sale->refresh();
        $item = $sale->items->first();

        $this->assertEquals(110.00, (float) $sale->total_amount);
        $this->assertEquals(10.00, (float) $sale->tax_total);
        $this->assertEquals('Original Historical Item', $item->product_name);
        $this->assertEquals(100.00, (float) $item->unit_price);
        $this->assertEquals(10.00, (float) $item->tax_rate);
    }
}
