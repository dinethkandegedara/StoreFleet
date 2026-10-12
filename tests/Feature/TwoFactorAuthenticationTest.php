<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\TwoFactorAuthenticationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TwoFactorAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_login_triggers_mandatory_two_factor_challenge(): void
    {
        $twoFactorService = app(TwoFactorAuthenticationService::class);
        $secret = $twoFactorService->generateSecretKey();

        $superAdmin = User::factory()->create([
            'email' => 'superadmin@storefleet.test',
            'password' => 'SuperSecret123!',
            'is_super_admin' => true,
            'role' => 'super_admin',
            'is_active' => true,
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
        ]);

        $response = $this->post('/login', [
            'email' => 'superadmin@storefleet.test',
            'password' => 'SuperSecret123!',
        ]);

        $response->assertRedirect(route('two-factor.challenge'));
        $this->assertGuest();
        $this->assertEquals($superAdmin->id, session('auth.2fa.user_id'));

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $superAdmin->id,
            'event' => 'auth.2fa_challenge',
        ]);
    }

    public function test_two_factor_challenge_can_be_completed_with_valid_totp_code(): void
    {
        $twoFactorService = app(TwoFactorAuthenticationService::class);
        $secret = $twoFactorService->generateSecretKey();

        // Calculate current valid 6-digit TOTP code
        $currentTime = floor(time() / 30);
        $packed = pack('N*', 0).pack('N*', $currentTime);
        $reflection = new \ReflectionClass($twoFactorService);
        $decodeMethod = $reflection->getMethod('base32Decode');
        $decodeMethod->setAccessible(true);
        $binary = $decodeMethod->invoke($twoFactorService, $secret);

        $hash = hash_hmac('sha1', $packed, $binary, true);
        $offset = ord($hash[19]) & 0x0F;
        $truncated = (
            ((ord($hash[$offset]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF)
        );
        $validCode = str_pad((string) ($truncated % 1000000), 6, '0', STR_PAD_LEFT);

        $superAdmin = User::factory()->create([
            'is_super_admin' => true,
            'is_active' => true,
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
        ]);

        $response = $this->withSession(['auth.2fa.user_id' => $superAdmin->id])
            ->post('/two-factor-challenge', [
                'code' => $validCode,
            ]);

        $response->assertRedirect(route('welcome'));
        $this->assertAuthenticatedAs($superAdmin);
        $this->assertNull(session('auth.2fa.user_id'));

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $superAdmin->id,
            'event' => 'auth.login',
        ]);
    }

    public function test_two_factor_challenge_fails_with_invalid_totp_code(): void
    {
        $superAdmin = User::factory()->create([
            'is_super_admin' => true,
            'is_active' => true,
            'two_factor_secret' => 'JBSWY3DPEHPK3PXP',
            'two_factor_confirmed_at' => now(),
        ]);

        $response = $this->withSession(['auth.2fa.user_id' => $superAdmin->id])
            ->from('/two-factor-challenge')
            ->post('/two-factor-challenge', [
                'code' => '000000',
            ]);

        $response->assertSessionHasErrors('code');
        $this->assertGuest();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $superAdmin->id,
            'event' => 'auth.2fa_failed',
        ]);
    }

    public function test_two_factor_challenge_can_be_completed_with_single_use_recovery_code(): void
    {
        $superAdmin = User::factory()->create([
            'is_super_admin' => true,
            'is_active' => true,
            'two_factor_secret' => 'JBSWY3DPEHPK3PXP',
            'two_factor_recovery_codes' => ['CODE-ONE-1111', 'CODE-TWO-2222'],
            'two_factor_confirmed_at' => now(),
        ]);

        $response = $this->withSession(['auth.2fa.user_id' => $superAdmin->id])
            ->post('/two-factor-challenge', [
                'recovery_code' => 'CODE-ONE-1111',
            ]);

        $response->assertRedirect(route('welcome'));
        $this->assertAuthenticatedAs($superAdmin);

        // Verify that CODE-ONE-1111 is burned and removed from the database
        $superAdmin->refresh();
        $this->assertNotContains('CODE-ONE-1111', $superAdmin->two_factor_recovery_codes);
        $this->assertContains('CODE-TWO-2222', $superAdmin->two_factor_recovery_codes);
        $this->assertCount(1, $superAdmin->two_factor_recovery_codes);
    }

    public function test_consumed_recovery_code_cannot_be_reused(): void
    {
        $superAdmin = User::factory()->create([
            'is_super_admin' => true,
            'is_active' => true,
            'two_factor_secret' => 'JBSWY3DPEHPK3PXP',
            'two_factor_recovery_codes' => ['CODE-TWO-2222'],
            'two_factor_confirmed_at' => now(),
        ]);

        $response = $this->withSession(['auth.2fa.user_id' => $superAdmin->id])
            ->from('/two-factor-challenge')
            ->post('/two-factor-challenge', [
                'recovery_code' => 'CODE-ONE-1111', // Not present
            ]);

        $response->assertSessionHasErrors('recovery_code');
        $this->assertGuest();
    }
}
