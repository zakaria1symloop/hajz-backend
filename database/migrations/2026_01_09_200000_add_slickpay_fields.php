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
        // Add slickpay_contact_uuid to users table
        Schema::table('users', function (Blueprint $table) {
            $table->string('slickpay_contact_uuid')->nullable()->after('email');
        });

        // Add slickpay fields to payments table
        Schema::table('payments', function (Blueprint $table) {
            $table->string('slickpay_invoice_id')->nullable()->after('checkout_id');
            $table->json('slickpay_response')->nullable()->after('chargily_response');
            $table->timestamp('paid_at')->nullable()->after('status');
            $table->text('description')->nullable()->after('payment_method');

            // Make reservation_id nullable for polymorphic payments
            $table->foreignId('reservation_id')->nullable()->change();

            // Add polymorphic columns for different booking types
            $table->string('payable_type')->nullable()->after('reservation_id');
            $table->unsignedBigInteger('payable_id')->nullable()->after('payable_type');

            // Add index for polymorphic relation
            $table->index(['payable_type', 'payable_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('slickpay_contact_uuid');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['payable_type', 'payable_id']);
            $table->dropColumn([
                'slickpay_invoice_id',
                'slickpay_response',
                'paid_at',
                'description',
                'payable_type',
                'payable_id',
            ]);
        });
    }
};
