<?php

namespace Tests;

use App\Contracts\Repositories\AmenityRepositoryInterface;
use App\Repositories\AmenityRepository;
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
        
        // Bind the repository interface to its implementation
        $this->app->bind(
            AmenityRepositoryInterface::class,
            AmenityRepository::class
        );
    }
}
