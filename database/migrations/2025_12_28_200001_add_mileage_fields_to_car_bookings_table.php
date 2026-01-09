<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('car_bookings', function (Blueprint $table) {
            $table->integer('pickup_mileage')->nullable()->after('return_location');
            $table->integer('return_mileage')->nullable()->after('pickup_mileage');
            $table->integer('km_driven')->nullable()->after('return_mileage');
            $table->integer('extra_km')->nullable()->after('km_driven');
            $table->decimal('extra_km_charge', 10, 2)->default(0)->after('extra_km');
            $table->text('pickup_notes')->nullable()->after('notes');
            $table->text('return_notes')->nullable()->after('pickup_notes');
            $table->string('extra_charges_reason')->nullable()->after('extra_charges_description');
        });
    }

    public function down(): void
    {
        Schema::table('car_bookings', function (Blueprint $table) {
            $table->dropColumn([
                'pickup_mileage',
                'return_mileage',
                'km_driven',
                'extra_km',
                'extra_km_charge',
                'pickup_notes',
                'return_notes',
                'extra_charges_reason',
            ]);
        });
    }
};
