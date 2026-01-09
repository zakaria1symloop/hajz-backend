<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('table_reservations', function (Blueprint $table) {
            $table->decimal('table_price_per_hour', 10, 2)->default(0)->after('duration_minutes');
            $table->decimal('table_total', 10, 2)->default(0)->after('table_price_per_hour');
        });
    }

    public function down(): void
    {
        Schema::table('table_reservations', function (Blueprint $table) {
            $table->dropColumn(['table_price_per_hour', 'table_total']);
        });
    }
};
