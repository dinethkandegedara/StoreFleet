<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Sales Header
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('sale_number', 50)->unique();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('customer_name', 150)->nullable();
            $table->string('customer_phone', 50)->nullable();
            $table->string('status', 30)->default('completed'); // completed, voided
            $table->decimal('subtotal', 12, 4)->default(0.0000);
            $table->decimal('discount_total', 12, 4)->default(0.0000);
            $table->decimal('tax_total', 12, 4)->default(0.0000);
            $table->decimal('total_amount', 12, 4)->default(0.0000);
            $table->string('payment_status', 30)->default('paid'); // paid, partial, unpaid
            $table->text('notes')->nullable();
            $table->string('idempotency_key', 64)->unique();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'completed_at']);
            $table->index(['user_id', 'completed_at']);
            $table->index('status');
        });

        // 2. Immutable Line Item Snapshots
        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('product_name');
            $table->string('sku');
            $table->string('barcode')->nullable();
            $table->string('unit', 30)->default('piece');
            $table->decimal('quantity', 10, 3)->default(1.000);
            $table->decimal('unit_price', 12, 4);
            $table->decimal('cost_price', 12, 4)->nullable();
            $table->decimal('discount_amount', 12, 4)->default(0.0000);
            $table->boolean('is_taxable')->default(true);
            $table->string('tax_class', 30)->default('standard');
            $table->decimal('tax_rate', 5, 2)->default(0.00);
            $table->decimal('tax_amount', 12, 4)->default(0.0000);
            $table->decimal('subtotal', 12, 4);
            $table->decimal('total', 12, 4);
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index('sale_id');
            $table->index('product_id');
        });

        // 3. Payment Records
        Schema::create('sale_payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->string('payment_method', 30); // cash, card, bank_transfer, other
            $table->decimal('amount', 12, 4);
            $table->decimal('tendered_amount', 12, 4)->nullable();
            $table->decimal('change_amount', 12, 4)->default(0.0000);
            $table->string('reference_note')->nullable(); // card last4, authorization code, etc.
            $table->string('status', 30)->default('completed');
            $table->timestamps();

            $table->index('sale_id');
            $table->index('payment_method');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_payments');
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
    }
};
