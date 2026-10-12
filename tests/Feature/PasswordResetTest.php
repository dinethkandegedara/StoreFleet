<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Auth/ForgotPassword'));
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        $user = User::factory()->create([
            'email' => 'operator@storefleet.test',
        ]);

        $response = $this->post('/forgot-password', [
            'email' => 'operator@storefleet.test',
        ]);

        $response->assertSessionHas('status');

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'auth.password_reset_requested',
        ]);
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        $user = User::factory()->create([
            'email' => 'operator@storefleet.test',
            'password' => 'OldPassword123!',
        ]);

        $token = Password::createToken($user);

        $response = $this->post('/reset-password', [
            'token' => $token,
            'email' => 'operator@storefleet.test',
            'password' => 'NewSecurePassword123!',
            'password_confirmation' => 'NewSecurePassword123!',
        ]);

        $response->assertRedirect(route('login'));

        $user->refresh();
        $this->assertTrue(Hash::check('NewSecurePassword123!', $user->password));

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'event' => 'auth.password_reset_completed',
        ]);
    }
}
