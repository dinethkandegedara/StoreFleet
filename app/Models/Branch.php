<?php

namespace App\Models;

use App\Services\BusinessSettingService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'address',
        'city',
        'state',
        'postal_code',
        'country',
        'phone',
        'email',
        'manager_name',
        'receipt_header',
        'receipt_footer',
        'is_active',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    /**
     * Get effective receipt header (branch override or business default).
     */
    public function getEffectiveReceiptHeader(): string
    {
        return $this->receipt_header ?: (string) app(BusinessSettingService::class)->get('receipt_header', '');
    }

    /**
     * Get effective receipt footer (branch override or business default).
     */
    public function getEffectiveReceiptFooter(): string
    {
        return $this->receipt_footer ?: (string) app(BusinessSettingService::class)->get('receipt_footer', '');
    }

    public function branchUserRoles(): HasMany
    {
        return $this->hasMany(BranchUserRole::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'branch_user_role')
            ->withPivot('role_id')
            ->withTimestamps();
    }
}
