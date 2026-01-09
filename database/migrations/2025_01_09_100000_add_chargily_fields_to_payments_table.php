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
        Schema::table('payments', function (Blueprint $table) {
            if (!Schema::hasColumn('payments', 'chargily_checkout_id')) {
                $table->string('chargily_checkout_id')->nullable();
            }
            if (!Schema::hasColumn('payments', 'chargily_customer_id')) {
                $table->string('chargily_customer_id')->nullable();
            }
            if (!Schema::hasColumn('payments', 'chargily_response')) {
                $table->json('chargily_response')->nullable();
            }
        });

        // Also add chargily_customer_id to users table
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'chargily_customer_id')) {
                $table->string('chargily_customer_id')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $columns = ['chargily_checkout_id', 'chargily_customer_id', 'chargily_response'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('payments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'chargily_customer_id')) {
                $table->dropColumn('chargily_customer_id');
            }
        });
    }
};
