<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cars')) {
            return;
        }
        Schema::create('cars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_rental_company_id')->constrained()->cascadeOnDelete();
            $table->string('brand'); // Toyota, BMW, etc.
            $table->string('model'); // Corolla, X5, etc.
            $table->integer('year');
            $table->string('color')->nullable();
            $table->string('license_plate')->nullable();
            $table->enum('type', ['economy', 'compact', 'midsize', 'fullsize', 'suv', 'luxury', 'sports', 'van', 'truck'])->default('midsize');
            $table->enum('transmission', ['automatic', 'manual'])->default('automatic');
            $table->enum('fuel_type', ['petrol', 'diesel', 'electric', 'hybrid'])->default('petrol');
            $table->integer('seats')->default(5);
            $table->integer('doors')->default(4);
            $table->boolean('has_ac')->default(true);
            $table->boolean('has_gps')->default(false);
            $table->boolean('has_bluetooth')->default(true);
            $table->boolean('has_usb')->default(true);
            $table->boolean('has_child_seat')->default(false);
            $table->decimal('price_per_day', 10, 2);
            $table->decimal('price_per_hour', 10, 2)->nullable();
            $table->integer('min_rental_days')->default(1);
            $table->integer('max_rental_days')->nullable();
            $table->decimal('deposit_amount', 10, 2)->nullable();
            $table->integer('mileage_limit')->nullable(); // km per day
            $table->decimal('extra_km_price', 10, 2)->nullable();
            $table->text('description')->nullable();
            $table->json('features')->nullable();
            $table->boolean('is_available')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cars');
    }
};
