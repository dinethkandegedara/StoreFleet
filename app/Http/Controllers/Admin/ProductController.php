<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\ImageProcessingException;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\ProductImageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function __construct(
        protected ProductImageService $imageService
    ) {}

    /**
     * Display a paginated, searchable, filterable list of products.
     */
    public function index(Request $request): Response
    {
        $search = $request->query('search');
        $categoryId = $request->query('category_id');
        $status = $request->query('status', 'all');

        $query = Product::with(['category:id,name', 'variants'])
            ->withCount('variants');

        if (! empty($search)) {
            $query->search($search);
        }

        if (! empty($categoryId)) {
            $query->inCategory((int) $categoryId);
        }

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        $products = $query->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(function (Product $product) {
                return [
                    'id' => $product->id,
                    'uuid' => $product->uuid,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'barcode' => $product->barcode,
                    'brand' => $product->brand,
                    'product_type' => $product->product_type,
                    'unit' => $product->unit,
                    'selling_price' => (float) $product->selling_price,
                    'cost_price' => $product->cost_price !== null ? (float) $product->cost_price : null,
                    'is_taxable' => $product->is_taxable,
                    'tax_class' => $product->tax_class,
                    'tax_rate' => (float) $product->tax_rate,
                    'image_url' => $product->image_url,
                    'is_active' => $product->is_active,
                    'has_variants' => $product->has_variants,
                    'variants_count' => $product->variants_count,
                    'category' => $product->category ? [
                        'id' => $product->category->id,
                        'name' => $product->category->name,
                    ] : null,
                    'created_at' => $product->created_at?->toIso8601String(),
                ];
            });

        $categories = Category::active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'parent_id'])
            ->map(function ($cat) {
                return [
                    'id' => $cat->id,
                    'name' => $cat->full_path,
                ];
            });

        return Inertia::render('Admin/Products/Index', [
            'products' => $products,
            'filters' => [
                'search' => $search ?? '',
                'category_id' => $categoryId ? (int) $categoryId : null,
                'status' => $status,
            ],
            'categories' => $categories,
        ]);
    }

    /**
     * Show the form for creating a new product.
     */
    public function create(): Response
    {
        $categories = Category::active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn ($cat) => [
                'id' => $cat->id,
                'name' => $cat->full_path,
            ]);

        return Inertia::render('Admin/Products/Form', [
            'mode' => 'create',
            'product' => null,
            'categories' => $categories,
            'standardUnits' => ['piece', 'kg', 'g', 'l', 'ml', 'box', 'pack', 'meter', 'pair'],
            'taxClasses' => [
                ['value' => 'standard', 'label' => 'Standard Rate'],
                ['value' => 'reduced', 'label' => 'Reduced Rate'],
                ['value' => 'exempt', 'label' => 'Tax Exempt'],
            ],
        ]);
    }

    /**
     * Store a newly created product in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => [
                'required',
                'string',
                'max:100',
                'unique:products,sku',
                'unique:product_variants,sku',
            ],
            'barcode' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'brand' => ['nullable', 'string', 'max:100'],
            'product_type' => ['required', 'string', Rule::in(['standard', 'composite', 'service'])],
            'unit' => ['required', 'string', 'max:30'],
            'selling_price' => ['required', 'numeric', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'is_taxable' => ['boolean'],
            'tax_class' => ['required', 'string', Rule::in(['standard', 'reduced', 'exempt'])],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'description' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
            'has_variants' => ['boolean'],
            'is_active' => ['boolean'],
            'image' => ['nullable', 'file', 'max:5120'], // 5MB max
            'variants' => ['nullable', 'array'],
            'variants.*.name' => ['required_with:variants', 'string', 'max:255'],
            'variants.*.sku' => [
                'required_with:variants',
                'string',
                'max:100',
                'distinct',
                'unique:products,sku',
                'unique:product_variants,sku',
            ],
            'variants.*.barcode' => ['nullable', 'string', 'max:100'],
            'variants.*.selling_price' => ['required_with:variants', 'numeric', 'min:0'],
            'variants.*.cost_price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.is_active' => ['boolean'],
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            try {
                $imagePath = $this->imageService->processAndStore(
                    $request->file('image'),
                    $validated['name']
                );
            } catch (ImageProcessingException $e) {
                return back()->withInput()->withErrors(['image' => $e->getMessage()]);
            }
        }

        DB::transaction(function () use ($validated, $imagePath) {
            $hasVariants = ! empty($validated['has_variants']) && ! empty($validated['variants']);

            $product = Product::create([
                'name' => $validated['name'],
                'sku' => $validated['sku'],
                'barcode' => $validated['barcode'] ?? null,
                'category_id' => $validated['category_id'] ?? null,
                'brand' => $validated['brand'] ?? null,
                'product_type' => $validated['product_type'],
                'unit' => $validated['unit'],
                'selling_price' => $validated['selling_price'],
                'cost_price' => $validated['cost_price'] ?? null,
                'is_taxable' => $validated['is_taxable'] ?? true,
                'tax_class' => $validated['tax_class'],
                'tax_rate' => $validated['tax_rate'] ?? 0.00,
                'image_path' => $imagePath,
                'description' => $validated['description'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'tags' => $validated['tags'] ?? [],
                'has_variants' => $hasVariants,
                'is_active' => $validated['is_active'] ?? true,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            if ($hasVariants) {
                foreach ($validated['variants'] as $variantData) {
                    $product->variants()->create([
                        'name' => $variantData['name'],
                        'sku' => $variantData['sku'],
                        'barcode' => $variantData['barcode'] ?? null,
                        'selling_price' => $variantData['selling_price'],
                        'cost_price' => $variantData['cost_price'] ?? null,
                        'is_active' => $variantData['is_active'] ?? true,
                    ]);
                }
            }
        });

        return redirect()->route('admin.products.index')->with('success', 'Product created successfully.');
    }

    /**
     * Show the form for editing the specified product.
     */
    public function edit(Product $product): Response
    {
        $product->load(['variants', 'category']);

        $categories = Category::active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn ($cat) => [
                'id' => $cat->id,
                'name' => $cat->full_path,
            ]);

        return Inertia::render('Admin/Products/Form', [
            'mode' => 'edit',
            'product' => [
                'id' => $product->id,
                'uuid' => $product->uuid,
                'name' => $product->name,
                'sku' => $product->sku,
                'barcode' => $product->barcode,
                'category_id' => $product->category_id,
                'brand' => $product->brand,
                'product_type' => $product->product_type,
                'unit' => $product->unit,
                'selling_price' => (float) $product->selling_price,
                'cost_price' => $product->cost_price !== null ? (float) $product->cost_price : null,
                'is_taxable' => $product->is_taxable,
                'tax_class' => $product->tax_class,
                'tax_rate' => (float) $product->tax_rate,
                'image_url' => $product->image_url,
                'image_path' => $product->image_path,
                'description' => $product->description,
                'notes' => $product->notes,
                'tags' => $product->tags ?? [],
                'has_variants' => $product->has_variants,
                'is_active' => $product->is_active,
                'variants' => $product->variants->map(fn ($v) => [
                    'id' => $v->id,
                    'name' => $v->name,
                    'sku' => $v->sku,
                    'barcode' => $v->barcode,
                    'selling_price' => (float) $v->selling_price,
                    'cost_price' => $v->cost_price !== null ? (float) $v->cost_price : null,
                    'is_active' => $v->is_active,
                ]),
            ],
            'categories' => $categories,
            'standardUnits' => ['piece', 'kg', 'g', 'l', 'ml', 'box', 'pack', 'meter', 'pair'],
            'taxClasses' => [
                ['value' => 'standard', 'label' => 'Standard Rate'],
                ['value' => 'reduced', 'label' => 'Reduced Rate'],
                ['value' => 'exempt', 'label' => 'Tax Exempt'],
            ],
        ]);
    }

    /**
     * Update the specified product in storage.
     */
    public function update(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => [
                'required',
                'string',
                'max:100',
                Rule::unique('products', 'sku')->ignore($product->id),
                'unique:product_variants,sku',
            ],
            'barcode' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'brand' => ['nullable', 'string', 'max:100'],
            'product_type' => ['required', 'string', Rule::in(['standard', 'composite', 'service'])],
            'unit' => ['required', 'string', 'max:30'],
            'selling_price' => ['required', 'numeric', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'is_taxable' => ['boolean'],
            'tax_class' => ['required', 'string', Rule::in(['standard', 'reduced', 'exempt'])],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'description' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
            'has_variants' => ['boolean'],
            'is_active' => ['boolean'],
            'remove_image' => ['nullable', 'boolean'],
            'image' => ['nullable', 'file', 'max:5120'],
            'variants' => ['nullable', 'array'],
            'variants.*.id' => ['nullable', 'integer'],
            'variants.*.name' => ['required_with:variants', 'string', 'max:255'],
            'variants.*.sku' => [
                'required_with:variants',
                'string',
                'max:100',
                'distinct',
            ],
            'variants.*.barcode' => ['nullable', 'string', 'max:100'],
            'variants.*.selling_price' => ['required_with:variants', 'numeric', 'min:0'],
            'variants.*.cost_price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.is_active' => ['boolean'],
        ]);

        $imagePath = $product->image_path;

        // Handle image removal request
        if (! empty($validated['remove_image']) && ! $request->hasFile('image')) {
            if ($imagePath) {
                $this->imageService->deleteImage($imagePath);
                $imagePath = null;
            }
        }

        // Handle new image upload
        if ($request->hasFile('image')) {
            try {
                $imagePath = $this->imageService->processAndStore(
                    $request->file('image'),
                    $validated['name'],
                    $product->image_path // Clean up old image safely
                );
            } catch (ImageProcessingException $e) {
                return back()->withInput()->withErrors(['image' => $e->getMessage()]);
            }
        }

        DB::transaction(function () use ($product, $validated, $imagePath) {
            $hasVariants = ! empty($validated['has_variants']) && ! empty($validated['variants']);

            $product->update([
                'name' => $validated['name'],
                'sku' => $validated['sku'],
                'barcode' => $validated['barcode'] ?? null,
                'category_id' => $validated['category_id'] ?? null,
                'brand' => $validated['brand'] ?? null,
                'product_type' => $validated['product_type'],
                'unit' => $validated['unit'],
                'selling_price' => $validated['selling_price'],
                'cost_price' => $validated['cost_price'] ?? null,
                'is_taxable' => $validated['is_taxable'] ?? true,
                'tax_class' => $validated['tax_class'],
                'tax_rate' => $validated['tax_rate'] ?? 0.00,
                'image_path' => $imagePath,
                'description' => $validated['description'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'tags' => $validated['tags'] ?? [],
                'has_variants' => $hasVariants,
                'is_active' => $validated['is_active'] ?? true,
                'updated_by' => Auth::id(),
            ]);

            // Sync variants if enabled
            if ($hasVariants) {
                $submittedVariantIds = [];

                foreach ($validated['variants'] as $vData) {
                    $variantId = $vData['id'] ?? null;

                    if ($variantId) {
                        $variant = ProductVariant::where('product_id', $product->id)->find($variantId);
                        if ($variant) {
                            $variant->update([
                                'name' => $vData['name'],
                                'sku' => $vData['sku'],
                                'barcode' => $vData['barcode'] ?? null,
                                'selling_price' => $vData['selling_price'],
                                'cost_price' => $vData['cost_price'] ?? null,
                                'is_active' => $vData['is_active'] ?? true,
                            ]);
                            $submittedVariantIds[] = $variant->id;

                            continue;
                        }
                    }

                    $created = $product->variants()->create([
                        'name' => $vData['name'],
                        'sku' => $vData['sku'],
                        'barcode' => $vData['barcode'] ?? null,
                        'selling_price' => $vData['selling_price'],
                        'cost_price' => $vData['cost_price'] ?? null,
                        'is_active' => $vData['is_active'] ?? true,
                    ]);
                    $submittedVariantIds[] = $created->id;
                }

                // Soft-delete variants that were removed
                $product->variants()->whereNotIn('id', $submittedVariantIds)->delete();
            } else {
                // If variants toggled off, soft delete existing variants
                $product->variants()->delete();
            }
        });

        return redirect()->route('admin.products.index')->with('success', 'Product updated successfully.');
    }

    /**
     * Fast toggle product active/inactive lifecycle status.
     */
    public function toggleActive(Product $product): RedirectResponse
    {
        $product->update([
            'is_active' => ! $product->is_active,
            'updated_by' => Auth::id(),
        ]);

        $statusLabel = $product->is_active ? 'activated' : 'deactivated';

        return back()->with('success', "Product \"{$product->name}\" was {$statusLabel}.");
    }

    /**
     * Soft delete product to protect historical sales integrity.
     */
    public function destroy(Product $product): RedirectResponse
    {
        // Safe historical preservation: uses Eloquent SoftDeletes
        // Completed sales records referencing this product remain intact
        $product->delete();

        return redirect()->route('admin.products.index')->with('success', 'Product archived successfully. Historical transaction references are preserved.');
    }
}
