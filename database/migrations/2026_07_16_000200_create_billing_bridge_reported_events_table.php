<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Dedup marker: the id of every domain event already reported to billing, so
        // at-least-once outbox redelivery bills each event exactly once.
        Schema::create('billing_bridge_reported_events', function (Blueprint $table): void {
            $table->string('event_id', 40)->primary();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_bridge_reported_events');
    }
};
