<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('name_ar', 180);
            $table->string('slug', 180)->unique();
            $table->text('short_description_ar');
            $table->text('description_ar');
            $table->text('details_ar')->nullable();
            $table->string('material_ar', 80)->nullable()->index();
            $table->string('room_ar', 80)->nullable()->index();
            $table->boolean('is_featured')->default(false)->index();
            $table->timestampTz('published_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
