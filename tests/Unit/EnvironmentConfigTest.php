<?php

namespace Tests\Unit;

use Tests\TestCase;

class EnvironmentConfigTest extends TestCase
{
    /**
     * Test that essential application configurations for shared hosting exist.
     */
    public function test_essential_configurations_are_set(): void
    {
        $this->assertNotEmpty(config('app.name'), 'Application name must be set.');
        $this->assertContains(config('session.driver'), ['database', 'file', 'cookie', 'array']);
        $this->assertContains(config('queue.default'), ['database', 'sync']);
        $this->assertContains(config('cache.default'), ['database', 'file', 'array']);
        $this->assertContains(config('database.default'), ['sqlite', 'mysql', 'mariadb']);
    }

    /**
     * Test that filesystems disk defaults to local storage.
     */
    public function test_filesystem_default_disk_is_local(): void
    {
        $this->assertEquals('local', config('filesystems.default'));
    }
}
