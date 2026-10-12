<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Services\BusinessSettingService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessSettingsAndBranchManagementTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $mainBranch;

    protected Role $businessOwnerRole;

    protected Role $cashierRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);

        $this->mainBranch = Branch::where('code', 'MAIN')->firstOrFail();
        $this->businessOwnerRole = Role::where('slug', 'business-owner')->firstOrFail();
        $this->cashierRole = Role::where('slug', 'cashier')->firstOrFail();
    }

    /**
     * Test 1: Guest is redirected from settings and branch administration.
     */
    public function test_guest_is_redirected_from_settings_and_branch_routes(): void
    {
        $this->get('/admin/settings')->assertRedirect('/login');
        $this->get('/admin/branches')->assertRedirect('/login');
        $this->get('/admin/branches/create')->assertRedirect('/login');
    }

    /**
     * Test 2: Unauthorized operational user cannot access business settings or branch management.
     */
    public function test_unauthorized_user_receives_403_on_settings_and_branch_routes(): void
    {
        $cashier = User::factory()->create(['is_active' => true]);
        $cashier->assignRole($this->cashierRole, $this->mainBranch->id);

        $this->actingAs($cashier)->get('/admin/settings')->assertStatus(403);
        $this->actingAs($cashier)->put('/admin/settings', ['business_name' => 'Hacked Name'])->assertStatus(403);

        $this->actingAs($cashier)->get('/admin/branches')->assertStatus(403);
        $this->actingAs($cashier)->post('/admin/branches', ['name' => 'Hacked Branch'])->assertStatus(403);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $cashier->id,
            'event' => 'unauthorized_permission_access_attempt',
        ]);
    }

    /**
     * Test 3: Business owner can update business configuration, branding, currency, tax, and receipts.
     */
    public function test_business_owner_can_update_and_persist_settings(): void
    {
        $owner = User::factory()->create(['is_active' => true]);
        $owner->assignRole($this->businessOwnerRole);

        $payload = [
            'business_name' => 'Metro Retail Co.',
            'business_email' => 'admin@metroretail.test',
            'business_phone' => '+1 (555) 345-6789',
            'address_line1' => '500 Market St',
            'address_line2' => 'Suite 100',
            'city' => 'San Francisco',
            'state' => 'CA',
            'postal_code' => '94105',
            'country' => 'US',
            'primary_color' => '#059669',
            'currency_code' => 'EUR',
            'currency_symbol' => '€',
            'currency_precision' => 2,
            'currency_symbol_position' => 'after',
            'decimal_separator' => ',',
            'thousand_separator' => '.',
            'timezone' => 'Europe/Paris',
            'date_format' => 'd/m/Y',
            'time_format' => 'H:i',
            'tax_enabled' => true,
            'default_tax_rate' => 20.00,
            'tax_id_number' => 'FR-123456789',
            'prices_include_tax' => true,
            'receipt_header' => 'Bienvenue chez Metro Retail!',
            'receipt_footer' => 'Merci de votre visite.',
            'receipt_show_logo' => true,
        ];

        $response = $this->actingAs($owner)->put('/admin/settings', $payload);
        $response->assertSessionHas('success');

        $service = app(BusinessSettingService::class);
        $this->assertEquals('Metro Retail Co.', $service->get('business_name'));
        $this->assertEquals('EUR', $service->get('currency_code'));
        $this->assertEquals('€', $service->get('currency_symbol'));
        $this->assertEquals(20.00, $service->get('default_tax_rate'));
        $this->assertEquals('Bienvenue chez Metro Retail!', $service->get('receipt_header'));

        // Setting persists in settings table
        $this->assertDatabaseHas('settings', [
            'scope' => 'business',
            'key' => 'business_name',
            'value' => 'Metro Retail Co.',
        ]);

        // Audit log records update
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $owner->id,
            'event' => 'business_settings_updated',
        ]);
    }

    /**
     * Test 4: Authorized administrator can create, view, and edit branches.
     */
    public function test_authorized_user_can_create_and_update_branches(): void
    {
        $owner = User::factory()->create(['is_active' => true]);
        $owner->assignRole($this->businessOwnerRole);

        // 1. Create branch
        $createResponse = $this->actingAs($owner)->post('/admin/branches', [
            'name' => 'Uptown Boutique',
            'code' => 'UPT01',
            'address' => '789 Uptown Blvd',
            'city' => 'Chicago',
            'state' => 'IL',
            'postal_code' => '60611',
            'country' => 'US',
            'phone' => '+1 (312) 555-0199',
            'email' => 'uptown@metroretail.test',
            'manager_name' => 'Alice Johnson',
            'receipt_header' => 'Uptown Location - Exclusive Designer Goods',
            'receipt_footer' => 'Final sale on seasonal accessories.',
            'is_default' => false,
        ]);

        $createResponse->assertRedirect('/admin/branches');
        $this->assertDatabaseHas('branches', [
            'name' => 'Uptown Boutique',
            'code' => 'UPT01',
            'city' => 'Chicago',
        ]);

        $branch = Branch::where('code', 'UPT01')->firstOrFail();

        // 2. Edit branch
        $updateResponse = $this->actingAs($owner)->put("/admin/branches/{$branch->id}", [
            'name' => 'Uptown Flagship Boutique',
            'code' => 'UPT01',
            'city' => 'Chicago',
            'manager_name' => 'Alice Cooper',
            'is_default' => false,
        ]);

        $updateResponse->assertRedirect('/admin/branches');
        $this->assertEquals('Uptown Flagship Boutique', $branch->fresh()->name);
        $this->assertEquals('Alice Cooper', $branch->fresh()->manager_name);
    }

    /**
     * Test 5: Controlled deactivation refuses to deactivate the only active branch.
     */
    public function test_controlled_deactivation_prevents_deactivating_the_only_active_branch(): void
    {
        $owner = User::factory()->create(['is_active' => true]);
        $owner->assignRole($this->businessOwnerRole);

        // Only MAIN branch exists and is active
        $this->assertEquals(1, Branch::where('is_active', true)->count());

        $response = $this->actingAs($owner)->post("/admin/branches/{$this->mainBranch->id}/toggle-active");
        $response->assertSessionHas('error', 'Cannot deactivate the only active branch. The business must have at least one operating location.');

        $this->assertTrue($this->mainBranch->fresh()->is_active);
    }

    /**
     * Test 6: Controlled deactivation succeeds when multiple active branches exist.
     */
    public function test_can_deactivate_branch_when_another_active_branch_exists(): void
    {
        $owner = User::factory()->create(['is_active' => true]);
        $owner->assignRole($this->businessOwnerRole);

        $secondBranch = Branch::create([
            'name' => 'Harbor Store',
            'code' => 'HBR01',
            'is_active' => true,
        ]);

        $response = $this->actingAs($owner)->post("/admin/branches/{$secondBranch->id}/toggle-active");
        $response->assertSessionHas('success');

        $this->assertFalse($secondBranch->fresh()->is_active);
    }

    /**
     * Test 7: Authorized branch switching vs unauthorized cross-branch switching.
     */
    public function test_authorized_branch_switching_and_unauthorized_rejection(): void
    {
        $branchA = $this->mainBranch;
        $branchB = Branch::create([
            'name' => 'South Mall',
            'code' => 'SM01',
            'is_active' => true,
        ]);

        $staff = User::factory()->create(['is_active' => true]);
        // Staff only assigned to branchA
        $staff->assignRole($this->cashierRole, $branchA->id);

        // Attempting to switch to branchA succeeds
        $allowedResponse = $this->actingAs($staff)->post("/switch-branch/{$branchA->id}");
        $allowedResponse->assertSessionHas('success');
        $this->assertEquals($branchA->id, session('active_branch_id'));

        // Attempting to switch to branchB (unauthorized) is rejected
        $deniedResponse = $this->actingAs($staff)->post("/switch-branch/{$branchB->id}");
        $deniedResponse->assertSessionHas('error', 'You are not authorized to access this branch.');

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $staff->id,
            'event' => 'unauthorized_branch_switch_attempt',
        ]);
    }

    /**
     * Test 8: Branch-specific receipt header overrides fallback to business defaults when empty.
     */
    public function test_receipt_resolution_uses_branch_override_or_business_default(): void
    {
        app(BusinessSettingService::class)->updateMany([
            'receipt_header' => 'Global Merchant Header',
            'receipt_footer' => 'Global Policy Footer',
        ]);

        $branchWithOverride = Branch::create([
            'name' => 'Custom Receipt Store',
            'code' => 'CRS01',
            'receipt_header' => 'Branch Custom Header',
            'receipt_footer' => null, // Empty footer
            'is_active' => true,
        ]);

        // Header uses branch override
        $this->assertEquals('Branch Custom Header', $branchWithOverride->getEffectiveReceiptHeader());
        // Footer falls back to business default
        $this->assertEquals('Global Policy Footer', $branchWithOverride->getEffectiveReceiptFooter());
    }

    /**
     * Test 9: User-specific theme preference persists independently.
     */
    public function test_user_theme_preference_persists(): void
    {
        $user = User::factory()->create(['is_active' => true, 'theme_preference' => 'light']);

        $response = $this->actingAs($user)->post('/user/preference/theme', [
            'theme_preference' => 'dark',
        ]);

        $response->assertSessionHas('success');
        $this->assertEquals('dark', $user->fresh()->theme_preference);
    }
}
