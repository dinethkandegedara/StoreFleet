<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ProductVariant extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'product_id',
        'name',
        'sku',
        'barcode',
        'selling_price',
        'cost_price',
        'image_path',
        'attributes',
        'is_active',
    ];

    protected $casts = [
        'selling_price' => 'decimal:4',
        'cost_price' => 'decimal:4',
        'attributes' => 'array',
        'is_active' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (ProductVariant $variant) {
            if (empty($variant->uuid)) {
                $variant->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Parent product.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
