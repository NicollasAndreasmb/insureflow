<?php

namespace Tests\Feature;

use Tests\TestCase;

class SpaRoutingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_root_serves_the_spa_shell(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('<div id="app"></div>', escape: false);
    }

    public function test_deep_links_are_delegated_to_the_spa(): void
    {
        $this->get('/clients/15')->assertOk()->assertViewIs('app');
    }

    public function test_unknown_api_routes_return_json_not_found(): void
    {
        $this->get('/api/does-not-exist')
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/json');
    }
}
