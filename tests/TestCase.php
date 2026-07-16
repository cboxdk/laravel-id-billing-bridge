<?php

declare(strict_types=1);

namespace Cbox\Id\BillingBridge\Tests;

use Cbox\Id\BillingBridge\BillingBridgeServiceProvider;
use Illuminate\Support\ServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * @return list<class-string<ServiceProvider>>
     */
    protected function getPackageProviders($app): array
    {
        return [BillingBridgeServiceProvider::class];
    }
}
