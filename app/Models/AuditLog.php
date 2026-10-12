<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'event',
        'ip_address',
        'user_agent',
        'payload',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Record an audit event securely.
     */
    public static function log(string $event, ?int $userId = null, ?array $payload = null, ?Request $request = null): self
    {
        $request = $request ?? request();

        // Sanitize payload to guarantee no passwords or secrets are ever stored
        $safePayload = $payload ?? [];
        unset(
            $safePayload['password'],
            $safePayload['password_confirmation'],
            $safePayload['current_password'],
            $safePayload['two_factor_secret'],
            $safePayload['recovery_code'],
            $safePayload['code']
        );

        return static::create([
            'user_id' => $userId,
            'event' => $event,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? substr((string) $request->userAgent(), 0, 500) : null,
            'payload' => empty($safePayload) ? null : $safePayload,
            'created_at' => now(),
        ]);
    }
}
