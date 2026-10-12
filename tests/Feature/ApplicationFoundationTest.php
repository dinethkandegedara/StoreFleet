<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ApplicationFoundationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that the root route renders the Welcome Inertia view with required diagnostic props.
     */
    public function test_welcome_page_renders_with_inertia(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Welcome')
            ->has('appName')
            ->has('phpVersion')
            ->has('laravelVersion')
            ->has('dbDriver')
            ->has('dbConnected')
            ->has('appEnv')
            ->where('dbConnected', true)
        );
    }

    /**
     * Test that the HandleInertiaRequests middleware shares standard props.
     */
    public function test_inertia_shared_props(): void
    {
        $response = $this->get('/');

        $response->assertInertia(fn (Assert $page) => $page
            ->has('appName')
            ->has('flash')
            ->has('auth')
        );
    }

    /**
     * Test flash messages are shared to the Inertia payload for toast display.
     */
    public function test_flash_messages_are_shared_for_toast_notifications(): void
    {
        $response = $this->withSession([
            'success' => 'Item saved successfully.',
            'error' => 'Unable to proceed with action.',
        ])->get('/');

        $response->assertInertia(fn (Assert $page) => $page
            ->where('flash.success', 'Item saved successfully.')
            ->where('flash.error', 'Unable to proceed with action.')
        );
    }

    /**
     * Test that compiled frontend assets manifest exists and contains entrypoints.
     */
    public function test_frontend_build_manifest_exists(): void
    {
        $manifestPath = public_path('build/manifest.json');

        $this->assertFileExists($manifestPath, 'Vite production build manifest must exist.');

        $manifestContent = json_decode((string) file_get_contents($manifestPath), true);

        $this->assertIsArray($manifestContent);
        $this->assertArrayHasKey('resources/js/app.ts', $manifestContent);
        $this->assertArrayHasKey('resources/css/app.css', $manifestContent);
    }

    /**
     * Test health check endpoint responds with 200.
     */
    public function test_health_check_endpoint(): void
    {
        $response = $this->get('/up');

        $response->assertStatus(200);
    }
}
