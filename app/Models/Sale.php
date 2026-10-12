<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Sale extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'sale_number',
        'branch_id',
        'user_id',
        'customer_name',
        'customer_phone',
        'status',
        'subtotal',
        'discount_total',
        'tax_total',
        'total_amount',
        'payment_status',
        'notes',
        'idempotency_key',
        'completed_at',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
    ];

    protected $casts = [
        'branch_id' => 'integer',
        'user_id' => 'integer',
        'cancelled_by' => 'integer',
        'subtotal' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (Sale $sale) {
            if (empty($sale->uuid)) {
                $sale->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Associated branch where the transaction occurred.
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Cashier / user who processed the checkout.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * User who authorized and performed the cancellation.
     */
    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /**
     * Check if the sale is cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled' || ! is_null($this->cancelled_at);
    }

    /**
     * Check if the sale can be cancelled.
     */
    public function canBeCancelled(): bool
    {
        return ! $this->isCancelled();
    }

    /**
     * Immutable line-item snapshots.
     */
    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    /**
     * Payment records for this sale.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class);
    }
}
