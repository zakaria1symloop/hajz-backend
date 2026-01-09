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
        Schema::table('withdrawal_requests', function (Blueprint $table) {
            // Drop foreign key first
            $table->dropForeign(['hotel_wallet_id']);

            // Make column nullable
            $table->unsignedBigInteger('hotel_wallet_id')->nullable()->change();

            // Re-add foreign key
            $table->foreign('hotel_wallet_id')->references('id')->on('hotel_wallets')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('withdrawal_requests', function (Blueprint $table) {
            $table->dropForeign(['hotel_wallet_id']);
            $table->unsignedBigInteger('hotel_wallet_id')->nullable(false)->change();
            $table->foreign('hotel_wallet_id')->references('id')->on('hotel_wallets')->onDelete('cascade');
        });
    }
};
