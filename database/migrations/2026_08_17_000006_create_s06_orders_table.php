<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->string('order_number',40)->unique();
            $table->uuid('idempotency_key')->unique();
            $table->char('currency',3);
            $table->string('customer_full_name',160);
            $table->string('customer_email',254);
            $table->string('customer_phone',32);
            $table->char('delivery_country_code',2);
            $table->string('delivery_region',120);
            $table->string('delivery_city',120);
            $table->string('delivery_district',160)->nullable();
            $table->string('delivery_address_line',255);
            $table->string('delivery_building_unit',120)->nullable();
            $table->string('delivery_postal_code',24)->nullable();
            $table->text('delivery_notes')->nullable();
            $table->string('shipping_method_code',80);
            $table->string('shipping_method_name',160);
            $table->decimal('shipping_amount',12,2);
            $table->string('tax_policy_code',100);
            $table->decimal('tax_amount',12,2);
            $table->decimal('subtotal',12,2);
            $table->decimal('total',12,2);
            $table->string('payment_method_code',80);
            $table->string('payment_state',40);
            $table->string('order_state',40);
            $table->string('reservation_state',40);
            $table->string('reservation_policy_code',80);
            $table->string('fulfillment_state',40);
            $table->string('consent_version',120);
            $table->timestampTz('consented_at');
            $table->timestampsTz();
        });
        Schema::create('order_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name',200);
            $table->string('variant_name',200);
            $table->string('variant_sku',80);
            $table->decimal('unit_price',12,2);
            $table->unsignedSmallInteger('quantity');
            $table->decimal('line_total',12,2);
            $table->char('currency',3);
            $table->timestampsTz();
        });
        Schema::create('order_line_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_line_id')->constrained()->cascadeOnDelete();
            $table->string('attribute_code',80);
            $table->string('attribute_name',160);
            $table->string('option_value',160);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('order_line_options');
        Schema::dropIfExists('order_lines');
        Schema::dropIfExists('orders');
    }
};
