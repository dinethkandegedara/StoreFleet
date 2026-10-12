<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Sale;
use App\Models\User;
use App\Services\BusinessSettingService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SaleHistoryController extends Controller
{
    public function __construct(
        protected BusinessSettingService $settingService
    ) {}

    /**
     * Resolve authorized branch IDs for the given user.
     * Returns null if unrestricted (Super Admin or Business Owner).
     *
     * @return list<int>|null
     */
    protected function getAuthorizedBranchIds(User $user): ?array
    {
        if ($user->isSuperAdmin() || $user->hasRole('business-owner')) {
            return null;
        }

        $hasGlobalRole = $user->branchUserRoles()
            ->whereNull('branch_id')
            ->whereHas('role', fn ($q) => $q->where('is_active', true))
            ->exists();

        if ($hasGlobalRole) {
            return null;
        }

        return $user->branchUserRoles()
            ->whereNotNull('branch_id')
            ->whereHas('role', fn ($q) => $q->where('is_active', true))
            ->pluck('branch_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Check if user is allowed to cancel sales.
     */
    protected function userCanCancel(User $user, ?int $branchId = null): bool
    {
        if ($user->isSuperAdmin() || $user->hasRole('business-owner')) {
            return true;
        }

        return $user->hasPermission('sales.orders.cancel', $branchId)
            || $user->hasPermission('pos.orders.void', $branchId);
    }

    /**
     * Display a permission-protected, server-side filtered listing of sales transactions.
     */
    public function index(Request $request): Response
    {
        $user = Auth::user();
        $authorizedBranchIds = $this->getAuthorizedBranchIds($user);

        $query = Sale::query()
            ->with([
                'branch:id,name,code,address,phone',
                'user:id,name,email',
                'canceller:id,name,email',
                'payments',
                'items',
            ]);

        // 1. Enforce branch authorization boundary
        if ($authorizedBranchIds !== null) {
            $query->whereIn('branch_id', $authorizedBranchIds);
        }

        // 2. Filter: Branch
        $selectedBranchId = $request->input('branch_id');
        if ($selectedBranchId !== null && $selectedBranchId !== '') {
            $branchId = (int) $selectedBranchId;
            if ($authorizedBranchIds !== null && ! in_array($branchId, $authorizedBranchIds, true)) {
                abort(403, 'You are not authorized to access sales for this branch.');
            }
            $query->where('branch_id', $branchId);
        }

        // 3. Filter: Search (Sale number, customer name, phone, cashier name, SKU, or item name)
        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('sale_number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_phone', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($uq) => $uq->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('items', function ($iq) use ($search) {
                        $iq->where('product_name', 'like', "%{$search}%")
                            ->orWhere('sku', 'like', "%{$search}%")
                            ->orWhere('barcode', 'like', "%{$search}%");
                    });
            });
        }

        // 4. Filter: Cashier
        $cashierId = $request->input('cashier_id');
        if ($cashierId !== null && $cashierId !== '') {
            $query->where('user_id', (int) $cashierId);
        }

        // 5. Filter: Payment Method
        $paymentMethod = $request->input('payment_method');
        if ($paymentMethod !== null && $paymentMethod !== '') {
            $query->whereHas('payments', function ($pq) use ($paymentMethod) {
                $pq->where('payment_method', $paymentMethod);
            });
        }

        // 6. Filter: Status
        $status = $request->input('status');
        if ($status !== null && $status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        // 7. Filter: Date Range
        $dateFrom = $request->input('date_from');
        if ($dateFrom) {
            try {
                $query->where('completed_at', '>=', Carbon::parse($dateFrom)->startOfDay());
            } catch (\Throwable $e) {
                // Ignore invalid date format
            }
        }

        $dateTo = $request->input('date_to');
        if ($dateTo) {
            try {
                $query->where('completed_at', '<=', Carbon::parse($dateTo)->endOfDay());
            } catch (\Throwable $e) {
                // Ignore invalid date format
            }
        }

        // Order by completed_at descending
        $query->orderBy('completed_at', 'desc')->orderBy('id', 'desc');

        $sales = $query->paginate(15)->withQueryString();

        // Transform paginated sales
        $sales->through(function (Sale $sale) use ($user) {
            return [
                'id' => $sale->id,
                'uuid' => $sale->uuid,
                'sale_number' => $sale->sale_number,
                'branch' => [
                    'id' => $sale->branch?->id,
                    'name' => $sale->branch?->name,
                    'code' => $sale->branch?->code,
                ],
                'cashier' => [
                    'id' => $sale->user?->id,
                    'name' => $sale->user?->name,
                ],
                'customer_name' => $sale->customer_name,
                'customer_phone' => $sale->customer_phone,
                'status' => $sale->status,
                'subtotal' => (float) $sale->subtotal,
                'discount_total' => (float) $sale->discount_total,
                'tax_total' => (float) $sale->tax_total,
                'total_amount' => (float) $sale->total_amount,
                'payment_status' => $sale->payment_status,
                'payment_methods' => $sale->payments->pluck('payment_method')->unique()->values()->all(),
                'items_count' => $sale->items->count(),
                'completed_at' => $sale->completed_at?->toIso8601String(),
                'completed_at_formatted' => $sale->completed_at?->format('M d, Y h:i A'),
                'cancelled_at' => $sale->cancelled_at?->toIso8601String(),
                'cancelled_at_formatted' => $sale->cancelled_at?->format('M d, Y h:i A'),
                'cancelled_by_name' => $sale->canceller?->name,
                'cancellation_reason' => $sale->cancellation_reason,
                'can_cancel' => $sale->canBeCancelled() && $this->userCanCancel($user, $sale->branch_id),
            ];
        });

        // Available branches for filtering
        $branchesQuery = Branch::where('is_active', true)->orderBy('name');
        if ($authorizedBranchIds !== null) {
            $branchesQuery->whereIn('id', $authorizedBranchIds);
        }
        $branches = $branchesQuery->get(['id', 'name', 'code']);

        // Cashiers for filtering (users who have recorded sales in authorized branches)
        $cashiersQuery = User::whereHas('sales', function ($sq) use ($authorizedBranchIds) {
            if ($authorizedBranchIds !== null) {
                $sq->whereIn('branch_id', $authorizedBranchIds);
            }
        })->orderBy('name');
        $cashiers = $cashiersQuery->get(['id', 'name', 'email']);

        $settings = $this->settingService->getAll();

        return Inertia::render('Admin/Sales/Index', [
            'sales' => $sales,
            'filters' => [
                'search' => $request->input('search', ''),
                'branch_id' => $request->input('branch_id', ''),
                'cashier_id' => $request->input('cashier_id', ''),
                'payment_method' => $request->input('payment_method', ''),
                'status' => $request->input('status', 'all'),
                'date_from' => $request->input('date_from', ''),
                'date_to' => $request->input('date_to', ''),
            ],
            'branches' => $branches,
            'cashiers' => $cashiers,
            'paymentMethods' => ['cash', 'card', 'bank_transfer', 'other'],
            'canCancelAny' => $this->userCanCancel($user),
            'business' => [
                'name' => $settings['business_name'],
                'currency_symbol' => $settings['currency_symbol'],
                'currency_code' => $settings['currency_code'],
                'currency_precision' => (int) $settings['currency_precision'],
            ],
        ]);
    }

    /**
     * Show full historical sale details with item snapshots and payment records.
     */
    public function show(Request $request, Sale $sale): JsonResponse|Response|RedirectResponse
    {
        $user = Auth::user();
        $authorizedBranchIds = $this->getAuthorizedBranchIds($user);

        if ($authorizedBranchIds !== null && ! in_array($sale->branch_id, $authorizedBranchIds, true)) {
            abort(403, 'You are not authorized to view this branch transaction.');
        }

        $sale->load([
            'branch',
            'user:id,name,email',
            'canceller:id,name,email',
            'payments',
            'items',
        ]);

        $payload = $this->formatSaleDetailPayload($sale, $user);

        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        // If visited as a full page request, redirect to index with initial view ID
        return redirect()->route('admin.sales.index', ['view' => $sale->id]);
    }

    /**
     * Printable thermal / standard receipt data and HTML view.
     */
    public function receipt(Request $request, Sale $sale): JsonResponse|HttpResponse
    {
        $user = Auth::user();
        $authorizedBranchIds = $this->getAuthorizedBranchIds($user);

        if ($authorizedBranchIds !== null && ! in_array($sale->branch_id, $authorizedBranchIds, true)) {
            abort(403, 'You are not authorized to print receipts for this branch.');
        }

        $sale->load([
            'branch',
            'user:id,name,email',
            'canceller:id,name,email',
            'payments',
            'items',
        ]);

        $receiptData = $this->formatReceiptPayload($sale);

        if ($request->wantsJson()) {
            return response()->json($receiptData);
        }

        return response()->view('receipts.thermal', ['receipt' => $receiptData]);
    }

    /**
     * Authorize and record cancellation of a completed sale order.
     */
    public function cancel(Request $request, Sale $sale): JsonResponse|RedirectResponse
    {
        $user = Auth::user();

        // 1. Permission check
        if (! $this->userCanCancel($user, $sale->branch_id)) {
            abort(403, 'You do not have permission to cancel completed sales transactions.');
        }

        // 2. Branch boundary check
        $authorizedBranchIds = $this->getAuthorizedBranchIds($user);
        if ($authorizedBranchIds !== null && ! in_array($sale->branch_id, $authorizedBranchIds, true)) {
            abort(403, 'You are not authorized to cancel sales in this branch.');
        }

        // 3. Validation
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        // 4. Verify current status
        if ($sale->isCancelled()) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'This sale has already been cancelled.',
                ], 422);
            }

            return back()->with('error', 'This sale has already been cancelled.');
        }

        // 5. Execute cancellation atomically
        DB::transaction(function () use ($sale, $user, $validated) {
            $sale->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancelled_by' => $user->id,
                'cancellation_reason' => $validated['reason'],
            ]);

            AuditLog::record('sale.cancelled', [
                'sale_id' => $sale->id,
                'sale_number' => $sale->sale_number,
                'branch_id' => $sale->branch_id,
                'total_amount' => $sale->total_amount,
                'cashier_id' => $sale->user_id,
                'cancelled_by_id' => $user->id,
                'cancellation_reason' => $validated['reason'],
            ], $user);
        });

        $message = "Sale #{$sale->sale_number} has been cancelled successfully.";

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => $message,
                'sale' => $this->formatSaleDetailPayload($sale->fresh(['branch', 'user', 'canceller', 'payments', 'items']), $user),
            ]);
        }

        return back()->with('success', $message);
    }

    /**
     * Format rich sale detail payload.
     *
     * @return array<string, mixed>
     */
    protected function formatSaleDetailPayload(Sale $sale, User $currentUser): array
    {
        return [
            'id' => $sale->id,
            'uuid' => $sale->uuid,
            'sale_number' => $sale->sale_number,
            'status' => $sale->status,
            'payment_status' => $sale->payment_status,
            'customer_name' => $sale->customer_name,
            'customer_phone' => $sale->customer_phone,
            'notes' => $sale->notes,
            'subtotal' => (float) $sale->subtotal,
            'discount_total' => (float) $sale->discount_total,
            'tax_total' => (float) $sale->tax_total,
            'total_amount' => (float) $sale->total_amount,
            'completed_at' => $sale->completed_at?->toIso8601String(),
            'completed_at_formatted' => $sale->completed_at?->format('M d, Y h:i A'),
            'cancelled_at' => $sale->cancelled_at?->toIso8601String(),
            'cancelled_at_formatted' => $sale->cancelled_at?->format('M d, Y h:i A'),
            'cancellation_reason' => $sale->cancellation_reason,
            'canceller' => $sale->canceller ? [
                'id' => $sale->canceller->id,
                'name' => $sale->canceller->name,
                'email' => $sale->canceller->email,
            ] : null,
            'can_cancel' => $sale->canBeCancelled() && $this->userCanCancel($currentUser, $sale->branch_id),
            'branch' => [
                'id' => $sale->branch?->id,
                'name' => $sale->branch?->name,
                'code' => $sale->branch?->code,
                'address' => $sale->branch?->address,
                'phone' => $sale->branch?->phone,
                'email' => $sale->branch?->email,
            ],
            'cashier' => [
                'id' => $sale->user?->id,
                'name' => $sale->user?->name,
                'email' => $sale->user?->email,
            ],
            'items' => $sale->items->map(fn ($item) => [
                'id' => $item->id,
                'uuid' => $item->uuid,
                'product_id' => $item->product_id,
                'product_name' => $item->product_name,
                'sku' => $item->sku,
                'barcode' => $item->barcode,
                'unit' => $item->unit,
                'quantity' => (float) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'discount_amount' => (float) $item->discount_amount,
                'is_taxable' => (bool) $item->is_taxable,
                'tax_rate' => (float) $item->tax_rate,
                'tax_amount' => (float) $item->tax_amount,
                'subtotal' => (float) $item->subtotal,
                'total' => (float) $item->total,
                'notes' => $item->notes,
            ]),
            'payments' => $sale->payments->map(fn ($payment) => [
                'id' => $payment->id,
                'uuid' => $payment->uuid,
                'payment_method' => $payment->payment_method,
                'amount' => (float) $payment->amount,
                'tendered_amount' => $payment->tendered_amount !== null ? (float) $payment->tendered_amount : null,
                'change_amount' => (float) $payment->change_amount,
                'reference_note' => $payment->reference_note,
                'status' => $payment->status,
                'created_at' => $payment->created_at?->format('M d, Y h:i A'),
            ]),
        ];
    }

    /**
     * Format receipt snapshot for browser and thermal printing.
     * Uses strictly stored snapshots without recalculating historical values.
     *
     * @return array<string, mixed>
     */
    protected function formatReceiptPayload(Sale $sale): array
    {
        $settings = $this->settingService->getAll();
        $branch = $sale->branch;

        $effectiveHeader = $branch?->receipt_header ?: $settings['receipt_header'];
        $effectiveFooter = $branch?->receipt_footer ?: $settings['receipt_footer'];

        $tenderedTotal = 0.0;
        $changeTotal = 0.0;
        foreach ($sale->payments as $p) {
            if ($p->tendered_amount !== null) {
                $tenderedTotal += (float) $p->tendered_amount;
            }
            $changeTotal += (float) $p->change_amount;
        }

        return [
            'business' => [
                'name' => $settings['business_name'],
                'logo_path' => $settings['business_logo_path'],
                'show_logo' => (bool) $settings['receipt_show_logo'],
                'tax_id' => $settings['tax_id_number'],
            ],
            'branch' => [
                'name' => $branch?->name ?? 'StoreFleet Branch',
                'code' => $branch?->code ?? '',
                'address' => $branch?->address ?? '',
                'phone' => $branch?->phone ?? '',
                'email' => $branch?->email ?? '',
            ],
            'sale' => [
                'id' => $sale->id,
                'sale_number' => $sale->sale_number,
                'completed_at' => $sale->completed_at?->format('Y-m-d H:i:s'),
                'completed_at_formatted' => $sale->completed_at?->format('M d, Y h:i A'),
                'status' => $sale->status,
                'is_cancelled' => $sale->isCancelled(),
                'cancelled_at' => $sale->cancelled_at?->format('M d, Y h:i A'),
                'cancellation_reason' => $sale->cancellation_reason,
                'customer_name' => $sale->customer_name,
                'customer_phone' => $sale->customer_phone,
                'notes' => $sale->notes,
                'cashier_name' => $sale->user?->name ?? 'Cashier',
            ],
            'header' => $effectiveHeader,
            'footer' => $effectiveFooter,
            'currency' => [
                'symbol' => $settings['currency_symbol'],
                'code' => $settings['currency_code'],
                'precision' => (int) $settings['currency_precision'],
                'position' => $settings['currency_symbol_position'],
            ],
            'items' => $sale->items->map(fn ($item) => [
                'name' => $item->product_name,
                'sku' => $item->sku,
                'barcode' => $item->barcode,
                'unit' => $item->unit,
                'quantity' => (float) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'discount' => (float) $item->discount_amount,
                'tax_rate' => (float) $item->tax_rate,
                'tax_amount' => (float) $item->tax_amount,
                'subtotal' => (float) $item->subtotal,
                'total' => (float) $item->total,
                'notes' => $item->notes,
            ])->all(),
            'totals' => [
                'subtotal' => (float) $sale->subtotal,
                'discount_total' => (float) $sale->discount_total,
                'tax_total' => (float) $sale->tax_total,
                'total_amount' => (float) $sale->total_amount,
                'tendered_total' => $tenderedTotal > 0 ? $tenderedTotal : (float) $sale->total_amount,
                'change_total' => $changeTotal,
            ],
            'payments' => $sale->payments->map(fn ($p) => [
                'method' => strtoupper(str_replace('_', ' ', $p->payment_method)),
                'amount' => (float) $p->amount,
                'tendered' => $p->tendered_amount !== null ? (float) $p->tendered_amount : null,
                'change' => (float) $p->change_amount,
                'reference' => $p->reference_note,
            ])->all(),
        ];
    }
}
