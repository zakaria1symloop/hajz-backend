<?php

namespace Database\Seeders;

use App\Models\Restaurant;
use Illuminate\Database\Seeder;

class RestaurantSeeder extends Seeder
{
    public function run(): void
    {
        $restaurants = [
            [
                'name' => 'La Baie d\'Alger',
                'description' => 'Fine dining restaurant offering stunning sea views and exquisite Mediterranean cuisine.',
                'address' => 'Port d\'Alger',
                'city' => 'Algiers',
                'cuisine_type' => 'Mediterranean',
                'price_range' => 5000,
                'rating' => 5,
                'image' => 'https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?w=800',
                'opening_time' => '12:00',
                'closing_time' => '23:00',
                'capacity' => 80,
                'is_active' => true,
            ],
            [
                'name' => 'El Djazair',
                'description' => 'Traditional Algerian restaurant serving authentic local dishes in a beautiful setting.',
                'address' => 'Rue Didouche Mourad',
                'city' => 'Algiers',
                'cuisine_type' => 'Algerian',
                'price_range' => 3000,
                'rating' => 4,
                'image' => 'https://images.unsplash.com/photo-1555396273-367ea4eb4db5?w=800',
                'opening_time' => '11:00',
                'closing_time' => '22:00',
                'capacity' => 60,
                'is_active' => true,
            ],
            [
                'name' => 'Sushi Master Oran',
                'description' => 'Premium Japanese restaurant with fresh sushi and authentic Asian flavors.',
                'address' => 'Boulevard de la Soummam',
                'city' => 'Oran',
                'cuisine_type' => 'Japanese',
                'price_range' => 4500,
                'rating' => 4,
                'image' => 'https://images.unsplash.com/photo-1579027989536-b7b1f875659b?w=800',
                'opening_time' => '12:00',
                'closing_time' => '22:30',
                'capacity' => 45,
                'is_active' => true,
            ],
            [
                'name' => 'Le Cirta',
                'description' => 'Rooftop restaurant in Constantine with panoramic views and fusion cuisine.',
                'address' => 'Centre Ville',
                'city' => 'Constantine',
                'cuisine_type' => 'Fusion',
                'price_range' => 3500,
                'rating' => 4,
                'image' => 'https://images.unsplash.com/photo-1466978913421-dad2ebd01d17?w=800',
                'opening_time' => '18:00',
                'closing_time' => '00:00',
                'capacity' => 50,
                'is_active' => true,
            ],
            [
                'name' => 'Pizzeria Napoli',
                'description' => 'Authentic Italian pizzeria with wood-fired oven and imported ingredients.',
                'address' => 'Rue Larbi Ben M\'hidi',
                'city' => 'Algiers',
                'cuisine_type' => 'Italian',
                'price_range' => 2000,
                'rating' => 4,
                'image' => 'https://images.unsplash.com/photo-1565299624946-b28f40a0ae38?w=800',
                'opening_time' => '11:30',
                'closing_time' => '23:00',
                'capacity' => 70,
                'is_active' => true,
            ],
            [
                'name' => 'Le Sahara',
                'description' => 'Traditional desert cuisine with unique flavors and authentic atmosphere.',
                'address' => 'Place du Marche',
                'city' => 'Ghardaia',
                'cuisine_type' => 'Traditional',
                'price_range' => 2500,
                'rating' => 5,
                'image' => 'https://images.unsplash.com/photo-1552566626-52f8b828add9?w=800',
                'opening_time' => '10:00',
                'closing_time' => '22:00',
                'capacity' => 40,
                'is_active' => true,
            ],
        ];

        foreach ($restaurants as $restaurant) {
            Restaurant::create($restaurant);
        }
    }
}
