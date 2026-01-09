<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('table_reservations')) {
            return;
        }
        Schema::create('table_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('restaurant_table_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('guest_name');
            $table->string('guest_email');
            $table->string('guest_phone');
            $table->date('reservation_date');
            $table->time('reservation_time');
            $table->integer('guests_count');
            $table->integer('duration_minutes')->default(120); // Default 2 hours
            $table->text('special_requests')->nullable();
            $table->decimal('plats_total', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->enum('status', ['pending', 'confirmed', 'cancelled', 'completed', 'no_show'])->default('pending');
            $table->enum('payment_status', ['pending', 'paid', 'refunded'])->default('pending');
            $table->string('payment_id')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['restaurant_id', 'reservation_date'], 'tr_restaurant_date_idx');
            $table->index(['restaurant_table_id', 'reservation_date', 'reservation_time'], 'tr_table_date_time_idx');
        });

        // Pivot table for reservation plats
        if (Schema::hasTable('reservation_plats')) {
            return;
        }
        Schema::create('reservation_plats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('table_reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plat_id')->constrained()->cascadeOnDelete();
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total_price', 10, 2);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_plats');
        Schema::dropIfExists('table_reservations');
    }
};
