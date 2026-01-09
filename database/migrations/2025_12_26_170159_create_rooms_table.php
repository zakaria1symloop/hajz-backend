<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->enum('type', ['single', 'double', 'twin', 'triple', 'quad', 'suite', 'deluxe', 'presidential'])->default('double');
            $table->text('description')->nullable();
            $table->integer('capacity')->default(2);
            $table->string('bed_configuration', 100)->nullable();
            $table->decimal('price_per_night', 10, 2);
            $table->integer('size_sqm')->nullable();
            $table->json('amenities')->nullable();
            $table->boolean('is_active')->default(true);
            $table->enum('status', ['available', 'maintenance', 'out_of_service'])->default('available');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
