<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('variants', function (Blueprint $table): void {
            $table->jsonb('options')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('variants', function (Blueprint $table): void {
            $table->dropColumn('options');
        });
    }
};
