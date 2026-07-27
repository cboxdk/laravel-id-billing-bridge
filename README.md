# Cbox ID → Billing bridge

**`cboxdk/laravel-id-billing-bridge`** — forwards [Cbox ID](https://github.com/cboxdk/laravel-id)
domain events to the [Cbox Billing client](https://github.com/cboxdk/laravel-billing-client)
so identity usage is metered to billing, off the transactional outbox, **exactly-once**.

It exists so the two stay cleanly separated: **Cbox ID meters locally for analytics**
(works with no billing at all), **a billing service meters and gates where you bill**,
and this small package is the only thing that depends on *both*.

**Optional by design.** Install it only where you bill someone; Cbox ID itself never
depends on the billing client, and is free to self-host with no limits.

## How it works

- Listens for `EventDelivered` on Cbox ID's transactional outbox.
- Maps the event to a metric via Cbox ID's shared `EventMetricMap` — the **same**
  `auth.*` vocabulary Cbox ID's own analytics counters and the billing meters use, so
  a billing meter and a local counter of the same event agree.
- Records one unit against the event's organization with the billing client's
  `UsageBuffer::record($org, $metric, 1)` (the client buffers and flushes to the
  remote billing service).

Two guarantees:

- **Per-org only** — billing meters are per-organization, so a system-scoped
  (org-less) event is skipped.
- **Exactly-once** — outbox delivery is at-least-once and `UsageBuffer::record()`
  appends, so a redelivery would over-bill. A dedup marker
  (`billing_bridge_reported_events`, keyed on the event id) lets only the first
  delivery through.

## Install

```bash
composer require cboxdk/laravel-id-billing-bridge
php artisan migrate
```

The service provider auto-registers. Metering starts immediately; disable without
removing the package:

```dotenv
CBOX_ID_BILLING_BRIDGE_ENABLED=false
```

## Scope

This bridge only **reports usage**. Hot-path **quota gating** stays where it belongs —
inline at the action site via `BillingClient::reserve()` — not on the event bus.

## License

**MIT.** See [LICENSE](LICENSE).
