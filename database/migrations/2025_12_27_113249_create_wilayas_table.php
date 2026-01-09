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
        Schema::create('wilayas', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_ar')->nullable(); // Arabic name
            $table->string('code', 10)->unique(); // Wilaya code (01-58)
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // Add wilaya_id to hotels table
        Schema::table('hotels', function (Blueprint $table) {
            $table->foreignId('wilaya_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        // Add wilaya_id to restaurants table if exists
        if (Schema::hasTable('restaurants')) {
            Schema::table('restaurants', function (Blueprint $table) {
                $table->foreignId('wilaya_id')->nullable()->after('id')->constrained()->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->dropForeign(['wilaya_id']);
            $table->dropColumn('wilaya_id');
        });

        if (Schema::hasTable('restaurants')) {
            Schema::table('restaurants', function (Blueprint $table) {
                $table->dropForeign(['wilaya_id']);
                $table->dropColumn('wilaya_id');
            });
        }

        Schema::dropIfExists('wilayas');
    }
};
