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
        Schema::table('reservations', function (Blueprint $table) {
            $table->foreignId('room_id')->nullable()->after('reservable_id')->constrained()->onDelete('set null');
            $table->timestamp('confirmed_at')->nullable()->after('status');
            $table->timestamp('completed_at')->nullable()->after('confirmed_at');
            $table->timestamp('cancelled_at')->nullable()->after('completed_at');
            $table->text('cancellation_reason')->nullable()->after('cancelled_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropForeign(['room_id']);
            $table->dropColumn([
                'room_id',
                'confirmed_at',
                'completed_at',
                'cancelled_at',
                'cancellation_reason',
            ]);
        });
    }
};
