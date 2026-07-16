<?php

declare(strict_types=1);

namespace Cbox\Id\BillingBridge\Listeners;

use Cbox\Billing\Client\Contracts\UsageBuffer;
use Cbox\Id\Kernel\Events\EventDelivered;
use Cbox\Id\Kernel\Usage\EventMetricMap;
use Illuminate\Support\Facades\DB;

/**
 * Forwards a metered Cbox ID domain event to the billing usage buffer. It uses the
 * framework's shared {@see EventMetricMap} so a billing meter and Cbox ID's local
 * analytics counter of the same event agree on the metric (the `auth.*` vocabulary),
 * then records one unit against the event's organization.
 *
 * Two guards:
 *  - Only org-scoped events are billed — billing meters are per-org, so a
 *    system-scoped (org-less) event is skipped.
 *  - Exactly-once: outbox delivery is at-least-once and {@see UsageBuffer::record()}
 *    appends (not idempotent), so a redelivery would over-bill. An `insertOrIgnore`
 *    into `billing_bridge_reported_events` keyed on the event id lets only the first
 *    delivery through.
 */
final class ReportUsageToBilling
{
    public function __construct(private readonly UsageBuffer $usage) {}

    public function handle(EventDelivered $delivered): void
    {
        $metric = EventMetricMap::for($delivered->event->type);

        if ($metric === null) {
            return;
        }

        $organizationId = $delivered->event->organization_id;

        if (! is_string($organizationId) || $organizationId === '') {
            return;
        }

        $fresh = DB::table('billing_bridge_reported_events')->insertOrIgnore([
            'event_id' => $delivered->event->id,
            'created_at' => now(),
        ]);

        if ($fresh === 0) {
            return;
        }

        $this->usage->record($organizationId, $metric->value, 1);
    }
}
