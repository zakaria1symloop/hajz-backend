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
        Schema::table('restaurants', function (Blueprint $table) {
            // Add restaurant_owner_id if not exists
            if (!Schema::hasColumn('restaurants', 'restaurant_owner_id')) {
                $table->foreignId('restaurant_owner_id')->nullable()->after('id')->constrained()->nullOnDelete();
            }

            // Add name_ar if not exists
            if (!Schema::hasColumn('restaurants', 'name_ar')) {
                $table->string('name_ar')->nullable()->after('name');
            }

            // Add wilaya_id if not exists
            if (!Schema::hasColumn('restaurants', 'wilaya_id')) {
                $table->foreignId('wilaya_id')->nullable()->after('city')->constrained()->nullOnDelete();
            }

            // Add phone if not exists
            if (!Schema::hasColumn('restaurants', 'phone')) {
                $table->string('phone')->nullable()->after('price_range');
            }

            // Add email if not exists
            if (!Schema::hasColumn('restaurants', 'email')) {
                $table->string('email')->nullable()->after('phone');
            }

            // Add total_tables if not exists
            if (!Schema::hasColumn('restaurants', 'total_tables')) {
                $table->integer('total_tables')->default(0)->after('closing_time');
            }

            // Add latitude if not exists
            if (!Schema::hasColumn('restaurants', 'latitude')) {
                $table->decimal('latitude', 10, 8)->nullable()->after('total_tables');
            }

            // Add longitude if not exists
            if (!Schema::hasColumn('restaurants', 'longitude')) {
                $table->decimal('longitude', 11, 8)->nullable()->after('latitude');
            }

            // Add verification_status if not exists
            if (!Schema::hasColumn('restaurants', 'verification_status')) {
                $table->enum('verification_status', ['pending', 'verified', 'rejected'])->default('pending')->after('is_active');
            }

            // Make description nullable if it's not
            // We'll handle this separately
        });

        // Make some columns nullable that weren't before
        Schema::table('restaurants', function (Blueprint $table) {
            $table->text('description')->nullable()->change();
            $table->string('cuisine_type')->nullable()->change();
            $table->decimal('price_range', 10, 2)->nullable()->change();
            $table->time('opening_time')->nullable()->change();
            $table->time('closing_time')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $columns = [
                'restaurant_owner_id',
                'name_ar',
                'wilaya_id',
                'phone',
                'email',
                'total_tables',
                'latitude',
                'longitude',
                'verification_status',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('restaurants', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
