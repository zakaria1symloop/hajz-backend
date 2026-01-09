<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->decimal('price_per_night', 10, 2)->nullable()->default(null)->change();
            $table->integer('rooms_available')->nullable()->default(0)->change();
            $table->decimal('rating', 3, 2)->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->decimal('price_per_night', 10, 2)->nullable(false)->change();
            $table->integer('rooms_available')->nullable(false)->change();
            $table->decimal('rating', 3, 2)->nullable(false)->change();
        });
    }
};
