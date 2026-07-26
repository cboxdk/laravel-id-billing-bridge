<?php

declare(strict_types=1);

namespace Cbox\Id\BillingBridge;

use Cbox\Id\BillingBridge\Listeners\ReportUsageToBilling;
use Cbox\Id\Kernel\Events\EventDelivered;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the usage bridge: when enabled, a delivered Cbox ID domain event is forwarded
 * to the billing usage buffer by {@see ReportUsageToBilling}. Compose this only in a
 * deployment that embeds the billing client (the SaaS); the self-hostable app never
 * depends on billing.
 */
class BillingBridgeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/laravel-id-billing-bridge.php', 'laravel-id-billing-bridge');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->publishes([
            __DIR__.'/../config/laravel-id-billing-bridge.php' => config_path('laravel-id-billing-bridge.php'),
        ], 'cbox-id-billing-bridge-config');

        if (config('laravel-id-billing-bridge.enabled', true) !== true) {
            return;
        }

        Event::listen(EventDelivered::class, function (EventDelivered $delivered): void {
            $this->app->make(ReportUsageToBilling::class)->handle($delivered);
        });
    }
}
