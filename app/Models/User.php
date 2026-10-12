<?php

namespace App\Models;

use App\Services\PermissionRegistrar;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     * Note: 'is_super_admin' is intentionally NOT in fillable to prevent privilege escalation.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
        'last_login_at',
        'last_login_ip',
        'theme_preference',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
            'is_active' => 'boolean',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function branchUserRoles(): HasMany
    {
        return $this->hasMany(BranchUserRole::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'branch_user_role')
            ->withPivot('branch_id')
            ->withTimestamps();
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'branch_user_role')
            ->withPivot('role_id')
            ->withTimestamps();
    }

    public function hasPermission(string $permission, ?int $branchId = null): bool
    {
        return app(PermissionRegistrar::class)->userHasPermission($this, $permission, $branchId);
    }

    public function hasRole(string $role, ?int $branchId = null): bool
    {
        return app(PermissionRegistrar::class)->userHasRole($this, $role, $branchId);
    }

    public function getPermissions(?int $branchId = null): array
    {
        return app(PermissionRegistrar::class)->getUserPermissions($this, $branchId);
    }

    public function assignRole(Role|string|int $role, ?int $branchId = null): BranchUserRole
    {
        $roleId = $role instanceof Role ? $role->id : (is_numeric($role) ? (int) $role : Role::where('slug', $role)->firstOrFail()->id);

        return BranchUserRole::firstOrCreate([
            'user_id' => $this->id,
            'role_id' => $roleId,
            'branch_id' => $branchId,
        ]);
    }

    public function removeRole(Role|string|int $role, ?int $branchId = null): bool
    {
        $roleId = $role instanceof Role ? $role->id : (is_numeric($role) ? (int) $role : Role::where('slug', $role)->firstOrFail()->id);

        return (bool) BranchUserRole::where('user_id', $this->id)
            ->where('role_id', $roleId)
            ->where('branch_id', $branchId)
            ->delete();
    }

    public function isSuperAdmin(): bool
    {
        return (bool) $this->is_super_admin;
    }

    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    public function hasTwoFactorEnabled(): bool
    {
        return ! empty($this->two_factor_secret);
    }

    public function hasTwoFactorConfirmed(): bool
    {
        return $this->hasTwoFactorEnabled() && ! empty($this->two_factor_confirmed_at);
    }

    /**
     * Validate and consume a single-use recovery code.
     */
    public function consumeRecoveryCode(string $code): bool
    {
        $code = strtoupper(trim($code));
        $recoveryCodes = $this->two_factor_recovery_codes ?? [];

        if (! is_array($recoveryCodes) || empty($recoveryCodes)) {
            return false;
        }

        foreach ($recoveryCodes as $index => $storedCode) {
            if (hash_equals($storedCode, $code)) {
                unset($recoveryCodes[$index]);
                $this->forceFill([
                    'two_factor_recovery_codes' => array_values($recoveryCodes),
                ])->save();

                return true;
            }
        }

        return false;
    }
}
