<?php

declare(strict_types=1);

namespace HyperfTest\Cases;

use GuzzleHttp\Client;
use Hyperf\Testing\TestCase;

class Part01EnvironmentTest extends TestCase
{
    public function testPart01ApiHealthEndpointIsUp(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Tecno Pix')
            ->assertSee('/swagger')
            ->assertSee('/dashboard');
    }

    public function testPart01SwaggerAndSpecEndpointsAreReachable(): void
    {
        $http = new Client([
            'base_uri' => 'http://127.0.0.1:9500',
            'http_errors' => false,
            'timeout' => 5.0,
        ]);

        $swaggerResponse = $http->get('/swagger');
        self::assertSame(200, $swaggerResponse->getStatusCode());
        self::assertStringContainsString('swagger-ui', (string) $swaggerResponse->getBody());

        $specResponse = $http->get('/http.json');
        self::assertSame(200, $specResponse->getStatusCode());
        self::assertStringContainsString('"openapi":"3.0.0"', (string) $specResponse->getBody());
    }
}
