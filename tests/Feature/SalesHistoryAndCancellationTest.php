<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SalesHistoryAndCancellationTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branchA;

    protected Branch $branchB;

    protected Role $businessOwnerRole;

    protected Role $managerRole;

    protected Role $cashierRole;

    protected User $superAdmin;

    protected User $owner;

    protected User $managerBranchA;

    protected User $cashierBranchA;

    protected User $cashierBranchB;

    protected Product $product;

    protected Sale $saleBranchA;

    protected Sale $saleBranchB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);

        $this->branchA = Branch::where('code', 'MAIN')->firstOrFail();
        $this->branchA->update([
            'name' => 'Flagship Store',
            'phone' => '+1 (555) 111-2222',
            'address' => '100 Downtown Plaza',
        ]);

        $this->branchB = Branch::create([
            'name' => 'North Shore Branch',
            'code' => 'NORTH',
            'phone' => '+1 (555) 333-4444',
            'address' => '200 Coastal Highway',
            'is_active' => true,
        ]);

        $this->businessOwnerRole = Role::where('slug', 'business-owner')->firstOrFail();
        $this->managerRole = Role::where('slug', 'branch-manager')->firstOrFail();
        $this->cashierRole = Role::where('slug', 'cashier')->firstOrFail();

        // 1. Super Admin
        $this->superAdmin = User::factory()->create([
            'is_super_admin' => true,
            'is_active' => true,
        ]);

        // 2. Business Owner (Global)
        $this->owner = User::factory()->create(['is_active' => true]);
        $this->owner->assignRole($this->businessOwnerRole, null);

        // 3. Manager Branch A
        $this->managerBranchA = User::factory()->create(['is_active' => true]);
        $this->managerBranchA->assignRole($this->managerRole, $this->branchA->id);

        // 4. Cashier Branch A
        $this->cashierBranchA = User::factory()->create(['is_active' => true]);
        $this->cashierBranchA->assignRole($this->cashierRole, $this->branchA->id);

        // 5. Cashier Branch B
        $this->cashierBranchB = User::factory()->create(['is_active' => true]);
        $this->cashierBranchB->assignRole($this->cashierRole, $this->branchB->id);

        // Seed Product
        $this->product = Product::create([
            'name' => 'Thermal Bluetooth Printer',
            'sku' => 'PRN-BT-001',
            'barcode' => '8901234567890',
            'selling_price' => 120.00,
            'cost_price' => 70.00,
            'unit' => 'piece',
            'is_taxable' => true,
            'tax_class' => 'standard',
            'tax_rate' => 10.00,
            'product_type' => 'standard',
            'is_active' => true,
        ]);

        // Seed Sale for Branch A
        $this->saleBranchA = $this->createTestSale(
            branch: $this->branchA,
            cashier: $this->cashierBranchA,
            saleNumber: 'POS-MAIN-1001',
            customerName: 'Alice Johnson',
            customerPhone: '+1-555-0199',
            completedAt: now()->subHours(2),
            paymentMethod: 'cash',
            tendered: 150.00,
            change: 18.00
        );

        // Seed Sale for Branch B
        $this->saleBranchB = $this->createTestSale(
            branch: $this->branchB,
            cashier: $this->cashierBranchB,
            saleNumber: 'POS-NORTH-2001',
            customerName: 'Bob Smith',
            customerPhone: '+1-555-0288',
            completedAt: now()->subDays(1),
            paymentMethod: 'card',
            tendered: 132.00,
            change: 0.00
        );
    }

    /**
     * Helper to create a fully formed test sale with item snapshots and payments.
     */
    protected function createTestSale(
        Branch $branch,
        User $cashier,
        string $saleNumber,
        string $customerName,
        string $customerPhone,
        $completedAt,
        string $paymentMethod = 'cash',
        float $tendered = 150.00,
        float $change = 18.00
    ): Sale {
        $subtotal = 120.00;
        $tax = 12.00;
        $total = 132.00;

        $sale = Sale::create([
            'uuid' => (string) Str::uuid(),
            'sale_number' => $saleNumber,
            'branch_id' => $branch->id,
            'user_id' => $cashier->id,
            'customer_name' => $customerName,
            'customer_phone' => $customerPhone,
            'status' => 'completed',
            'subtotal' => $subtotal,
            'discount_total' => 0.00,
            'tax_total' => $tax,
            'total_amount' => $total,
            'payment_status' => 'paid',
            'notes' => 'Customer requested paper receipt',
            'idempotency_key' => (string) Str::uuid(),
            'completed_at' => $completedAt,
        ]);

        SaleItem::create([
            'uuid' => (string) Str::uuid(),
            'sale_id' => $sale->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'sku' => $this->product->sku,
            'barcode' => $this->product->barcode,
            'unit' => 'piece',
            'quantity' => 1.0,
            'unit_price' => 120.00,
            'cost_price' => 70.00,
            'discount_amount' => 0.00,
            'is_taxable' => true,
            'tax_class' => 'standard',
            'tax_rate' => 10.00,
            'tax_amount' => 12.00,
            'subtotal' => 120.00,
            'total' => 132.00,
        ]);

        SalePayment::create([
            'uuid' => (string) Str::uuid(),
            'sale_id' => $sale->id,
            'payment_method' => $paymentMethod,
            'amount' => $total,
            'tendered_amount' => $tendered,
            'change_amount' => $change,
            'reference_note' => $paymentMethod === 'card' ? 'AUTH-99482' : null,
            'status' => 'completed',
        ]);

        return $sale;
    }

    public function test_guest_cannot_access_sales_history_or_receipts(): void
    {
        $this->get('/admin/sales')->assertRedirect('/login');
        $this->get("/admin/sales/{$this->saleBranchA->id}")->assertRedirect('/login');
        $this->get("/admin/sales/{$this->saleBranchA->id}/receipt")->assertRedirect('/login');
        $this->post("/admin/sales/{$this->saleBranchA->id}/cancel", ['reason' => 'Mistake'])->assertRedirect('/login');
    }

    public function test_user_without_permission_cannot_view_sales_history(): void
    {
        $userWithoutPerms = User::factory()->create(['is_active' => true]);

        $this->actingAs($userWithoutPerms)
            ->get('/admin/sales')
            ->assertForbidden();
    }

    public function test_authorized_user_can_view_sales_history_listing(): void
    {
        $response = $this->actingAs($this->cashierBranchA)
            ->get('/admin/sales');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Sales/Index')
            ->has('sales.data')
            ->where('sales.data.0.sale_number', $this->saleBranchA->sale_number)
        );
    }

    public function test_branch_restrictions_enforced_in_sales_history_listing(): void
    {
        // Cashier A should only see Branch A sales
        $responseCashierA = $this->actingAs($this->cashierBranchA)->get('/admin/sales');
        $responseCashierA->assertOk();
        $salesListA = $responseCashierA->inertiaProps('sales')['data'];
        $saleNumbersA = collect($salesListA)->pluck('sale_number')->all();

        $this->assertContains($this->saleBranchA->sale_number, $saleNumbersA);
        $this->assertNotContains($this->saleBranchB->sale_number, $saleNumbersA);

        // Cashier B should only see Branch B sales
        $responseCashierB = $this->actingAs($this->cashierBranchB)->get('/admin/sales');
        $responseCashierB->assertOk();
        $salesListB = $responseCashierB->inertiaProps('sales')['data'];
        $saleNumbersB = collect($salesListB)->pluck('sale_number')->all();

        $this->assertContains($this->saleBranchB->sale_number, $saleNumbersB);
        $this->assertNotContains($this->saleBranchA->sale_number, $saleNumbersB);

        // Super Admin sees both
        $responseAdmin = $this->actingAs($this->superAdmin)->get('/admin/sales');
        $responseAdmin->assertOk();
        $adminSales = collect($responseAdmin->inertiaProps('sales')['data'])->pluck('sale_number')->all();
        $this->assertContains($this->saleBranchA->sale_number, $adminSales);
        $this->assertContains($this->saleBranchB->sale_number, $adminSales);
    }

    public function test_user_cannot_filter_by_unauthorized_branch(): void
    {
        // Cashier A attempts to filter explicitly for Branch B
        $this->actingAs($this->cashierBranchA)
            ->get('/admin/sales?branch_id='.$this->branchB->id)
            ->assertForbidden();
    }

    public function test_user_cannot_view_sale_details_for_unauthorized_branch(): void
    {
        // Cashier A attempts to view details for Sale from Branch B
        $this->actingAs($this->cashierBranchA)
            ->getJson("/admin/sales/{$this->saleBranchB->id}")
            ->assertForbidden();
    }

    public function test_user_cannot_view_receipt_for_unauthorized_branch(): void
    {
        // Cashier A attempts to access receipt for Sale from Branch B
        $this->actingAs($this->cashierBranchA)
            ->getJson("/admin/sales/{$this->saleBranchB->id}/receipt")
            ->assertForbidden();
    }

    public function test_search_by_sale_number_customer_and_identifiers(): void
    {
        // Search by sale number
        $resNumber = $this->actingAs($this->superAdmin)
            ->get('/admin/sales?search=POS-MAIN-1001');
        $resNumber->assertOk();
        $this->assertCount(1, $resNumber->inertiaProps('sales')['data']);
        $this->assertEquals('POS-MAIN-1001', $resNumber->inertiaProps('sales')['data'][0]['sale_number']);

        // Search by customer name
        $resCustomer = $this->actingAs($this->superAdmin)
            ->get('/admin/sales?search=Alice');
        $resCustomer->assertOk();
        $this->assertCount(1, $resCustomer->inertiaProps('sales')['data']);
        $this->assertEquals('Alice Johnson', $resCustomer->inertiaProps('sales')['data'][0]['customer_name']);

        // Search by SKU
        $resSku = $this->actingAs($this->superAdmin)
            ->get('/admin/sales?search=PRN-BT-001');
        $resSku->assertOk();
        $this->assertCount(2, $resSku->inertiaProps('sales')['data']);
    }

    public function test_filter_by_cashier_and_payment_method(): void
    {
        // Filter by Cashier A
        $resCashier = $this->actingAs($this->superAdmin)
            ->get('/admin/sales?cashier_id='.$this->cashierBranchA->id);
        $resCashier->assertOk();
        $this->assertCount(1, $resCashier->inertiaProps('sales')['data']);
        $this->assertEquals('POS-MAIN-1001', $resCashier->inertiaProps('sales')['data'][0]['sale_number']);

        // Filter by Payment Method Card
        $resCard = $this->actingAs($this->superAdmin)
            ->get('/admin/sales?payment_method=card');
        $resCard->assertOk();
        $this->assertCount(1, $resCard->inertiaProps('sales')['data']);
        $this->assertEquals('POS-NORTH-2001', $resCard->inertiaProps('sales')['data'][0]['sale_number']);

        // Filter by Payment Method Cash
        $resCash = $this->actingAs($this->superAdmin)
            ->get('/admin/sales?payment_method=cash');
        $resCash->assertOk();
        $this->assertCount(1, $resCash->inertiaProps('sales')['data']);
        $this->assertEquals('POS-MAIN-1001', $resCash->inertiaProps('sales')['data'][0]['sale_number']);
    }

    public function test_filter_by_date_range(): void
    {
        $today = now()->format('Y-m-d');
        $yesterday = now()->subDays(1)->format('Y-m-d');

        // Only today's sales (Branch A)
        $resToday = $this->actingAs($this->superAdmin)
            ->get("/admin/sales?date_from={$today}&date_to={$today}");
        $resToday->assertOk();
        $this->assertCount(1, $resToday->inertiaProps('sales')['data']);
        $this->assertEquals('POS-MAIN-1001', $resToday->inertiaProps('sales')['data'][0]['sale_number']);

        // Only yesterday's sales (Branch B)
        $resYest = $this->actingAs($this->superAdmin)
            ->get("/admin/sales?date_from={$yesterday}&date_to={$yesterday}");
        $resYest->assertOk();
        $this->assertCount(1, $resYest->inertiaProps('sales')['data']);
        $this->assertEquals('POS-NORTH-2001', $resYest->inertiaProps('sales')['data'][0]['sale_number']);
    }

    public function test_filter_by_status(): void
    {
        // Cancel saleBranchB
        $this->saleBranchB->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancelled_by' => $this->superAdmin->id,
            'cancellation_reason' => 'Customer cancellation',
        ]);

        // Filter status=completed
        $resCompleted = $this->actingAs($this->superAdmin)
            ->get('/admin/sales?status=completed');
        $resCompleted->assertOk();
        $this->assertCount(1, $resCompleted->inertiaProps('sales')['data']);
        $this->assertEquals('POS-MAIN-1001', $resCompleted->inertiaProps('sales')['data'][0]['sale_number']);

        // Filter status=cancelled
        $resCancelled = $this->actingAs($this->superAdmin)
            ->get('/admin/sales?status=cancelled');
        $resCancelled->assertOk();
        $this->assertCount(1, $resCancelled->inertiaProps('sales')['data']);
        $this->assertEquals('POS-NORTH-2001', $resCancelled->inertiaProps('sales')['data'][0]['sale_number']);

        // Filter status=all
        $resAll = $this->actingAs($this->superAdmin)
            ->get('/admin/sales?status=all');
        $resAll->assertOk();
        $this->assertCount(2, $resAll->inertiaProps('sales')['data']);
    }

    public function test_receipt_preserves_historical_snapshots_when_catalog_changes(): void
    {
        // Modify the current active catalog product price, name, and tax
        $this->product->update([
            'name' => 'Renamed Ultra Thermal Printer 2.0',
            'selling_price' => 299.99,
            'tax_rate' => 20.00,
        ]);

        // Request receipt data for the previously recorded sale
        $response = $this->actingAs($this->managerBranchA)
            ->getJson("/admin/sales/{$this->saleBranchA->id}/receipt");

        $response->assertOk();
        $data = $response->json();

        // Must still show stored snapshot values (120.00, 10% tax, original name)
        $this->assertEquals('Thermal Bluetooth Printer', $data['items'][0]['name']);
        $this->assertEquals(120.00, $data['items'][0]['unit_price']);
        $this->assertEquals(10.00, $data['items'][0]['tax_rate']);
        $this->assertEquals(132.00, $data['totals']['total_amount']);
        $this->assertEquals('POS-MAIN-1001', $data['sale']['sale_number']);
    }

    public function test_receipt_contains_required_fields_and_branch_details(): void
    {
        $response = $this->actingAs($this->managerBranchA)
            ->get("/admin/sales/{$this->saleBranchA->id}/receipt");

        $response->assertOk();
        $content = $response->getContent();

        // Check required fields rendered in printer-friendly view
        $this->assertStringContainsString('POS-MAIN-1001', $content);
        $this->assertStringContainsString('Flagship Store', $content);
        $this->assertStringContainsString('100 Downtown Plaza', $content);
        $this->assertStringContainsString('+1 (555) 111-2222', $content);
        $this->assertStringContainsString('Alice Johnson', $content);
        $this->assertStringContainsString('Thermal Bluetooth Printer', $content);
        $this->assertStringContainsString('132.00', $content);
        $this->assertStringContainsString('Change Due', $content);
        $this->assertStringContainsString('18.00', $content);
    }

    public function test_unauthorized_user_cannot_cancel_sale(): void
    {
        // Cashier A has 'sales.orders.view' but NOT 'sales.orders.cancel' or 'pos.orders.void'
        $this->actingAs($this->cashierBranchA)
            ->post("/admin/sales/{$this->saleBranchA->id}/cancel", [
                'reason' => 'Customer changed mind',
            ])
            ->assertForbidden();

        $this->saleBranchA->refresh();
        $this->assertEquals('completed', $this->saleBranchA->status);
        $this->assertNull($this->saleBranchA->cancelled_at);
    }

    public function test_user_cannot_cancel_sale_in_unauthorized_branch(): void
    {
        // Manager of Branch A cannot cancel sale in Branch B
        $this->actingAs($this->managerBranchA)
            ->post("/admin/sales/{$this->saleBranchB->id}/cancel", [
                'reason' => 'Manager attempt on other branch',
            ])
            ->assertForbidden();

        $this->saleBranchB->refresh();
        $this->assertEquals('completed', $this->saleBranchB->status);
    }

    public function test_cancellation_requires_a_non_empty_reason(): void
    {
        $response = $this->actingAs($this->managerBranchA)
            ->postJson("/admin/sales/{$this->saleBranchA->id}/cancel", [
                'reason' => '',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['reason']);

        // Whitespace only
        $resWhitespace = $this->actingAs($this->managerBranchA)
            ->postJson("/admin/sales/{$this->saleBranchA->id}/cancel", [
                'reason' => '  ',
            ]);
        $resWhitespace->assertStatus(422);
    }

    public function test_authorized_user_can_cancel_completed_sale_with_audit_trail(): void
    {
        $reason = 'Customer requested immediate order cancellation before dispatch';

        $response = $this->actingAs($this->managerBranchA)
            ->post("/admin/sales/{$this->saleBranchA->id}/cancel", [
                'reason' => $reason,
            ]);

        $response->assertSessionHas('success');

        $this->saleBranchA->refresh();
        $this->assertEquals('cancelled', $this->saleBranchA->status);
        $this->assertNotNull($this->saleBranchA->cancelled_at);
        $this->assertEquals($this->managerBranchA->id, $this->saleBranchA->cancelled_by);
        $this->assertEquals($reason, $this->saleBranchA->cancellation_reason);

        // Verify audit log record created
        $auditLog = AuditLog::where('event', 'sale.cancelled')
            ->where('user_id', $this->managerBranchA->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertEquals($this->saleBranchA->id, $auditLog->payload['sale_id']);
        $this->assertEquals($this->saleBranchA->sale_number, $auditLog->payload['sale_number']);
        $this->assertEquals($reason, $auditLog->payload['cancellation_reason']);
    }

    public function test_already_cancelled_sale_cannot_be_cancelled_again(): void
    {
        // First cancellation
        $this->actingAs($this->managerBranchA)
            ->post("/admin/sales/{$this->saleBranchA->id}/cancel", [
                'reason' => 'Original valid reason',
            ])->assertSessionHas('success');

        // Second attempt
        $resSecond = $this->actingAs($this->managerBranchA)
            ->postJson("/admin/sales/{$this->saleBranchA->id}/cancel", [
                'reason' => 'Second attempt should fail',
            ]);

        $resSecond->assertStatus(422);
        $resSecond->assertJsonFragment(['message' => 'This sale has already been cancelled.']);
    }

    public function test_completed_sale_is_never_deleted_or_rewritten_upon_cancellation(): void
    {
        $originalItemsCount = $this->saleBranchA->items()->count();
        $originalPaymentsCount = $this->saleBranchA->payments()->count();

        $this->actingAs($this->managerBranchA)
            ->post("/admin/sales/{$this->saleBranchA->id}/cancel", [
                'reason' => 'Audit preserved cancellation',
            ]);

        // Assert sale record still exists in database
        $this->assertDatabaseHas('sales', [
            'id' => $this->saleBranchA->id,
            'sale_number' => 'POS-MAIN-1001',
            'status' => 'cancelled',
        ]);

        // Assert line items snapshots are completely preserved
        $this->assertEquals($originalItemsCount, $this->saleBranchA->items()->count());
        $this->assertDatabaseHas('sale_items', [
            'sale_id' => $this->saleBranchA->id,
            'product_name' => 'Thermal Bluetooth Printer',
            'unit_price' => 120.00,
        ]);

        // Assert payment records are completely preserved
        $this->assertEquals($originalPaymentsCount, $this->saleBranchA->payments()->count());
        $this->assertDatabaseHas('sale_payments', [
            'sale_id' => $this->saleBranchA->id,
            'payment_method' => 'cash',
            'amount' => 132.00,
        ]);
    }
}
