<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('car_bookings')) {
            return;
        }
        Schema::create('car_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_id')->constrained()->cascadeOnDelete();
            $table->foreignId('car_rental_company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('customer_phone');
            $table->string('customer_id_number')->nullable(); // National ID or Passport
            $table->string('driver_license_number')->nullable();
            $table->date('pickup_date');
            $table->time('pickup_time');
            $table->date('return_date');
            $table->time('return_time');
            $table->string('pickup_location')->nullable();
            $table->string('return_location')->nullable();
            $table->integer('rental_days');
            $table->decimal('price_per_day', 10, 2);
            $table->decimal('subtotal', 10, 2);
            $table->decimal('deposit_amount', 10, 2)->default(0);
            $table->decimal('extra_charges', 10, 2)->default(0);
            $table->text('extra_charges_description')->nullable();
            $table->decimal('total_amount', 10, 2);
            $table->enum('status', ['pending', 'confirmed', 'picked_up', 'returned', 'cancelled', 'no_show'])->default('pending');
            $table->enum('payment_status', ['pending', 'deposit_paid', 'fully_paid', 'refunded'])->default('pending');
            $table->string('payment_id')->nullable();
            $table->text('notes')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['car_id', 'pickup_date', 'return_date']);
            $table->index(['car_rental_company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('car_bookings');
    }
};
