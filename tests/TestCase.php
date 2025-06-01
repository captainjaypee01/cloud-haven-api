<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Fixtures\TestClerkAuthMiddleware;

abstract class TestCase extends BaseTestCase
{

    protected function setUp(): void
    {
        parent::setUp();
        
        // Swap Clerk middleware with test version
        $this->app->instance(
            \App\Http\Middleware\ClerkAuthMiddleware::class,
            new TestClerkAuthMiddleware()
        );
    }
}
