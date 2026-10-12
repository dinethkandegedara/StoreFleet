<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\User;
use App\Services\PosCheckoutService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PosCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $mainBranch;

    protected Branch $secondBranch;

    protected Role $businessOwnerRole;

    protected Role $cashierRole;

    protected User $owner;

    protected User $cashier;

    protected Product $standardProduct;

    protected Product $taxExemptProduct;

    protected Product $variantProduct;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);

        $this->mainBranch = Branch::where('code', 'MAIN')->firstOrFail();

        $this->secondBranch = Branch::create([
            'name' => 'Secondary Branch',
            'code' => 'SEC',
            'address' => '200 Market Street',
            'is_active' => true,
        ]);

        $this->businessOwnerRole = Role::where('slug', 'business-owner')->firstOrFail();
        $this->cashierRole = Role::where('slug', 'cashier')->firstOrFail();

        $this->owner = User::factory()->create(['is_active' => true]);
        $this->owner->assignRole($this->businessOwnerRole, $this->mainBranch->id);

        $this->cashier = User::factory()->create(['is_active' => true]);
        $this->cashier->assignRole($this->cashierRole, $this->mainBranch->id);

        // Seed products
        $this->standardProduct = Product::create([
            'name' => 'Wireless Keyboard',
            'sku' => 'KEY-WIRE-01',
            'barcode' => '7111222333444',
            'selling_price' => 50.00,
            'cost_price' => 25.00,
            'unit' => 'piece',
            'is_taxable' => true,
            'tax_class' => 'standard',
            'tax_rate' => 10.00,
            'product_type' => 'standard',
            'is_active' => true,
        ]);

        $this->taxExemptProduct = Product::create([
            'name' => 'Fresh Apples',
            'sku' => 'APPLE-01',
            'barcode' => '7222333444555',
            'selling_price' => 5.00,
            'unit' => 'kg',
            'is_taxable' => false,
            'tax_class' => 'exempt',
            'tax_rate' => 0.00,
            'product_type' => 'standard',
            'is_active' => true,
        ]);

        $this->variantProduct = Product::create([
            'name' => 'StoreFleet T-Shirt',
            'sku' => 'TSHIRT-SF',
            'selling_price' => 20.00,
            'unit' => 'piece',
            'is_taxable' => true,
            'tax_class' => 'standard',
            'tax_rate' => 10.00,
            'product_type' => 'standard',
            'has_variants' => true,
            'is_active' => true,
        ]);

        ProductVariant::create([
            'product_id' => $this->variantProduct->id,
            'name' => 'Medium / Navy',
            'sku' => 'TSHIRT-SF-M-NVY',
            'barcode' => '7333444555666',
            'selling_price' => 22.50,
            'cost_price' => 8.00,
            'is_active' => true,
        ]);
    }

    /**
     * Test 1: Guest is redirected from POS terminal routes.
     */
    public function test_guest_is_redirected_from_pos_terminal(): void
    {
        $this->get('/pos')->assertRedirect('/login');
        $this->get('/pos/search?query=test')->assertRedirect('/login');
        $this->post('/pos/checkout', [])->assertRedirect('/login');
    }

    /**
     * Test 2: User without permissions receives 403 on POS routes.
     */
    public function test_user_without_permission_receives_403_on_pos_terminal(): void
    {
        $unauthorizedUser = User::factory()->create(['is_active' => true]);

        $this->actingAs($unauthorizedUser)->get('/pos')->assertStatus(403);
        $this->actingAs($unauthorizedUser)->post('/pos/checkout', [
            'branch_id' => $this->mainBranch->id,
            'idempotency_key' => (string) Str::uuid(),
            'items' => [['product_id' => $this->standardProduct->id, 'quantity' => 1]],
            'payments' => [['payment_method' => 'cash', 'amount' => 55.00]],
        ])->assertStatus(403);
    }

    /**
     * Test 3: Unauthorized branch checkout attempt is rejected.
     */
    public function test_unauthorized_branch_access_is_rejected(): void
    {
        // Cashier is only assigned to mainBranch, not secondBranch
        // Middleware enforces 403 Forbidden on cross-branch unauthorized access
        $response = $this->actingAs($this->cashier)->post('/pos/checkout', [
            'branch_id' => $this->secondBranch->id,
            'idempotency_key' => (string) Str::uuid(),
            'items' => [
                ['product_id' => $this->standardProduct->id, 'quantity' => 1],
            ],
            'payments' => [
                ['payment_method' => 'cash', 'amount' => 55.00],
            ],
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('sales', ['branch_id' => $this->secondBranch->id]);
    }

    /**
     * Test 4: Successful cash checkout with tax, change calculation, and immutable snapshots.
     */
    public function test_successful_cash_checkout_with_change_calculation(): void
    {
        $idempotencyKey = (string) Str::uuid();

        // 1 Keyboard ($50.00) + 10% tax ($5.00) = $55.00 Total
        // Customer hands $60.00 cash -> Change = $5.00
        $response = $this->actingAs($this->cashier)->post('/pos/checkout', [
            'branch_id' => $this->mainBranch->id,
            'idempotency_key' => $idempotencyKey,
            'items' => [
                [
                    'product_id' => $this->standardProduct->id,
                    'quantity' => 1,
                    'discount_amount' => 0,
                ],
            ],
            'payments' => [
                [
                    'payment_method' => 'cash',
                    'amount' => 55.00,
                    'tendered_amount' => 60.00,
                ],
            ],
        ]);

        $response->assertSessionHas('success');

        $this->assertDatabaseHas('sales', [
            'branch_id' => $this->mainBranch->id,
            'user_id' => $this->cashier->id,
            'subtotal' => 50.00,
            'tax_total' => 5.00,
            'total_amount' => 55.00,
            'idempotency_key' => $idempotencyKey,
            'status' => 'completed',
        ]);

        $sale = Sale::where('idempotency_key', $idempotencyKey)->firstOrFail();
        $this->assertNotNull($sale->sale_number);
        $this->assertStringStartsWith('POS-MAIN-', $sale->sale_number);

        // Verify immutable line item snapshot
        $this->assertDatabaseHas('sale_items', [
            'sale_id' => $sale->id,
            'product_id' => $this->standardProduct->id,
            'product_name' => 'Wireless Keyboard',
            'sku' => 'KEY-WIRE-01',
            'unit_price' => 50.00,
            'tax_rate' => 10.00,
            'tax_amount' => 5.00,
            'total' => 55.00,
        ]);

        // Verify payment record and change calculation
        $this->assertDatabaseHas('sale_payments', [
            'sale_id' => $sale->id,
            'payment_method' => 'cash',
            'amount' => 55.00,
            'tendered_amount' => 60.00,
            'change_amount' => 5.00,
            'status' => 'completed',
        ]);
    }

    /**
     * Test 5: Successful card checkout with reference note.
     */
    public function test_successful_card_checkout_with_reference_note(): void
    {
        $idempotencyKey = (string) Str::uuid();

        // Apples ($5.00 exempt) -> Total = $5.00
        $response = $this->actingAs($this->cashier)->post('/pos/checkout', [
            'branch_id' => $this->mainBranch->id,
            'idempotency_key' => $idempotencyKey,
            'items' => [
                [
                    'product_id' => $this->taxExemptProduct->id,
                    'quantity' => 2, // 2kg * $5 = $10.00
                ],
            ],
            'payments' => [
                [
                    'payment_method' => 'card',
                    'amount' => 10.00,
                    'reference_note' => 'AUTH-99214-VISA',
                ],
            ],
        ]);

        $response->assertSessionHas('success');

        $this->assertDatabaseHas('sales', [
            'idempotency_key' => $idempotencyKey,
            'total_amount' => 10.00,
            'tax_total' => 0.00,
        ]);

        $this->assertDatabaseHas('sale_payments', [
            'payment_method' => 'card',
            'amount' => 10.00,
            'reference_note' => 'AUTH-99214-VISA',
        ]);
    }

    /**
     * Test 6: Checkout rejects insufficient payment.
     */
    public function test_checkout_rejects_insufficient_payment(): void
    {
        // Total due is $55.00, but payment submitted is only $40.00
        $response = $this->actingAs($this->cashier)->post('/pos/checkout', [
            'branch_id' => $this->mainBranch->id,
            'idempotency_key' => (string) Str::uuid(),
            'items' => [
                ['product_id' => $this->standardProduct->id, 'quantity' => 1],
            ],
            'payments' => [
                ['payment_method' => 'cash', 'amount' => 40.00],
            ],
        ]);

        $response->assertSessionHasErrors(['payments']);
        $this->assertEquals(0, Sale::count());
    }

    /**
     * Test 7: Checkout rejects insufficient tendered cash.
     */
    public function test_checkout_rejects_insufficient_tendered_cash(): void
    {
        // Payment amount claims $55.00, but tendered cash given is only $50.00
        $response = $this->actingAs($this->cashier)->post('/pos/checkout', [
            'branch_id' => $this->mainBranch->id,
            'idempotency_key' => (string) Str::uuid(),
            'items' => [
                ['product_id' => $this->standardProduct->id, 'quantity' => 1],
            ],
            'payments' => [
                [
                    'payment_method' => 'cash',
                    'amount' => 55.00,
                    'tendered_amount' => 50.00, // Insufficient!
                ],
            ],
        ]);

        $response->assertSessionHasErrors(['payments.0.tendered_amount']);
        $this->assertEquals(0, Sale::count());
    }

    /**
     * Test 8: Checkout rejects invalid negative or zero quantities.
     */
    public function test_checkout_rejects_invalid_quantities(): void
    {
        $response = $this->actingAs($this->cashier)->post('/pos/checkout', [
            'branch_id' => $this->mainBranch->id,
            'idempotency_key' => (string) Str::uuid(),
            'items' => [
                ['product_id' => $this->standardProduct->id, 'quantity' => 0],
            ],
            'payments' => [
                ['payment_method' => 'cash', 'amount' => 0],
            ],
        ]);

        $response->assertSessionHasErrors(['items.0.quantity']);
    }

    /**
     * Test 9: Checkout rejects inactive or discontinued products.
     */
    public function test_checkout_rejects_inactive_product(): void
    {
        $this->standardProduct->update(['is_active' => false]);

        $response = $this->actingAs($this->cashier)->post('/pos/checkout', [
            'branch_id' => $this->mainBranch->id,
            'idempotency_key' => (string) Str::uuid(),
            'items' => [
                ['product_id' => $this->standardProduct->id, 'quantity' => 1],
            ],
            'payments' => [
                ['payment_method' => 'cash', 'amount' => 55.00],
            ],
        ]);

        $response->assertSessionHasErrors(['items.0']);
    }

    /**
     * Test 10: Idempotency prevents duplicate sales on repeat/double-click requests.
     */
    public function test_idempotency_prevents_duplicate_sales(): void
    {
        $idempotencyKey = 'shared-idempotency-'.Str::random(10);

        $payload = [
            'branch_id' => $this->mainBranch->id,
            'idempotency_key' => $idempotencyKey,
            'items' => [
                ['product_id' => $this->standardProduct->id, 'quantity' => 1],
            ],
            'payments' => [
                ['payment_method' => 'cash', 'amount' => 55.00],
            ],
        ];

        // 1st submission
        $res1 = $this->actingAs($this->cashier)->post('/pos/checkout', $payload);
        $res1->assertSessionHas('success');
        $this->assertEquals(1, Sale::count());

        $firstSale = Sale::where('idempotency_key', $idempotencyKey)->first();

        // 2nd duplicate submission (same idempotency key)
        $res2 = $this->actingAs($this->cashier)->post('/pos/checkout', $payload);
        $res2->assertSessionHas('success');

        // Strictly assert no duplicate sale was created
        $this->assertEquals(1, Sale::count());
        $this->assertEquals($firstSale->id, Sale::where('idempotency_key', $idempotencyKey)->first()->id);
    }

    /**
     * Test 11: Authoritative recalculation ignores client price tampering.
     */
    public function test_authoritative_recalculation_ignores_client_price_tampering(): void
    {
        $idempotencyKey = (string) Str::uuid();

        // Client attempts to pass a fake unit price of $1.00 instead of authoritative $50.00
        $response = $this->actingAs($this->cashier)->post('/pos/checkout', [
            'branch_id' => $this->mainBranch->id,
            'idempotency_key' => $idempotencyKey,
            'items' => [
                [
                    'product_id' => $this->standardProduct->id,
                    'quantity' => 1,
                    'unit_price' => 1.00, // Tampered price
                ],
            ],
            'payments' => [
                [
                    'payment_method' => 'cash',
                    'amount' => 1.10, // Tampered total
                ],
            ],
        ]);

        // Server detects that payment ($1.10) does not cover authoritative total ($55.00)
        $response->assertSessionHasErrors(['payments']);
        $this->assertEquals(0, Sale::count());
    }

    /**
     * Test 12: Historical integrity flag is active after sale.
     */
    public function test_product_historical_transactions_flag_is_active_after_sale(): void
    {
        $this->assertFalse($this->standardProduct->hasHistoricalTransactions());

        $this->actingAs($this->cashier)->post('/pos/checkout', [
            'branch_id' => $this->mainBranch->id,
            'idempotency_key' => (string) Str::uuid(),
            'items' => [
                ['product_id' => $this->standardProduct->id, 'quantity' => 1],
            ],
            'payments' => [
                ['payment_method' => 'cash', 'amount' => 55.00],
            ],
        ]);

        $this->standardProduct->refresh();
        $this->assertTrue($this->standardProduct->hasHistoricalTransactions());
    }

    /**
     * Test 13: Variant checkout captures variant specific identifiers and pricing.
     */
    public function test_variant_checkout_captures_variant_specific_details(): void
    {
        $variant = $this->variantProduct->variants()->firstOrFail();
        $idempotencyKey = (string) Str::uuid();

        // Variant price is $22.50 + 10% tax = $24.75
        $response = $this->actingAs($this->cashier)->post('/pos/checkout', [
            'branch_id' => $this->mainBranch->id,
            'idempotency_key' => $idempotencyKey,
            'items' => [
                [
                    'product_id' => $this->variantProduct->id,
                    'product_variant_id' => $variant->id,
                    'quantity' => 1,
                ],
            ],
            'payments' => [
                [
                    'payment_method' => 'cash',
                    'amount' => 24.75,
                ],
            ],
        ]);

        $response->assertSessionHas('success');

        $sale = Sale::where('idempotency_key', $idempotencyKey)->firstOrFail();
        $this->assertEquals(24.75, (float) $sale->total_amount);

        $this->assertDatabaseHas('sale_items', [
            'sale_id' => $sale->id,
            'product_id' => $this->variantProduct->id,
            'product_variant_id' => $variant->id,
            'product_name' => 'StoreFleet T-Shirt - Medium / Navy',
            'sku' => 'TSHIRT-SF-M-NVY',
            'unit_price' => 22.50,
            'total' => 24.75,
        ]);
    }

    /**
     * Test 14: POS terminal view renders with Inertia and active branch catalog data.
     */
    public function test_terminal_view_renders_with_inertia(): void
    {
        $response = $this->actingAs($this->cashier)->get('/pos');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Pos/Terminal')
            ->has('products')
            ->has('categories')
            ->where('activeBranch.code', 'MAIN')
        );
    }

    /**
     * Test 15: Fast barcode and SKU lookup API endpoint.
     */
    public function test_barcode_search_api_returns_matching_products(): void
    {
        $response = $this->actingAs($this->cashier)->getJson('/pos/search?query=7111222333444');

        $response->assertStatus(200);
        $response->assertJsonCount(1);
        $response->assertJsonFragment([
            'sku' => 'KEY-WIRE-01',
            'barcode' => '7111222333444',
        ]);
    }

    /**
     * Test 16: Atomic rollback behavior prevents partial record creation on failure.
     */
    public function test_atomic_rollback_on_failure(): void
    {
        // Mock a failure during payment record insertion or transaction
        // by attempting a checkout with an invalid payload structure that triggers an exception
        try {
            $checkoutService = app(PosCheckoutService::class);
            $checkoutService->processCheckout($this->cashier, $this->mainBranch->id, [
                'idempotency_key' => 'fail-test-idempotency',
                'items' => [
                    ['product_id' => $this->standardProduct->id, 'quantity' => 1],
                ],
                'payments' => [
                    // Mismatched payment amount triggers validation exception
                    ['payment_method' => 'cash', 'amount' => 10.00],
                ],
            ]);
        } catch (ValidationException $e) {
            // Expected
        }

        // Assert nothing was committed to database
        $this->assertEquals(0, Sale::count());
        $this->assertEquals(0, SaleItem::count());
        $this->assertEquals(0, SalePayment::count());
    }
}
