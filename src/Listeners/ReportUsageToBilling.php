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
class ReportUsageToBilling
{
    public function __construct(private readonly UsageBuffer $usage) {}

    public function handle(EventDelivered $delivered): void
    {
        $event = $delivered->event;

        $organizationId = $event->organization_id;

        if (! is_string($organizationId) || $organizationId === '') {
            return;
        }

        // A `usage.reconciled` correction carries its own metric + unit count (the
        // reconciler's drift), so billing's own store converges on ground truth too.
        // A normal event maps to a metric via the shared vocabulary and counts as one.
        if ($event->type === 'usage.reconciled') {
            $metric = is_string($event->payload['metric'] ?? null) ? $event->payload['metric'] : null;
            $units = is_int($event->payload['units'] ?? null) ? $event->payload['units'] : 0;

            if ($metric === null || $units <= 0) {
                return;
            }
        } else {
            $mapped = EventMetricMap::for($event->type);

            if ($mapped === null) {
                return;
            }

            $metric = $mapped->value;
            $units = 1;
        }

        $fresh = DB::table('billing_bridge_reported_events')->insertOrIgnore([
            'event_id' => $event->id,
            'created_at' => now(),
        ]);

        if ($fresh === 0) {
            return;
        }

        $this->usage->record($organizationId, $metric, $units);
    }
}
