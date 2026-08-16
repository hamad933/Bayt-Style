<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('variants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('sku', 80)->unique();
            $table->string('name_ar', 160);
            $table->decimal('price', 10, 2);
            $table->char('currency', 3)->default('SAR');
            $table->unsignedInteger('inventory_quantity')->default(0);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        DB::statement('CREATE UNIQUE INDEX variants_one_default_per_product ON variants (product_id) WHERE is_default = true');
    }

    public function down(): void
    {
        Schema::dropIfExists('variants');
    }
};
