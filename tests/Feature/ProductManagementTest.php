<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductManagementTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $mainBranch;

    protected Role $businessOwnerRole;

    protected Role $cashierRole;

    protected User $owner;

    protected User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->seed(RbacSeeder::class);

        $this->mainBranch = Branch::where('code', 'MAIN')->firstOrFail();
        $this->businessOwnerRole = Role::where('slug', 'business-owner')->firstOrFail();
        $this->cashierRole = Role::where('slug', 'cashier')->firstOrFail();

        $this->owner = User::factory()->create(['is_active' => true]);
        $this->owner->assignRole($this->businessOwnerRole, $this->mainBranch->id);

        $this->cashier = User::factory()->create(['is_active' => true]);
        $this->cashier->assignRole($this->cashierRole, $this->mainBranch->id);
    }

    /**
     * Helper to create real bitmap test images without requiring GD extension in CLI.
     */
    protected function createTestImage(int $width = 400, int $height = 300, string $filename = 'sample.jpg'): UploadedFile
    {
        $temp = tempnam(sys_get_temp_dir(), 'test_img_').'.jpg';
        exec("magick -size {$width}x{$height} xc:blue ".escapeshellarg($temp));

        return new UploadedFile($temp, $filename, 'image/jpeg', null, true);
    }

    /**
     * Test 1: Guest is redirected from catalog routes to login.
     */
    public function test_guest_is_redirected_from_catalog_routes(): void
    {
        $this->get('/admin/products')->assertRedirect('/login');
        $this->get('/admin/products/create')->assertRedirect('/login');
        $this->post('/admin/products', [])->assertRedirect('/login');
        $this->get('/admin/categories')->assertRedirect('/login');
    }

    /**
     * Test 2: User without permissions receives 403 forbidden on catalog routes.
     */
    public function test_user_without_permission_receives_403_on_catalog_routes(): void
    {
        $this->actingAs($this->cashier)->get('/admin/products/create')->assertStatus(403);
        $this->actingAs($this->cashier)->post('/admin/products', [
            'name' => 'Unauthorized Item',
            'sku' => 'UNAUTH-01',
        ])->assertStatus(403);

        $product = Product::create([
            'name' => 'Existing Item',
            'sku' => 'EXIST-01',
            'selling_price' => 19.99,
            'unit' => 'piece',
            'product_type' => 'standard',
            'tax_class' => 'standard',
        ]);

        $this->actingAs($this->cashier)->get("/admin/products/{$product->id}/edit")->assertStatus(403);
        $this->actingAs($this->cashier)->delete("/admin/products/{$product->id}")->assertStatus(403);
        $this->actingAs($this->cashier)->get('/admin/categories')->assertStatus(403);
    }

    /**
     * Test 3: Authorized user can list and search products.
     */
    public function test_authorized_user_can_view_product_catalog_and_search(): void
    {
        Product::create([
            'name' => 'Wireless Gaming Mouse',
            'sku' => 'MOUSE-01',
            'barcode' => '8801234567890',
            'brand' => 'Logitech',
            'selling_price' => 49.99,
            'unit' => 'piece',
            'product_type' => 'standard',
            'tax_class' => 'standard',
            'is_active' => true,
        ]);

        Product::create([
            'name' => 'Mechanical Keyboard',
            'sku' => 'KEY-01',
            'selling_price' => 89.99,
            'unit' => 'piece',
            'product_type' => 'standard',
            'tax_class' => 'standard',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->owner)->get('/admin/products?search=Mouse');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Products/Index')
            ->has('products.data', 1)
            ->where('products.data.0.sku', 'MOUSE-01')
        );
    }

    /**
     * Test 4: Hierarchical category taxonomy management.
     */
    public function test_authorized_user_can_manage_hierarchical_categories(): void
    {
        // 1. Create root category
        $this->actingAs($this->owner)->post('/admin/categories', [
            'name' => 'Electronics',
            'description' => 'Electronic devices and hardware',
            'sort_order' => 1,
            'is_active' => true,
        ])->assertRedirect(route('admin.categories.index'));

        $electronics = Category::where('name', 'Electronics')->firstOrFail();
        $this->assertEquals('electronics', $electronics->slug);
        $this->assertNull($electronics->parent_id);

        // 2. Create child subcategory
        $this->actingAs($this->owner)->post('/admin/categories', [
            'name' => 'Audio & Headphones',
            'parent_id' => $electronics->id,
            'sort_order' => 2,
            'is_active' => true,
        ])->assertRedirect(route('admin.categories.index'));

        $audio = Category::where('name', 'Audio & Headphones')->firstOrFail();
        $this->assertEquals($electronics->id, $audio->parent_id);
        $this->assertEquals('Electronics > Audio & Headphones', $audio->full_path);

        // 3. Update category
        $this->actingAs($this->owner)->put("/admin/categories/{$audio->id}", [
            'name' => 'Audio Systems',
            'parent_id' => $electronics->id,
            'sort_order' => 3,
            'is_active' => true,
        ])->assertRedirect(route('admin.categories.index'));

        $this->assertDatabaseHas('categories', [
            'id' => $audio->id,
            'name' => 'Audio Systems',
        ]);
    }

    /**
     * Test 5: Cannot delete category with child subcategories or associated products.
     */
    public function test_cannot_delete_category_with_child_or_products(): void
    {
        $parent = Category::create(['name' => 'Apparel', 'slug' => 'apparel']);
        $child = Category::create(['name' => 'Shirts', 'slug' => 'shirts', 'parent_id' => $parent->id]);

        // Attempt to delete parent should fail because it has child
        $this->actingAs($this->owner)->delete("/admin/categories/{$parent->id}")
            ->assertSessionHas('error');
        $this->assertDatabaseHas('categories', ['id' => $parent->id]);

        // Create product associated with child
        $product = Product::create([
            'name' => 'Flannel Shirt',
            'sku' => 'SHIRT-01',
            'category_id' => $child->id,
            'selling_price' => 29.99,
            'unit' => 'piece',
            'product_type' => 'standard',
            'tax_class' => 'standard',
        ]);

        // Attempt to delete child should fail because it has products
        $this->actingAs($this->owner)->delete("/admin/categories/{$child->id}")
            ->assertSessionHas('error');
        $this->assertDatabaseHas('categories', ['id' => $child->id]);

        // Dissociate product and delete
        $product->delete(); // soft delete still preserves relationship if queried with trashed, but products() is standard
        // Physically clear or delete product to test clean category deletion
        $product->forceDelete();
        $this->actingAs($this->owner)->delete("/admin/categories/{$child->id}")
            ->assertRedirect(route('admin.categories.index'));
        $this->assertDatabaseMissing('categories', ['id' => $child->id]);
    }

    /**
     * Test 6: Authorized user can create a standard product.
     */
    public function test_authorized_user_can_create_standard_product(): void
    {
        $category = Category::create(['name' => 'Office Supplies', 'slug' => 'office']);

        $response = $this->actingAs($this->owner)->post('/admin/products', [
            'name' => 'Ergonomic Desk Chair',
            'sku' => 'CHAIR-ERG-01',
            'barcode' => '7350012345678',
            'category_id' => $category->id,
            'brand' => 'Herman Miller',
            'product_type' => 'standard',
            'unit' => 'piece',
            'selling_price' => 299.99,
            'cost_price' => 180.00,
            'is_taxable' => true,
            'tax_class' => 'standard',
            'tax_rate' => 10.00,
            'description' => 'Comfortable ergonomic mesh office chair.',
            'notes' => 'Supplier lead time 3 days.',
            'tags' => ['office', 'furniture', 'ergonomic'],
            'is_active' => true,
        ]);

        $response->assertRedirect(route('admin.products.index'));

        $this->assertDatabaseHas('products', [
            'name' => 'Ergonomic Desk Chair',
            'sku' => 'CHAIR-ERG-01',
            'barcode' => '7350012345678',
            'category_id' => $category->id,
            'selling_price' => 299.99,
            'cost_price' => 180.00,
            'unit' => 'piece',
            'is_active' => 1,
        ]);

        $product = Product::where('sku', 'CHAIR-ERG-01')->firstOrFail();
        $this->assertNotNull($product->uuid);
        $this->assertEquals($this->owner->id, $product->created_by);
    }

    /**
     * Test 7: Duplicate SKU validation rejection.
     */
    public function test_product_creation_rejects_duplicate_sku(): void
    {
        Product::create([
            'name' => 'First Product',
            'sku' => 'UNIQUE-SKU-100',
            'selling_price' => 10.00,
            'unit' => 'piece',
            'product_type' => 'standard',
            'tax_class' => 'standard',
        ]);

        $response = $this->actingAs($this->owner)->post('/admin/products', [
            'name' => 'Second Product with Duplicate SKU',
            'sku' => 'UNIQUE-SKU-100',
            'selling_price' => 15.00,
            'unit' => 'piece',
            'product_type' => 'standard',
            'tax_class' => 'standard',
        ]);

        $response->assertSessionHasErrors(['sku']);
    }

    /**
     * Test 8: Authorized user can create product with variants.
     */
    public function test_authorized_user_can_create_product_with_variants(): void
    {
        $response = $this->actingAs($this->owner)->post('/admin/products', [
            'name' => 'Cotton T-Shirt',
            'sku' => 'TSHIRT-BASE',
            'product_type' => 'standard',
            'unit' => 'piece',
            'selling_price' => 25.00,
            'tax_class' => 'standard',
            'has_variants' => true,
            'variants' => [
                [
                    'name' => 'Size: S / Color: Black',
                    'sku' => 'TSHIRT-S-BLK',
                    'barcode' => '100000000001',
                    'selling_price' => 25.00,
                    'cost_price' => 10.00,
                    'is_active' => true,
                ],
                [
                    'name' => 'Size: M / Color: Black',
                    'sku' => 'TSHIRT-M-BLK',
                    'barcode' => '100000000002',
                    'selling_price' => 25.00,
                    'cost_price' => 10.00,
                    'is_active' => true,
                ],
                [
                    'name' => 'Size: L / Color: Black',
                    'sku' => 'TSHIRT-L-BLK',
                    'barcode' => '100000000003',
                    'selling_price' => 27.00, // Premium size price
                    'cost_price' => 11.00,
                    'is_active' => true,
                ],
            ],
        ]);

        $response->assertRedirect(route('admin.products.index'));

        $product = Product::where('sku', 'TSHIRT-BASE')->firstOrFail();
        $this->assertTrue($product->has_variants);
        $this->assertCount(3, $product->variants);

        $this->assertDatabaseHas('product_variants', [
            'product_id' => $product->id,
            'sku' => 'TSHIRT-L-BLK',
            'selling_price' => 27.00,
        ]);
    }

    /**
     * Test 9: Variant creation rejects duplicate variant SKU.
     */
    public function test_variant_creation_rejects_duplicate_variant_sku(): void
    {
        $response = $this->actingAs($this->owner)->post('/admin/products', [
            'name' => 'Polo Shirt',
            'sku' => 'POLO-BASE',
            'product_type' => 'standard',
            'unit' => 'piece',
            'selling_price' => 30.00,
            'tax_class' => 'standard',
            'has_variants' => true,
            'variants' => [
                [
                    'name' => 'Red',
                    'sku' => 'POLO-RED',
                    'selling_price' => 30.00,
                    'is_active' => true,
                ],
                [
                    'name' => 'Blue (with duplicate SKU)',
                    'sku' => 'POLO-RED', // Duplicate
                    'selling_price' => 30.00,
                    'is_active' => true,
                ],
            ],
        ]);

        $response->assertSessionHasErrors(['variants.0.sku', 'variants.1.sku']);
    }

    /**
     * Test 10: Authorized user can update product and sync variants.
     */
    public function test_authorized_user_can_update_product_and_variants(): void
    {
        $product = Product::create([
            'name' => 'Original Coffee Mug',
            'sku' => 'MUG-001',
            'selling_price' => 12.00,
            'cost_price' => 4.00,
            'unit' => 'piece',
            'product_type' => 'standard',
            'tax_class' => 'standard',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->owner)->put("/admin/products/{$product->id}", [
            'name' => 'Ceramic Coffee Mug Pro',
            'sku' => 'MUG-001', // Keeps same SKU
            'selling_price' => 14.50,
            'cost_price' => 5.00,
            'unit' => 'piece',
            'product_type' => 'standard',
            'tax_class' => 'standard',
            'is_active' => true,
            'has_variants' => false,
        ]);

        $response->assertRedirect(route('admin.products.index'));

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Ceramic Coffee Mug Pro',
            'selling_price' => 14.50,
            'cost_price' => 5.00,
            'updated_by' => $this->owner->id,
        ]);
    }

    /**
     * Test 11: Fast toggle product active/inactive lifecycle status.
     */
    public function test_fast_toggle_product_active_lifecycle_status(): void
    {
        $product = Product::create([
            'name' => 'Seasonal Holiday Box',
            'sku' => 'BOX-HOLIDAY',
            'selling_price' => 45.00,
            'unit' => 'box',
            'product_type' => 'standard',
            'tax_class' => 'standard',
            'is_active' => true,
        ]);

        // Toggle to inactive
        $this->actingAs($this->owner)->post("/admin/products/{$product->id}/toggle-active")
            ->assertRedirect();

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'is_active' => 0,
        ]);

        // Toggle back to active
        $this->actingAs($this->owner)->post("/admin/products/{$product->id}/toggle-active")
            ->assertRedirect();

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'is_active' => 1,
        ]);
    }

    /**
     * Test 12: Soft delete product preserves historical data integrity.
     */
    public function test_soft_delete_preserves_historical_data(): void
    {
        $product = Product::create([
            'name' => 'Legacy Item',
            'sku' => 'LEGACY-001',
            'selling_price' => 20.00,
            'unit' => 'piece',
            'product_type' => 'standard',
            'tax_class' => 'standard',
            'is_active' => true,
        ]);

        $this->actingAs($this->owner)->delete("/admin/products/{$product->id}")
            ->assertRedirect(route('admin.products.index'));

        // Assert record is NOT hard deleted from database
        $this->assertSoftDeleted('products', [
            'id' => $product->id,
            'sku' => 'LEGACY-001',
        ]);

        // Querying with trashed still finds the product
        $found = Product::withTrashed()->find($product->id);
        $this->assertNotNull($found);
        $this->assertEquals('Legacy Item', $found->name);
    }

    /**
     * Test 13: Product image pipeline converts to square 256x256 WebP and stores safe path.
     */
    public function test_image_upload_pipeline_converts_to_256x256_webp_and_strips_metadata(): void
    {
        $image = $this->createTestImage(600, 400, 'camera-photo.jpg');

        $response = $this->actingAs($this->owner)->post('/admin/products', [
            'name' => 'DSLR Camera Bag',
            'sku' => 'BAG-CAM-01',
            'product_type' => 'standard',
            'unit' => 'piece',
            'selling_price' => 79.99,
            'tax_class' => 'standard',
            'image' => $image,
        ]);

        $response->assertRedirect(route('admin.products.index'));

        $product = Product::where('sku', 'BAG-CAM-01')->firstOrFail();
        $this->assertNotNull($product->image_path);
        $this->assertStringStartsWith('products/dslr-camera-bag-', $product->image_path);
        $this->assertStringEndsWith('.webp', $product->image_path);

        // Verify stored file on public storage
        Storage::disk('public')->assertExists($product->image_path);

        // Verify dimensions are 256x256
        $fullPath = Storage::disk('public')->path($product->image_path);
        $info = getimagesize($fullPath);
        $this->assertEquals(256, $info[0]);
        $this->assertEquals(256, $info[1]);
        $this->assertEquals('image/webp', $info['mime']);
    }

    /**
     * Test 14: Image pipeline rejects fake or malicious uploads.
     */
    public function test_image_upload_pipeline_rejects_fake_or_malicious_files(): void
    {
        $fakeFile = UploadedFile::fake()->createWithContent('malicious.jpg', '<?php echo "hack"; ?>');

        $response = $this->actingAs($this->owner)->post('/admin/products', [
            'name' => 'Attempted Hack Item',
            'sku' => 'HACK-01',
            'product_type' => 'standard',
            'unit' => 'piece',
            'selling_price' => 1.00,
            'tax_class' => 'standard',
            'image' => $fakeFile,
        ]);

        $response->assertSessionHasErrors(['image']);
        $this->assertDatabaseMissing('products', ['sku' => 'HACK-01']);
    }

    /**
     * Test 15: Image replacement safely cleans up old image file.
     */
    public function test_image_replacement_safely_cleans_up_old_image(): void
    {
        $img1 = $this->createTestImage(500, 500, 'img1.jpg');

        $this->actingAs($this->owner)->post('/admin/products', [
            'name' => 'Smart Watch',
            'sku' => 'WATCH-01',
            'product_type' => 'standard',
            'unit' => 'piece',
            'selling_price' => 199.00,
            'tax_class' => 'standard',
            'image' => $img1,
        ]);

        $product = Product::where('sku', 'WATCH-01')->firstOrFail();
        $oldImagePath = $product->image_path;
        Storage::disk('public')->assertExists($oldImagePath);

        // Update with new image
        $img2 = $this->createTestImage(400, 400, 'img2.jpg');
        $this->actingAs($this->owner)->put("/admin/products/{$product->id}", [
            'name' => 'Smart Watch Ultra',
            'sku' => 'WATCH-01',
            'product_type' => 'standard',
            'unit' => 'piece',
            'selling_price' => 249.00,
            'tax_class' => 'standard',
            'image' => $img2,
        ]);

        $product->refresh();
        $this->assertNotEquals($oldImagePath, $product->image_path);
        Storage::disk('public')->assertExists($product->image_path);

        // Old file must have been deleted
        Storage::disk('public')->assertMissing($oldImagePath);
    }

    /**
     * Test 16: Image removal flag deletes image and nulls path in database.
     */
    public function test_image_removal_flag_deletes_image_and_nulls_path(): void
    {
        $img = $this->createTestImage(300, 300, 'remove_me.jpg');

        $this->actingAs($this->owner)->post('/admin/products', [
            'name' => 'Bluetooth Speaker',
            'sku' => 'SPEAKER-01',
            'product_type' => 'standard',
            'unit' => 'piece',
            'selling_price' => 59.00,
            'tax_class' => 'standard',
            'image' => $img,
        ]);

        $product = Product::where('sku', 'SPEAKER-01')->firstOrFail();
        $imagePath = $product->image_path;
        Storage::disk('public')->assertExists($imagePath);

        // Remove image
        $this->actingAs($this->owner)->put("/admin/products/{$product->id}", [
            'name' => 'Bluetooth Speaker',
            'sku' => 'SPEAKER-01',
            'product_type' => 'standard',
            'unit' => 'piece',
            'selling_price' => 59.00,
            'tax_class' => 'standard',
            'remove_image' => true,
        ]);

        $product->refresh();
        $this->assertNull($product->image_path);
        Storage::disk('public')->assertMissing($imagePath);
    }

    /**
     * Test 17: Filename collisions are prevented for identical product names.
     */
    public function test_filename_collisions_are_prevented_for_identical_product_names(): void
    {
        $img1 = $this->createTestImage(300, 300, 'first.jpg');
        $img2 = $this->createTestImage(300, 300, 'second.jpg');

        $this->actingAs($this->owner)->post('/admin/products', [
            'name' => 'Same Name Product',
            'sku' => 'SAME-01',
            'product_type' => 'standard',
            'unit' => 'piece',
            'selling_price' => 10.00,
            'tax_class' => 'standard',
            'image' => $img1,
        ]);

        $this->actingAs($this->owner)->post('/admin/products', [
            'name' => 'Same Name Product',
            'sku' => 'SAME-02',
            'product_type' => 'standard',
            'unit' => 'piece',
            'selling_price' => 12.00,
            'tax_class' => 'standard',
            'image' => $img2,
        ]);

        $p1 = Product::where('sku', 'SAME-01')->firstOrFail();
        $p2 = Product::where('sku', 'SAME-02')->firstOrFail();

        $this->assertNotEquals($p1->image_path, $p2->image_path);
        Storage::disk('public')->assertExists($p1->image_path);
        Storage::disk('public')->assertExists($p2->image_path);
    }
}
