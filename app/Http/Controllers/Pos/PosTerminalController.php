<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Product;
use App\Services\BusinessSettingService;
use App\Services\PosCheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class PosTerminalController extends Controller
{
    public function __construct(
        protected PosCheckoutService $checkoutService,
        protected BusinessSettingService $settingService
    ) {}

    /**
     * Render the full-screen interactive POS register terminal.
     */
    public function index(Request $request): Response|RedirectResponse
    {
        $user = Auth::user();

        // Resolve active branch context
        $branchId = session('active_branch_id');
        $branch = null;

        if ($branchId) {
            $branch = Branch::where('id', $branchId)->where('is_active', true)->first();
        }

        if (! $branch) {
            $branch = $user->branches()->where('branches.is_active', true)->first()
                ?? Branch::where('is_default', true)->where('is_active', true)->first()
                ?? Branch::where('is_active', true)->first();

            if ($branch) {
                session(['active_branch_id' => $branch->id]);
            }
        }

        if (! $branch) {
            return redirect()->route('admin.branches.index')
                ->with('error', 'No active operating branches found. Please create or activate a branch first.');
        }

        // Validate branch authorization
        if (! $user->isSuperAdmin() && ! $user->isAuthorizedForBranch($branch->id)) {
            abort(403, 'You are not authorized to operate this branch register terminal.');
        }

        // Available authorized branches for switcher
        $branches = $user->isSuperAdmin()
            ? Branch::where('is_active', true)->get(['id', 'name', 'code'])
            : $user->branches()->where('branches.is_active', true)->get(['branches.id', 'branches.name', 'branches.code']);

        // Load active catalog with variants and categories
        $products = Product::active()
            ->with(['category:id,name', 'variants' => function ($q) {
                $q->where('is_active', true);
            }])
            ->orderBy('name')
            ->get()
            ->map(function (Product $product) {
                return [
                    'id' => $product->id,
                    'uuid' => $product->uuid,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'barcode' => $product->barcode,
                    'brand' => $product->brand,
                    'unit' => $product->unit,
                    'selling_price' => (float) $product->selling_price,
                    'is_taxable' => $product->is_taxable,
                    'tax_class' => $product->tax_class,
                    'tax_rate' => (float) $product->tax_rate,
                    'image_url' => $product->image_url,
                    'category_id' => $product->category_id,
                    'has_variants' => $product->has_variants,
                    'variants' => $product->variants->map(fn ($v) => [
                        'id' => $v->id,
                        'name' => $v->name,
                        'sku' => $v->sku,
                        'barcode' => $v->barcode,
                        'selling_price' => (float) $v->selling_price,
                    ]),
                ];
            });

        $categories = Category::active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'parent_id'])
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'full_path' => $c->full_path,
            ]);

        $settings = $this->settingService->getAll();

        return Inertia::render('Pos/Terminal', [
            'activeBranch' => [
                'id' => $branch->id,
                'name' => $branch->name,
                'code' => $branch->code,
                'receipt_header' => $branch->receipt_header_override ?: $settings['receipt_header'],
                'receipt_footer' => $branch->receipt_footer_override ?: $settings['receipt_footer'],
            ],
            'authorizedBranches' => $branches,
            'products' => $products,
            'categories' => $categories,
            'settings' => [
                'business_name' => $settings['business_name'],
                'currency_code' => $settings['currency_code'],
                'currency_symbol' => $settings['currency_symbol'],
                'currency_precision' => (int) $settings['currency_precision'],
                'currency_symbol_position' => $settings['currency_symbol_position'],
                'tax_enabled' => (bool) $settings['tax_enabled'],
                'default_tax_rate' => (float) $settings['default_tax_rate'],
                'prices_include_tax' => (bool) $settings['prices_include_tax'],
                'payment_cash_enabled' => (bool) ($settings['payment_cash_enabled'] ?? true),
                'payment_card_enabled' => (bool) ($settings['payment_card_enabled'] ?? true),
                'payment_bank_transfer_enabled' => (bool) ($settings['payment_bank_transfer_enabled'] ?? true),
                'payment_other_enabled' => (bool) ($settings['payment_other_enabled'] ?? true),
            ],
        ]);
    }

    /**
     * Fast barcode or search query lookup endpoint.
     */
    public function search(Request $request): JsonResponse
    {
        $term = trim((string) $request->input('query', ''));

        if (empty($term)) {
            return response()->json([]);
        }

        $products = Product::active()
            ->search($term)
            ->with(['variants' => fn ($q) => $q->where('is_active', true)])
            ->limit(20)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'barcode' => $p->barcode,
                'selling_price' => (float) $p->selling_price,
                'image_url' => $p->image_url,
                'has_variants' => $p->has_variants,
                'variants' => $p->variants,
            ]);

        return response()->json($products);
    }

    /**
     * Execute atomic transactional checkout.
     */
    public function checkout(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'integer'],
            'idempotency_key' => ['required', 'string', 'max:64'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.product_variant_id' => ['nullable', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
            'discount_total' => ['nullable', 'numeric', 'min:0'],
            'customer_name' => ['nullable', 'string', 'max:150'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:500'],
            'payments' => ['required', 'array', 'min:1'],
            'payments.*.payment_method' => ['required', 'string', 'in:cash,card,bank_transfer,other'],
            'payments.*.amount' => ['required', 'numeric', 'min:0.01'],
            'payments.*.tendered_amount' => ['nullable', 'numeric', 'min:0'],
            'payments.*.reference_note' => ['nullable', 'string', 'max:255'],
        ]);

        $sale = $this->checkoutService->processCheckout(
            $request->user(),
            (int) $validated['branch_id'],
            $validated
        );

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => "Sale #{$sale->sale_number} completed successfully.",
                'sale' => $sale,
            ], 201);
        }

        return back()
            ->with('success', "Sale #{$sale->sale_number} completed successfully.")
            ->with('completed_sale', $sale);
    }
}
