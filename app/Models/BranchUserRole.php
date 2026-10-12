<?php

namespace App\Models;

use App\Services\PermissionRegistrar;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchUserRole extends Model
{
    use HasFactory;

    protected $table = 'branch_user_role';

    protected $fillable = [
        'user_id',
        'role_id',
        'branch_id',
    ];

    protected static function booted(): void
    {
        static::saved(function ($model) {
            app(PermissionRegistrar::class)->clearUserPermissionCache($model->user_id);
        });

        static::deleted(function ($model) {
            app(PermissionRegistrar::class)->clearUserPermissionCache($model->user_id);
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
