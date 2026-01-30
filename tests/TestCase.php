<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Tests;

use ChangHorizon\ContentCollector\ContentCollectorServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app)
    {
        return [
            ContentCollectorServiceProvider::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}
