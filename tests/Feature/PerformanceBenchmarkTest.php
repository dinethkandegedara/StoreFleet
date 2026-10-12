<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchUserRole;
use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PerformanceBenchmarkTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch;

    protected User $cashier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);

        $this->branch = Branch::where('code', 'MAIN')->firstOrFail();
        $cashierRole = Role::where('slug', 'cashier')->firstOrFail();

        $this->cashier = User::factory()->create(['is_active' => true]);
        BranchUserRole::create([
            'user_id' => $this->cashier->id,
            'role_id' => $cashierRole->id,
            'branch_id' => $this->branch->id,
        ]);
    }

    /**
     * Benchmark: Product search under representative 1,000 product catalog load.
     * Target: Server response time strictly below 300 ms.
     */
    public function test_product_search_latency_under_1000_product_catalog_load(): void
    {
        $category = Category::create([
            'name' => 'Benchmark Category',
            'slug' => 'benchmark-category',
            'is_active' => true,
        ]);

        // Bulk insert 1,000 products to simulate realistic production store catalog
        $products = [];
        $now = now();
        for ($i = 1; $i <= 1000; $i++) {
            $products[] = [
                'uuid' => (string) Str::uuid(),
                'name' => "Benchmark Product {$i}",
                'slug' => "benchmark-product-{$i}",
                'sku' => sprintf('SKU-%05d', $i),
                'barcode' => sprintf('8800000%05d', $i),
                'category_id' => $category->id,
                'brand' => 'Acme Supplies',
                'product_type' => 'standard',
                'unit' => 'piece',
                'selling_price' => 19.99,
                'cost_price' => 10.00,
                'is_taxable' => true,
                'tax_class' => 'standard',
                'tax_rate' => 8.25,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // Chunked insert for SQLite / MariaDB parameter limits
        foreach (array_chunk($products, 200) as $chunk) {
            Product::insert($chunk);
        }

        $this->assertEquals(1000, Product::count());

        // 1. Exact Barcode Lookup Benchmark
        $targetBarcode = '880000000500';
        $barcodeDurations = [];

        for ($iteration = 0; $iteration < 5; $iteration++) {
            $start = microtime(true);
            $response = $this->actingAs($this->cashier)->getJson("/pos/search?query={$targetBarcode}");
            $durationMs = (microtime(true) - $start) * 1000;
            $barcodeDurations[] = $durationMs;

            $response->assertStatus(200);
            $data = $response->json();
            $this->assertNotEmpty($data);
            $this->assertEquals($targetBarcode, $data[0]['barcode']);
        }

        $avgBarcodeDuration = array_sum($barcodeDurations) / count($barcodeDurations);

        // 2. Substring Name Search Benchmark
        $nameQuery = 'Product 50';
        $nameDurations = [];

        for ($iteration = 0; $iteration < 5; $iteration++) {
            $start = microtime(true);
            $response = $this->actingAs($this->cashier)->getJson("/pos/search?query={$nameQuery}");
            $durationMs = (microtime(true) - $start) * 1000;
            $nameDurations[] = $durationMs;

            $response->assertStatus(200);
            $this->assertNotEmpty($response->json());
        }

        $avgNameDuration = array_sum($nameDurations) / count($nameDurations);

        // Output measured metrics for documented reporting
        echo sprintf(
            "\n[PERFORMANCE MEASUREMENT] Product Search (1,000 Catalog):\n".
            "  - Exact Barcode Lookup Avg: %.2f ms (Target: < 300 ms)\n".
            "  - Substring Name Search Avg: %.2f ms (Target: < 300 ms)\n",
            $avgBarcodeDuration,
            $avgNameDuration
        );

        $this->assertLessThan(300.0, $avgBarcodeDuration, "Barcode search average ({$avgBarcodeDuration}ms) exceeded 300ms target.");
        $this->assertLessThan(300.0, $avgNameDuration, "Name search average ({$avgNameDuration}ms) exceeded 300ms target.");
    }

    /**
     * Benchmark: POS Atomic Transactional Checkout Execution Latency.
     * Target: Transaction execution time strictly below 100 ms.
     */
    public function test_pos_transactional_checkout_execution_latency(): void
    {
        $products = [];
        for ($i = 1; $i <= 5; $i++) {
            $products[] = Product::create([
                'name' => "Speed Test Item {$i}",
                'sku' => "SPD-00{$i}",
                'barcode' => "99000{$i}",
                'selling_price' => 15.00 * $i,
                'cost_price' => 8.00 * $i,
                'is_taxable' => true,
                'tax_class' => 'standard',
                'tax_rate' => 10.00,
                'is_active' => true,
            ]);
        }

        $checkoutDurations = [];

        for ($run = 1; $run <= 5; $run++) {
            $payload = [
                'branch_id' => $this->branch->id,
                'idempotency_key' => 'BENCH-IDEM-'.Str::random(16),
                'customer_name' => 'Benchmark Customer',
                'items' => array_map(fn ($p) => [
                    'product_id' => $p->id,
                    'quantity' => 2,
                    'discount_amount' => 1.00,
                ], $products),
                'discount_total' => 5.00,
                'payments' => [
                    [
                        'payment_method' => 'cash',
                        'amount' => 1000.00,
                        'tendered_amount' => 1000.00,
                    ],
                ],
            ];

            $start = microtime(true);
            $response = $this->actingAs($this->cashier)->postJson('/pos/checkout', $payload);
            $durationMs = (microtime(true) - $start) * 1000;
            $checkoutDurations[] = $durationMs;

            $response->assertStatus(201);
        }

        $avgCheckoutDuration = array_sum($checkoutDurations) / count($checkoutDurations);

        echo sprintf(
            "[PERFORMANCE MEASUREMENT] POS Multi-Item Checkout Latency:\n".
            "  - 5-Item Atomic Transaction Avg: %.2f ms (Target: < 100 ms)\n",
            $avgCheckoutDuration
        );

        $this->assertLessThan(100.0, $avgCheckoutDuration, "POS Checkout average ({$avgCheckoutDuration}ms) exceeded 100ms target.");
    }

    /**
     * Benchmark: Eager Loading and N+1 Query Count Boundaries across Core Modules.
     * Target: Constant query count independent of row count.
     */
    public function test_query_count_boundaries_prevent_n_plus_one_explosions(): void
    {
        $owner = User::factory()->create(['is_active' => true]);
        $ownerRole = Role::where('slug', 'business-owner')->firstOrFail();
        BranchUserRole::create([
            'user_id' => $owner->id,
            'role_id' => $ownerRole->id,
            'branch_id' => null,
        ]);

        // Seed 15 sales orders with items and payments
        for ($s = 1; $s <= 15; $s++) {
            $sale = Sale::create([
                'sale_number' => "BENCH-SALE-{$s}",
                'branch_id' => $this->branch->id,
                'user_id' => $this->cashier->id,
                'subtotal' => 100.00,
                'total_amount' => 110.00,
                'status' => 'completed',
                'idempotency_key' => "BENCH-IDEM-SALE-{$s}",
                'completed_at' => now(),
            ]);

            $sale->items()->create([
                'product_name' => "Item {$s}",
                'sku' => "SKU-{$s}",
                'unit_price' => 100.00,
                'subtotal' => 100.00,
                'total' => 110.00,
            ]);

            $sale->payments()->create([
                'payment_method' => 'cash',
                'amount' => 110.00,
            ]);
        }

        // Measure query count for Sales History listing
        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs($owner)->get('/admin/sales');
        $response->assertStatus(200);

        $salesQueries = DB::getQueryLog();
        $salesQueryCount = count($salesQueries);
        DB::disableQueryLog();

        // Measure query count for Products catalog listing
        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs($owner)->get('/admin/products');
        $response->assertStatus(200);

        $productQueries = DB::getQueryLog();
        $productQueryCount = count($productQueries);
        DB::disableQueryLog();

        // Measure query count for Staff Users listing
        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs($owner)->get('/admin/users');
        $response->assertStatus(200);

        $userQueries = DB::getQueryLog();
        $userQueryCount = count($userQueries);
        DB::disableQueryLog();

        echo sprintf(
            "[PERFORMANCE MEASUREMENT] Database Query Count Boundaries (O(1) Query Complexity):\n".
            "  - /admin/sales (15 sales with relations): %d SQL queries (Bound: <= 20)\n".
            "  - /admin/products (catalog listing): %d SQL queries (Bound: <= 8)\n".
            "  - /admin/users (staff accounts): %d SQL queries (Bound: <= 16)\n",
            $salesQueryCount,
            $productQueryCount,
            $userQueryCount
        );

        $this->assertLessThanOrEqual(20, $salesQueryCount, "Sales history queries ({$salesQueryCount}) exceeded bound.");
        $this->assertLessThanOrEqual(8, $productQueryCount, "Product catalog queries ({$productQueryCount}) exceeded bound.");
        $this->assertLessThanOrEqual(16, $userQueryCount, "User listing queries ({$userQueryCount}) exceeded bound.");
    }
}
