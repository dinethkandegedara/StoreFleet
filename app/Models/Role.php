<?php

namespace App\Models;

use App\Services\PermissionRegistrar;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_system',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function () {
            app(PermissionRegistrar::class)->clearPermissionCache();
        });

        static::deleted(function () {
            app(PermissionRegistrar::class)->clearPermissionCache();
        });
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permission');
    }

    public function branchUserRoles(): HasMany
    {
        return $this->hasMany(BranchUserRole::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'branch_user_role')
            ->withPivot('branch_id')
            ->withTimestamps();
    }

    public function isSystem(): bool
    {
        return (bool) $this->is_system;
    }

    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    public function hasPermission(string $permissionSlug): bool
    {
        return $this->permissions->contains('slug', $permissionSlug);
    }
}
