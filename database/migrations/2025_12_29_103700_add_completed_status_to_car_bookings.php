<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add 'completed' to the status ENUM
        DB::statement("ALTER TABLE car_bookings MODIFY COLUMN status ENUM('pending', 'confirmed', 'picked_up', 'returned', 'completed', 'cancelled', 'no_show') DEFAULT 'pending'");
    }

    public function down(): void
    {
        // Remove 'completed' from the status ENUM
        DB::statement("ALTER TABLE car_bookings MODIFY COLUMN status ENUM('pending', 'confirmed', 'picked_up', 'returned', 'cancelled', 'no_show') DEFAULT 'pending'");
    }
};
