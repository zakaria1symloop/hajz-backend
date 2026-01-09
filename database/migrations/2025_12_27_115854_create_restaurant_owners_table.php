<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('restaurant_owners')) {
            Schema::create('restaurant_owners', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->string('phone')->nullable();
                $table->string('password');
                $table->boolean('is_active')->default(true);
                $table->rememberToken();
                $table->timestamps();
            });
        }

        // Update restaurants table to add owner_id
        Schema::table('restaurants', function (Blueprint $table) {
            if (!Schema::hasColumn('restaurants', 'restaurant_owner_id')) {
                $table->foreignId('restaurant_owner_id')->nullable()->after('id')->constrained()->nullOnDelete();
            }
            if (!Schema::hasColumn('restaurants', 'phone')) {
                $table->string('phone')->nullable()->after('description');
            }
            if (!Schema::hasColumn('restaurants', 'email')) {
                $table->string('email')->nullable()->after('phone');
            }
            if (!Schema::hasColumn('restaurants', 'opening_time')) {
                $table->time('opening_time')->nullable();
            }
            if (!Schema::hasColumn('restaurants', 'closing_time')) {
                $table->time('closing_time')->nullable();
            }
            if (!Schema::hasColumn('restaurants', 'total_tables')) {
                $table->integer('total_tables')->default(0);
            }
            if (!Schema::hasColumn('restaurants', 'verification_status')) {
                $table->enum('verification_status', ['pending', 'verified', 'rejected', 'suspended'])->default('pending');
            }
            if (!Schema::hasColumn('restaurants', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable();
            }
            if (!Schema::hasColumn('restaurants', 'verified_at')) {
                $table->timestamp('verified_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropForeign(['restaurant_owner_id']);
            $table->dropForeign(['wilaya_id']);
            $table->dropColumn(['restaurant_owner_id', 'wilaya_id', 'phone', 'email', 'opening_time', 'closing_time', 'total_tables', 'verification_status', 'rejection_reason', 'verified_at']);
        });

        Schema::dropIfExists('restaurant_owners');
    }
};
