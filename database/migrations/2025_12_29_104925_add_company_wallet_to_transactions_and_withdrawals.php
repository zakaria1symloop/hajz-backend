<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add company_wallet_id and car_booking_id to wallet_transactions
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->foreignId('company_wallet_id')->nullable()->after('restaurant_wallet_id')->constrained()->nullOnDelete();
            $table->foreignId('car_booking_id')->nullable()->after('table_reservation_id')->constrained()->nullOnDelete();
        });

        // Add company_wallet_id to withdrawal_requests
        Schema::table('withdrawal_requests', function (Blueprint $table) {
            $table->foreignId('company_wallet_id')->nullable()->after('restaurant_wallet_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dropForeign(['company_wallet_id']);
            $table->dropForeign(['car_booking_id']);
            $table->dropColumn(['company_wallet_id', 'car_booking_id']);
        });

        Schema::table('withdrawal_requests', function (Blueprint $table) {
            $table->dropForeign(['company_wallet_id']);
            $table->dropColumn('company_wallet_id');
        });
    }
};
