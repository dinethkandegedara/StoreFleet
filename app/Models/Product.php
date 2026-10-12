<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'sku',
        'barcode',
        'category_id',
        'brand',
        'product_type',
        'unit',
        'selling_price',
        'cost_price',
        'is_taxable',
        'tax_class',
        'tax_rate',
        'image_path',
        'description',
        'notes',
        'tags',
        'has_variants',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'selling_price' => 'decimal:4',
        'cost_price' => 'decimal:4',
        'is_taxable' => 'boolean',
        'tax_rate' => 'decimal:2',
        'tags' => 'array',
        'has_variants' => 'boolean',
        'is_active' => 'boolean',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    protected $appends = [
        'image_url',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (Product $product) {
            if (empty($product->uuid)) {
                $product->uuid = (string) Str::uuid();
            }

            if (empty($product->slug)) {
                $base = Str::slug($product->name);
                $slug = $base;
                $counter = 1;
                while (static::where('slug', $slug)->exists()) {
                    $slug = $base.'-'.$counter++;
                }
                $product->slug = $slug;
            }
        });
    }

    /**
     * Category relationship.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    /**
     * Product variants relationship.
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /**
     * Creator audit relation.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Updater audit relation.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get publicly accessible image URL.
     */
    public function getImageUrlAttribute(): ?string
    {
        if (! $this->image_path) {
            return null;
        }

        return Storage::disk('public')->url($this->image_path);
    }

    /**
     * Scope only active products.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Fast indexed search by name, SKU, barcode, or brand.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (empty($term)) {
            return $query;
        }

        $term = trim($term);

        return $query->where(function (Builder $q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('sku', 'like', "%{$term}%")
                ->orWhere('barcode', 'like', "%{$term}%")
                ->orWhere('brand', 'like', "%{$term}%");
        });
    }

    /**
     * Scope by category (including subcategories if needed).
     */
    public function scopeInCategory(Builder $query, ?int $categoryId): Builder
    {
        if (! $categoryId) {
            return $query;
        }

        return $query->where('category_id', $categoryId);
    }

    /**
     * Check if product has historical sales transactions.
     * Guaranteed safe compatibility hook for future sales modules.
     */
    public function hasHistoricalTransactions(): bool
    {
        // Future sales tables (e.g. order_items, sale_items) check
        return false;
    }
}
