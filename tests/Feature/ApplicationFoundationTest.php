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
     * Test health check endpoint responds with 200.
     */
    public function test_health_check_endpoint(): void
    {
        $response = $this->get('/up');

        $response->assertStatus(200);
    }
}
