<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Modify type enum to include more car types
        DB::statement("ALTER TABLE cars MODIFY COLUMN type ENUM('economy', 'compact', 'midsize', 'fullsize', 'suv', 'luxury', 'sports', 'van', 'truck', 'sedan', 'hatchback', 'coupe', 'convertible', 'wagon') DEFAULT 'sedan'");

        // Modify fuel_type enum to include gasoline
        DB::statement("ALTER TABLE cars MODIFY COLUMN fuel_type ENUM('petrol', 'diesel', 'electric', 'hybrid', 'gasoline') DEFAULT 'gasoline'");
    }

    public function down(): void
    {
        // Revert to original enums
        DB::statement("ALTER TABLE cars MODIFY COLUMN type ENUM('economy', 'compact', 'midsize', 'fullsize', 'suv', 'luxury', 'sports', 'van', 'truck') DEFAULT 'midsize'");
        DB::statement("ALTER TABLE cars MODIFY COLUMN fuel_type ENUM('petrol', 'diesel', 'electric', 'hybrid') DEFAULT 'petrol'");
    }
};
