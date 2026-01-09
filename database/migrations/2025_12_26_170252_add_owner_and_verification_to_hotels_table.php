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
        Schema::table('hotels', function (Blueprint $table) {
            $table->foreignId('hotel_owner_id')->nullable()->after('id')->constrained('hotel_owners')->onDelete('cascade');
            $table->string('wilaya', 100)->nullable()->after('city');
            $table->tinyInteger('star_rating')->unsigned()->default(0)->after('rating');
            $table->string('phone', 20)->nullable()->after('star_rating');
            $table->string('email')->nullable()->after('phone');
            $table->string('website')->nullable()->after('email');
            $table->time('check_in_time')->default('14:00')->after('website');
            $table->time('check_out_time')->default('12:00')->after('check_in_time');
            $table->enum('verification_status', ['pending', 'verified', 'rejected'])->default('pending')->after('is_active');
            $table->text('rejection_reason')->nullable()->after('verification_status');
            $table->timestamp('verified_at')->nullable()->after('rejection_reason');
            $table->decimal('latitude', 10, 8)->nullable()->after('verified_at');
            $table->decimal('longitude', 11, 8)->nullable()->after('latitude');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->dropForeign(['hotel_owner_id']);
            $table->dropColumn([
                'hotel_owner_id',
                'wilaya',
                'star_rating',
                'phone',
                'email',
                'website',
                'check_in_time',
                'check_out_time',
                'verification_status',
                'rejection_reason',
                'verified_at',
                'latitude',
                'longitude',
            ]);
        });
    }
};
