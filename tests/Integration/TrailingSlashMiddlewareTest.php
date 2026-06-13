<?php

declare(strict_types=1);

namespace Tests\Integration;

use Tests\WebTestCase;

class TrailingSlashMiddlewareTest extends WebTestCase
{
    public function testRewriteTrailingSlashOnGet(): void
    {
        $request = $this->createRequest('GET', '/v1/docs/');
        $response = $this->app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testNoRewriteWithoutTrailingSlash(): void
    {
        $request = $this->createRequest('GET', '/v1/docs');
        $response = $this->app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testRewriteTrailingSlashOnPost(): void
    {
        $request = $this->createRequest('POST', '/v1/auth/login/');
        $response = $this->app->handle($request);

        $this->assertNotEquals(404, $response->getStatusCode());
    }
}
