<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_rental_company_id')->constrained()->cascadeOnDelete();
            $table->decimal('pending_balance', 12, 2)->default(0);
            $table->decimal('available_balance', 12, 2)->default(0);
            $table->decimal('total_earned', 12, 2)->default(0);
            $table->decimal('total_withdrawn', 12, 2)->default(0);
            $table->timestamps();

            $table->unique('car_rental_company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_wallets');
    }
};
