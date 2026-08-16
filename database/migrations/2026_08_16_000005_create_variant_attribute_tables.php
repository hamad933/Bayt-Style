<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('variant_attributes', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 80)->unique();
            $table->string('name_ar', 120);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('variant_attribute_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('variant_attribute_id')->constrained()->cascadeOnDelete();
            $table->string('code', 80);
            $table->string('value_ar', 160);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['variant_attribute_id', 'code']);
            $table->unique(['variant_attribute_id', 'value_ar']);
        });

        Schema::create('variant_attribute_option_variant', function (Blueprint $table): void {
            $table->foreignId('variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variant_attribute_option_id')->constrained()->cascadeOnDelete();
            $table->primary(['variant_id', 'variant_attribute_option_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('variant_attribute_option_variant');
        Schema::dropIfExists('variant_attribute_options');
        Schema::dropIfExists('variant_attributes');
    }
};
