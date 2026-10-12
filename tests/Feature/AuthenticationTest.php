<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Auth/Login'));
    }

    public function test_operational_user_can_authenticate(): void
    {
        $user = User::factory()->create([
            'email' => 'cashier@storefleet.test',
            'password' => 'SecurePass123!',
            'role' => 'cashier',
            'is_super_admin' => false,
            'is_active' => true,
        ]);

        $response = $this->post('/login', [
            'email' => 'cashier@storefleet.test',
            'password' => 'SecurePass123!',
        ]);

        $response->assertRedirect(route('welcome'));
        $this->assertAuthenticatedAs($user);

        // Verify last login tracking and audit logging
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'last_login_ip' => '127.0.0.1',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'event' => 'auth.login',
        ]);
    }

    public function test_user_cannot_authenticate_with_invalid_password(): void
    {
        User::factory()->create([
            'email' => 'cashier@storefleet.test',
            'password' => 'CorrectPassword123!',
            'is_active' => true,
        ]);

        $response = $this->from('/login')->post('/login', [
            'email' => 'cashier@storefleet.test',
            'password' => 'WrongPassword',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'auth.failed',
        ]);
    }

    public function test_login_attempts_are_throttled_after_five_failures(): void
    {
        User::factory()->create([
            'email' => 'cashier@storefleet.test',
            'password' => 'CorrectPassword123!',
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'email' => 'cashier@storefleet.test',
                'password' => 'WrongPassword',
            ]);
        }

        // 6th attempt should be throttled
        $response = $this->post('/login', [
            'email' => 'cashier@storefleet.test',
            'password' => 'WrongPassword',
        ]);

        $response->assertSessionHasErrors('email');

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'auth.throttled',
        ]);
    }

    public function test_deactivated_user_cannot_authenticate(): void
    {
        $user = User::factory()->create([
            'email' => 'inactive@storefleet.test',
            'password' => 'Password123!',
            'is_active' => false,
        ]);

        $response = $this->from('/login')->post('/login', [
            'email' => 'inactive@storefleet.test',
            'password' => 'Password123!',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'event' => 'auth.deactivated_login_attempt',
        ]);
    }

    public function test_authenticated_user_can_logout_and_session_is_invalidated(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
        ]);

        $this->actingAs($user);

        $response = $this->post('/logout');

        $response->assertRedirect('/login');
        $this->assertGuest();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'event' => 'auth.logout',
        ]);
    }
}
