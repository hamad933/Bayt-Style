<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 160);
            $table->string('email', 254)->unique();
            $table->string('password');
            $table->string('role', 40)->index();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('inventory_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('variant_id')->constrained()->restrictOnDelete();
            $table->string('movement_type', 60)->index();
            $table->integer('quantity_delta');
            $table->unsignedInteger('quantity_before');
            $table->unsignedInteger('quantity_after');
            $table->text('reason');
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_identifier', 254);
            $table->uuid('correlation_id')->index();
            $table->timestampTz('occurred_at')->index();
        });

        Schema::create('admin_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_identifier', 254);
            $table->string('action', 100)->index();
            $table->string('entity_type', 60);
            $table->unsignedBigInteger('entity_id');
            $table->json('before_values')->nullable();
            $table->json('after_values')->nullable();
            $table->text('reason');
            $table->uuid('correlation_id')->index();
            $table->timestampTz('created_at')->index();
            $table->index(['entity_type', 'entity_id']);
        });

        Schema::table('order_lines', function (Blueprint $table): void {
            $table->dropForeign(['variant_id']);
            $table->foreign('variant_id')->references('id')->on('variants')->restrictOnDelete();
        });

        $now = now();
        foreach (DB::table('variants')->select(['id', 'inventory_quantity'])->orderBy('id')->get() as $variant) {
            DB::table('inventory_movements')->insert([
                'variant_id' => $variant->id,
                'movement_type' => 'opening_balance',
                'quantity_delta' => (int) $variant->inventory_quantity,
                'quantity_before' => 0,
                'quantity_after' => (int) $variant->inventory_quantity,
                'reason' => 'S09 baseline adoption of the existing inventory projection.',
                'actor_user_id' => null,
                'actor_identifier' => 'system:s09-baseline',
                'correlation_id' => (string) Str::uuid(),
                'occurred_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('order_lines', function (Blueprint $table): void {
            $table->dropForeign(['variant_id']);
            $table->foreign('variant_id')->references('id')->on('variants')->nullOnDelete();
        });

        Schema::dropIfExists('admin_audit_logs');
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('users');
    }
};
