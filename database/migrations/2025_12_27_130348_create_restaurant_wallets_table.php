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
        Schema::create('restaurant_wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->decimal('pending_balance', 15, 2)->default(0);
            $table->decimal('available_balance', 15, 2)->default(0);
            $table->decimal('total_earned', 15, 2)->default(0);
            $table->decimal('total_withdrawn', 15, 2)->default(0);
            $table->timestamps();

            $table->unique('restaurant_id');
        });

        // Add restaurant_wallet_id to wallet_transactions
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->foreignId('restaurant_wallet_id')->nullable()->after('hotel_wallet_id')->constrained('restaurant_wallets')->nullOnDelete();
            $table->foreignId('table_reservation_id')->nullable()->after('reservation_id')->constrained('table_reservations')->nullOnDelete();
        });

        // Add restaurant_wallet_id to withdrawal_requests
        Schema::table('withdrawal_requests', function (Blueprint $table) {
            $table->foreignId('restaurant_wallet_id')->nullable()->after('hotel_wallet_id')->constrained('restaurant_wallets')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('withdrawal_requests', function (Blueprint $table) {
            $table->dropForeign(['restaurant_wallet_id']);
            $table->dropColumn('restaurant_wallet_id');
        });

        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dropForeign(['restaurant_wallet_id']);
            $table->dropForeign(['table_reservation_id']);
            $table->dropColumn(['restaurant_wallet_id', 'table_reservation_id']);
        });

        Schema::dropIfExists('restaurant_wallets');
    }
};
