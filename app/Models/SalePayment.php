<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SalePayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'sale_id',
        'payment_method',
        'amount',
        'tendered_amount',
        'change_amount',
        'reference_note',
        'status',
    ];

    protected $casts = [
        'sale_id' => 'integer',
        'amount' => 'decimal:2',
        'tendered_amount' => 'decimal:2',
        'change_amount' => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (SalePayment $payment) {
            if (empty($payment->uuid)) {
                $payment->uuid = (string) Str::uuid();
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
}
