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
    ];

    protected $casts = [
        'branch_id' => 'integer',
        'user_id' => 'integer',
        'subtotal' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'completed_at' => 'datetime',
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
