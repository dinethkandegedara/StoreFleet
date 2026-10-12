<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
