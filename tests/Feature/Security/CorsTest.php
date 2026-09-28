<?php

namespace Tests\Feature\Security;

use Tests\TestCase;

class CorsTest extends TestCase
{
    public function test_foreign_origins_are_not_allowed(): void
    {
        $response = $this->withHeaders(['Origin' => 'https://evil.example'])->getJson('/api/health');

        $this->assertNotContains($response->headers->get('Access-Control-Allow-Origin'), ['*', 'https://evil.example']);
    }

    public function test_the_application_origin_is_allowed(): void
    {
        $origin = rtrim(config('app.url'), '/');

        $this->withHeaders(['Origin' => $origin])
            ->getJson('/api/health')
            ->assertHeader('Access-Control-Allow-Origin', $origin)
            ->assertHeader('Access-Control-Allow-Credentials', 'true');
    }
}
