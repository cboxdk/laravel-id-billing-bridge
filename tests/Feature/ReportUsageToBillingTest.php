<?php

declare(strict_types=1);

use Cbox\Billing\Client\Buffers\ArrayUsageBuffer;
use Cbox\Billing\Client\Contracts\UsageBuffer;
use Cbox\Id\BillingBridge\Listeners\ReportUsageToBilling;
use Cbox\Id\Kernel\Events\EventDelivered;
use Cbox\Id\Kernel\Events\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->buffer = new ArrayUsageBuffer;
    $this->app->instance(UsageBuffer::class, $this->buffer);
});

function deliver(string $id, string $type, ?string $org): EventDelivered
{
    return new EventDelivered(new Event(['id' => $id, 'type' => $type, 'organization_id' => $org]));
}

it('forwards a mapped org-scoped event to billing under the shared metric', function (): void {
    app(ReportUsageToBilling::class)->handle(deliver('evt_1', 'user.login', 'org_a'));

    expect($this->buffer->cumulative('org_a', 'auth.login'))->toBe(1);
});

it('bills a delivered event exactly once, even on redelivery', function (): void {
    $listener = app(ReportUsageToBilling::class);
    $listener->handle(deliver('evt_1', 'user.login', 'org_a'));
    $listener->handle(deliver('evt_1', 'user.login', 'org_a')); // at-least-once redelivery

    expect($this->buffer->cumulative('org_a', 'auth.login'))->toBe(1);
});

it('ignores an unmapped event type', function (): void {
    app(ReportUsageToBilling::class)->handle(deliver('evt_2', 'something.unmapped', 'org_a'));

    expect($this->buffer->snapshot('org_a'))->toBe([]);
});

it('skips a system (org-less) event — billing meters are per-org', function (): void {
    app(ReportUsageToBilling::class)->handle(deliver('evt_3', 'user.created', null));

    expect($this->buffer->snapshot())->toBe([]);
});

it('maps distinct events to their billing meters', function (): void {
    $listener = app(ReportUsageToBilling::class);
    $listener->handle(deliver('e1', 'user.login', 'org_a'));
    $listener->handle(deliver('e2', 'organization.member_added', 'org_a'));

    expect($this->buffer->cumulative('org_a', 'auth.login'))->toBe(1)
        ->and($this->buffer->cumulative('org_a', 'auth.member_added'))->toBe(1);
});

it('bills the seat and directory dimensions (member_removed, directory provisioning)', function (): void {
    $listener = app(ReportUsageToBilling::class);
    $listener->handle(deliver('e10', 'organization.member_added', 'org_a'));
    $listener->handle(deliver('e11', 'organization.member_removed', 'org_a'));
    $listener->handle(deliver('e12', 'directory.user.provisioned', 'org_a'));

    expect($this->buffer->cumulative('org_a', 'auth.member_added'))->toBe(1)
        ->and($this->buffer->cumulative('org_a', 'auth.member_removed'))->toBe(1)
        ->and($this->buffer->cumulative('org_a', 'auth.directory_user'))->toBe(1);
});
