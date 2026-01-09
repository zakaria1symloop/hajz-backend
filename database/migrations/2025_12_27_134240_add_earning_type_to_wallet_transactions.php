<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Modify enum to add 'earning' type
        DB::statement("ALTER TABLE wallet_transactions MODIFY COLUMN type ENUM('booking_credit', 'commission_deduction', 'balance_release', 'withdrawal', 'refund', 'earning')");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE wallet_transactions MODIFY COLUMN type ENUM('booking_credit', 'commission_deduction', 'balance_release', 'withdrawal', 'refund')");
    }
};
