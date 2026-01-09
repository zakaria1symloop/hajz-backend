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
        Schema::table('room_bookings', function (Blueprint $table) {
            // Drop the foreign key constraint first
            $table->dropForeign(['reservation_id']);

            // Make reservation_id nullable
            $table->foreignId('reservation_id')->nullable()->change();

            // Re-add the foreign key with nullOnDelete
            $table->foreign('reservation_id')
                ->references('id')
                ->on('reservations')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('room_bookings', function (Blueprint $table) {
            $table->dropForeign(['reservation_id']);
            $table->foreignId('reservation_id')->nullable(false)->change();
            $table->foreign('reservation_id')
                ->references('id')
                ->on('reservations')
                ->cascadeOnDelete();
        });
    }
};
