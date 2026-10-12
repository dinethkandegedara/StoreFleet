<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SaleItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'sale_id',
        'product_id',
        'product_variant_id',
        'product_name',
        'sku',
        'barcode',
        'unit',
        'quantity',
        'unit_price',
        'cost_price',
        'discount_amount',
        'is_taxable',
        'tax_class',
        'tax_rate',
        'tax_amount',
        'subtotal',
        'total',
        'notes',
    ];

    protected $casts = [
        'sale_id' => 'integer',
        'product_id' => 'integer',
        'product_variant_id' => 'integer',
        'quantity' => 'decimal:3',
        'unit_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'is_taxable' => 'boolean',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (SaleItem $item) {
            if (empty($item->uuid)) {
                $item->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Parent sale transaction.
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * Associated product definition.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Associated variant definition.
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
