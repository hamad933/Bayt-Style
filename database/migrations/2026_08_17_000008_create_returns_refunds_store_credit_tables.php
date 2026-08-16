<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('return_eligibilities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_line_id')->unique()->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('eligible_quantity');
            $table->string('state', 32);
            $table->string('source_type', 80);
            $table->string('source_reference', 160);
            $table->uuid('correlation_id');
            $table->timestampTz('recorded_at');
            $table->timestampsTz();
            $table->index(['order_id', 'state']);
        });

        Schema::create('return_cases', function (Blueprint $table): void {
            $table->id();
            $table->string('return_number', 48)->unique();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_line_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('requested_quantity');
            $table->string('reason_code', 64);
            $table->string('return_state', 40);
            $table->string('authority_type', 48);
            $table->char('authority_fingerprint', 64);
            $table->uuid('correlation_id');
            $table->timestampTz('requested_at');
            $table->timestampTz('authorized_at')->nullable();
            $table->timestampTz('received_at')->nullable();
            $table->timestampTz('inspected_at')->nullable();
            $table->timestampTz('disposition_decided_at')->nullable();
            $table->timestampTz('closed_at')->nullable();
            $table->timestampsTz();
            $table->index(['order_id', 'return_state']);
            $table->index(['order_line_id', 'return_state']);
        });

        Schema::create('return_case_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('return_case_id')->constrained()->restrictOnDelete();
            $table->string('event_type', 64);
            $table->string('from_state', 40)->nullable();
            $table->string('to_state', 40);
            $table->string('actor_type', 48);
            $table->uuid('correlation_id');
            $table->timestampTz('occurred_at');
            $table->index(['return_case_id', 'occurred_at']);
        });

        Schema::create('return_inspections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('return_case_id')->unique()->constrained()->restrictOnDelete();
            $table->string('inspection_outcome', 64);
            $table->string('actor_type', 48);
            $table->uuid('correlation_id');
            $table->timestampTz('inspected_at');
        });

        Schema::create('inventory_dispositions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('return_case_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('return_inspection_id')->unique()->constrained('return_inspections')->restrictOnDelete();
            $table->string('disposition', 48);
            $table->string('actor_type', 48);
            $table->uuid('correlation_id');
            $table->timestampTz('decided_at');
        });

        Schema::create('refund_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('return_case_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('refund_reference', 80);
            $table->string('refund_state', 32);
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3);
            $table->string('actor_type', 48);
            $table->uuid('correlation_id');
            $table->timestampTz('occurred_at');
            $table->index(['order_id', 'refund_reference', 'occurred_at']);
        });

        Schema::create('store_credit_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('return_case_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('entry_type', 32);
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3);
            $table->string('source_type', 80);
            $table->string('source_reference', 160);
            $table->foreignId('reversal_of_entry_id')->nullable()->unique()->constrained('store_credit_entries')->restrictOnDelete();
            $table->uuid('correlation_id');
            $table->timestampTz('occurred_at');
            $table->index(['order_id', 'currency', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_credit_entries');
        Schema::dropIfExists('refund_records');
        Schema::dropIfExists('inventory_dispositions');
        Schema::dropIfExists('return_inspections');
        Schema::dropIfExists('return_case_events');
        Schema::dropIfExists('return_cases');
        Schema::dropIfExists('return_eligibilities');
    }
};
