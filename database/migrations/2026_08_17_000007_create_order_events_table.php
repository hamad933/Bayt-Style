<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::create('order_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->string('event_type',80);
            $table->string('actor_type',80);
            $table->string('entity_type',80);
            $table->string('order_reference',40);
            $table->string('resulting_order_state',40);
            $table->string('resulting_payment_state',40);
            $table->string('resulting_reservation_state',40);
            $table->string('resulting_fulfillment_state',40);
            $table->string('reason_code',100);
            $table->uuid('correlation_id');
            $table->timestampTz('occurred_at');
            $table->unique(['order_id','event_type','correlation_id'], 'order_events_initial_context_unique');
            $table->index('correlation_id');
        });
    }
    public function down(): void { Schema::dropIfExists('order_events'); }
};
