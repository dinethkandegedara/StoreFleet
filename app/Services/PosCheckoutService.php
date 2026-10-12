<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PosCheckoutService
{
    public function __construct(
        protected BusinessSettingService $settingService
    ) {}

    /**
     * Process checkout transaction authoritatively.
     *
     * @param  User  $user  Authenticated operational user/cashier.
     * @param  int  $branchId  Active branch identifier.
     * @param  array<string, mixed>  $payload  Cart items, discounts, customer info, payments, idempotency key.
     * @return Sale Completed sale with loaded items and payments.
     *
     * @throws ValidationException
     */
    public function processCheckout(User $user, int $branchId, array $payload): Sale
    {
        // 1. Validate branch exists and is active
        $branch = Branch::where('id', $branchId)->where('is_active', true)->first();
        if (! $branch) {
            throw ValidationException::withMessages([
                'branch' => 'Selected branch is invalid or currently inactive.',
            ]);
        }

        // 2. Validate user branch authorization
        if (! $user->isSuperAdmin() && ! $user->isAuthorizedForBranch($branchId)) {
            throw ValidationException::withMessages([
                'branch' => 'You are not authorized to process transactions in this branch.',
            ]);
        }

        // 3. Idempotency Check: prevent duplicate sales on network retries or double-clicks
        $idempotencyKey = (string) ($payload['idempotency_key'] ?? '');
        if (empty($idempotencyKey)) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'An idempotency key is required to protect against duplicate sales.',
            ]);
        }

        $existingSale = Sale::with(['items', 'payments', 'branch', 'user'])
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existingSale) {
            return $existingSale;
        }

        // 4. Validate raw items presence
        $rawItems = $payload['items'] ?? [];
        if (empty($rawItems) || ! is_array($rawItems)) {
            throw ValidationException::withMessages([
                'items' => 'Cart is empty. Add at least one item before checkout.',
            ]);
        }

        $settings = $this->settingService->getAll();
        $processedItems = [];

        // 5. Authoritative recalculation of each cart item
        foreach ($rawItems as $idx => $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $product = Product::where('id', $productId)->first();

            if (! $product || ! $product->is_active) {
                throw ValidationException::withMessages([
                    "items.{$idx}" => 'One or more items in the cart are discontinued or unavailable.',
                ]);
            }

            $variant = null;
            if (! empty($item['product_variant_id'])) {
                $variant = ProductVariant::where('id', $item['product_variant_id'])
                    ->where('product_id', $product->id)
                    ->where('is_active', true)
                    ->first();

                if (! $variant) {
                    throw ValidationException::withMessages([
                        "items.{$idx}" => "Selected variant for \"{$product->name}\" is invalid or inactive.",
                    ]);
                }
            }

            $quantity = (float) ($item['quantity'] ?? 0);
            if ($quantity <= 0) {
                throw ValidationException::withMessages([
                    "items.{$idx}.quantity" => "Quantity for \"{$product->name}\" must be greater than zero.",
                ]);
            }

            // Authoritative server-side price (never trusted from frontend)
            $unitPrice = (float) ($variant ? $variant->selling_price : $product->selling_price);
            $costPrice = $variant ? $variant->cost_price : $product->cost_price;
            $lineDiscount = round(max(0, (float) ($item['discount_amount'] ?? 0)), 2);

            $grossLine = round($unitPrice * $quantity, 4);
            if ($lineDiscount > $grossLine) {
                throw ValidationException::withMessages([
                    "items.{$idx}.discount_amount" => "Discount for \"{$product->name}\" cannot exceed the line total.",
                ]);
            }

            $netLine = max(0, $grossLine - $lineDiscount);

            // Authoritative tax determination
            $isTaxable = (bool) ($product->is_taxable && $settings['tax_enabled'] && $product->tax_class !== 'exempt');
            $taxRate = 0.00;
            $lineTax = 0.00;
            $lineSubtotal = 0.00;
            $lineTotal = 0.00;

            if ($isTaxable) {
                $taxRate = (float) ($product->tax_rate > 0 ? $product->tax_rate : $settings['default_tax_rate']);

                if (! empty($settings['prices_include_tax'])) {
                    // Tax inclusive: tax is embedded inside the price
                    $lineTax = round($netLine - ($netLine / (1 + ($taxRate / 100))), 2);
                    $lineSubtotal = round($netLine - $lineTax, 2);
                    $lineTotal = round($netLine, 2);
                } else {
                    // Tax exclusive: tax added on top
                    $lineTax = round($netLine * ($taxRate / 100), 2);
                    $lineSubtotal = round($netLine, 2);
                    $lineTotal = round($lineSubtotal + $lineTax, 2);
                }
            } else {
                $lineSubtotal = round($netLine, 2);
                $lineTotal = round($netLine, 2);
            }

            $productDisplayName = $variant ? "{$product->name} - {$variant->name}" : $product->name;
            $sku = $variant ? $variant->sku : $product->sku;
            $barcode = $variant ? ($variant->barcode ?: $product->barcode) : $product->barcode;

            $processedItems[] = [
                'product_id' => $product->id,
                'product_variant_id' => $variant?->id,
                'product_name' => $productDisplayName,
                'sku' => $sku,
                'barcode' => $barcode,
                'unit' => $product->unit ?: 'piece',
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'cost_price' => $costPrice ? (float) $costPrice : null,
                'discount_amount' => $lineDiscount,
                'is_taxable' => $isTaxable,
                'tax_class' => $product->tax_class,
                'tax_rate' => $taxRate,
                'tax_amount' => $lineTax,
                'subtotal' => $lineSubtotal,
                'total' => $lineTotal,
                'notes' => $item['notes'] ?? null,
            ];
        }

        // 6. Aggregate monetary calculations
        $orderDiscount = round(max(0, (float) ($payload['discount_total'] ?? 0)), 2);
        $subtotalSum = round(array_sum(array_column($processedItems, 'subtotal')), 2);
        $taxSum = round(array_sum(array_column($processedItems, 'tax_amount')), 2);
        $grossTotal = round(array_sum(array_column($processedItems, 'total')), 2);
        $finalTotal = max(0, round($grossTotal - $orderDiscount, 2));

        // 7. Validate payments
        $rawPayments = $payload['payments'] ?? [];
        if (empty($rawPayments) || ! is_array($rawPayments)) {
            throw ValidationException::withMessages([
                'payments' => 'At least one payment entry is required to complete checkout.',
            ]);
        }

        $processedPayments = [];
        $totalPaid = 0.00;

        foreach ($rawPayments as $pIdx => $payment) {
            $method = (string) ($payment['payment_method'] ?? 'cash');
            $amount = round((float) ($payment['amount'] ?? 0), 2);

            $enabledKey = "payment_{$method}_enabled";
            if (array_key_exists($enabledKey, $settings) && ! (bool) $settings[$enabledKey]) {
                throw ValidationException::withMessages([
                    "payments.{$pIdx}.payment_method" => "Payment method '{$method}' is currently disabled in business configuration.",
                ]);
            }

            if ($amount <= 0 && $finalTotal > 0) {
                throw ValidationException::withMessages([
                    "payments.{$pIdx}.amount" => 'Payment amount must be greater than zero.',
                ]);
            }

            $tendered = null;
            $change = 0.00;

            if ($method === 'cash') {
                $tendered = round((float) ($payment['tendered_amount'] ?? $amount), 2);
                if ($tendered < $amount) {
                    throw ValidationException::withMessages([
                        "payments.{$pIdx}.tendered_amount" => "Tendered cash (\${$tendered}) is less than payment amount (\${$amount}).",
                    ]);
                }
                $change = round($tendered - $amount, 2);
            }

            $totalPaid = round($totalPaid + $amount, 2);

            $processedPayments[] = [
                'payment_method' => $method,
                'amount' => $amount,
                'tendered_amount' => $tendered,
                'change_amount' => $change,
                'reference_note' => $payment['reference_note'] ?? null,
                'status' => 'completed',
            ];
        }

        if ($totalPaid < $finalTotal) {
            throw ValidationException::withMessages([
                'payments' => "Total payment (\${$totalPaid}) does not cover the final sale total (\${$finalTotal}).",
            ]);
        }

        // 8. Atomic Database Transaction: commit all or rollback completely on error
        return DB::transaction(function () use (
            $branch,
            $user,
            $payload,
            $idempotencyKey,
            $subtotalSum,
            $orderDiscount,
            $taxSum,
            $finalTotal,
            $processedItems,
            $processedPayments
        ) {
            // Sequential human-readable sale number
            $saleNumber = $this->generateSequentialSaleNumber($branch);

            $sale = Sale::create([
                'sale_number' => $saleNumber,
                'branch_id' => $branch->id,
                'user_id' => $user->id,
                'customer_name' => $payload['customer_name'] ?? null,
                'customer_phone' => $payload['customer_phone'] ?? null,
                'status' => 'completed',
                'subtotal' => $subtotalSum,
                'discount_total' => $orderDiscount,
                'tax_total' => $taxSum,
                'total_amount' => $finalTotal,
                'payment_status' => 'paid',
                'notes' => $payload['notes'] ?? null,
                'idempotency_key' => $idempotencyKey,
                'completed_at' => now(),
            ]);

            // Create immutable snapshots
            foreach ($processedItems as $itemData) {
                $sale->items()->create($itemData);
            }

            // Record payments
            foreach ($processedPayments as $paymentData) {
                $sale->payments()->create($paymentData);
            }

            // Audit log the sale creation
            AuditLog::log('sale.completed', $user->id, [
                'sale_id' => $sale->id,
                'sale_number' => $sale->sale_number,
                'branch_id' => $branch->id,
                'total' => $sale->total_amount,
                'items_count' => count($processedItems),
            ]);

            return $sale->load(['items', 'payments', 'branch', 'user']);
        });
    }

    /**
     * Generate sequential sale number (e.g. POS-MAIN-20261012-0001).
     */
    protected function generateSequentialSaleNumber(Branch $branch): string
    {
        $code = $branch->code ?: 'STORE';
        $date = date('Ymd');
        $prefix = "POS-{$code}-{$date}";

        $latestSale = Sale::where('sale_number', 'like', "{$prefix}-%")
            ->lockForUpdate()
            ->orderBy('id', 'desc')
            ->first();

        $sequence = 1;
        if ($latestSale) {
            $parts = explode('-', $latestSale->sale_number);
            $lastSeq = (int) end($parts);
            $sequence = $lastSeq + 1;
        }

        $saleNumber = sprintf('%s-%04d', $prefix, $sequence);

        while (Sale::where('sale_number', $saleNumber)->exists()) {
            $sequence++;
            $saleNumber = sprintf('%s-%04d', $prefix, $sequence);
        }

        return $saleNumber;
    }
}
