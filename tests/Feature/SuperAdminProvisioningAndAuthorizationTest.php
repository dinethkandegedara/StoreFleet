<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SuperAdminProvisioningAndAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_be_provisioned_via_artisan_command(): void
    {
        $this->artisan('storefleet:super-admin:provision')
            ->expectsQuestion('Full Name', 'Platform Director')
            ->expectsQuestion('Email Address', 'director@storefleet.test')
            ->expectsQuestion('Password (min 10 characters, mixed case, numbers)', 'P@ssw0rdSecure!')
            ->expectsQuestion('Confirm Password', 'P@ssw0rdSecure!')
            ->assertSuccessful();

        $this->assertDatabaseHas('users', [
            'name' => 'Platform Director',
            'email' => 'director@storefleet.test',
            'is_super_admin' => true,
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        $superAdmin = User::where('email', 'director@storefleet.test')->firstOrFail();
        $this->assertTrue(Hash::check('P@ssw0rdSecure!', $superAdmin->password));
        $this->assertNotEmpty($superAdmin->two_factor_secret);
        $this->assertCount(8, $superAdmin->two_factor_recovery_codes);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $superAdmin->id,
            'event' => 'super_admin.provisioned',
        ]);
    }

    public function test_provision_command_refuses_duplicate_execution_without_force(): void
    {
        User::factory()->create([
            'is_super_admin' => true,
            'email' => 'existing@storefleet.test',
        ]);

        $this->artisan('storefleet:super-admin:provision')
            ->assertFailed();
    }

    public function test_super_admin_can_access_audit_logs_explorer(): void
    {
        $superAdmin = User::factory()->create([
            'is_super_admin' => true,
            'is_active' => true,
        ]);

        AuditLog::log('test.event', $superAdmin->id, ['info' => 'sample']);

        $response = $this->actingAs($superAdmin)->get('/admin/audit-logs');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/AuditLogs')
            ->has('logs.data')
        );
    }

    public function test_non_super_admin_receives_403_forbidden_on_audit_logs(): void
    {
        $operationalUser = User::factory()->create([
            'is_super_admin' => false,
            'role' => 'cashier',
            'is_active' => true,
        ]);

        $response = $this->actingAs($operationalUser)->get('/admin/audit-logs');

        $response->assertStatus(403);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $operationalUser->id,
            'event' => 'auth.unauthorized_super_admin_attempt',
        ]);
    }

    public function test_guest_is_redirected_to_login_from_admin_routes(): void
    {
        $response = $this->get('/admin/audit-logs');

        $response->assertRedirect('/login');
    }

    public function test_is_super_admin_cannot_be_mass_assigned_by_user_input(): void
    {
        $user = User::create([
            'name' => 'Sneaky Operator',
            'email' => 'sneaky@storefleet.test',
            'password' => 'Pass12345!',
            'is_super_admin' => true, // Attempting to escalate privileges via mass-assignment
        ]);

        $user->refresh();
        $this->assertFalse($user->is_super_admin);
        $this->assertFalse($user->isSuperAdmin());

        $user->update([
            'is_super_admin' => true,
        ]);

        $user->refresh();
        $this->assertFalse($user->is_super_admin);
    }
}
