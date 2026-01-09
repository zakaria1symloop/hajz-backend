<?php

namespace Database\Seeders;

use App\Models\Hotel;
use Illuminate\Database\Seeder;

class HotelSeeder extends Seeder
{
    public function run(): void
    {
        $hotels = [
            [
                'name' => 'Sofitel Algiers Hamma Garden',
                'description' => 'Luxurious 5-star hotel in the heart of Algiers with stunning views of the Mediterranean Sea and botanical garden.',
                'address' => 'Jardin Botanique du Hamma',
                'city' => 'Algiers',
                'country' => 'Algeria',
                'price_per_night' => 25000,
                'rating' => 5,
                'image' => 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=800',
                'amenities' => ['wifi', 'pool', 'spa', 'gym', 'restaurant', 'parking'],
                'rooms_available' => 50,
                'is_active' => true,
            ],
            [
                'name' => 'El Aurassi Hotel',
                'description' => 'Iconic hotel overlooking Algiers Bay with modern amenities and exceptional service.',
                'address' => '2 Boulevard Frantz Fanon',
                'city' => 'Algiers',
                'country' => 'Algeria',
                'price_per_night' => 18000,
                'rating' => 4,
                'image' => 'https://images.unsplash.com/photo-1542314831-068cd1dbfeeb?w=800',
                'amenities' => ['wifi', 'pool', 'restaurant', 'parking', 'conference'],
                'rooms_available' => 80,
                'is_active' => true,
            ],
            [
                'name' => 'Sheraton Oran Hotel',
                'description' => 'Premium beachfront hotel in Oran with world-class facilities and stunning sea views.',
                'address' => 'Front de Mer',
                'city' => 'Oran',
                'country' => 'Algeria',
                'price_per_night' => 22000,
                'rating' => 5,
                'image' => 'https://images.unsplash.com/photo-1582719508461-905c673771fd?w=800',
                'amenities' => ['wifi', 'pool', 'spa', 'gym', 'restaurant', 'beach'],
                'rooms_available' => 60,
                'is_active' => true,
            ],
            [
                'name' => 'Constantine Marriott Hotel',
                'description' => 'Modern hotel in the ancient city of Constantine with panoramic views of the gorges.',
                'address' => 'Boulevard Zighout Youcef',
                'city' => 'Constantine',
                'country' => 'Algeria',
                'price_per_night' => 15000,
                'rating' => 4,
                'image' => 'https://images.unsplash.com/photo-1571896349842-33c89424de2d?w=800',
                'amenities' => ['wifi', 'restaurant', 'parking', 'gym', 'conference'],
                'rooms_available' => 45,
                'is_active' => true,
            ],
            [
                'name' => 'Tassili Hotel Djanet',
                'description' => 'Desert oasis hotel near the Tassili n\'Ajjer mountains, perfect for adventure seekers.',
                'address' => 'Route de l\'Aeroport',
                'city' => 'Djanet',
                'country' => 'Algeria',
                'price_per_night' => 12000,
                'rating' => 4,
                'image' => 'https://images.unsplash.com/photo-1520250497591-112f2f40a3f4?w=800',
                'amenities' => ['wifi', 'restaurant', 'parking', 'tours', 'traditional'],
                'rooms_available' => 25,
                'is_active' => true,
            ],
            [
                'name' => 'Royal Tulip Skikda',
                'description' => 'Elegant seaside hotel with modern amenities and beautiful Mediterranean views.',
                'address' => 'Route Nationale',
                'city' => 'Skikda',
                'country' => 'Algeria',
                'price_per_night' => 14000,
                'rating' => 4,
                'image' => 'https://images.unsplash.com/photo-1563911302283-d2bc129e7570?w=800',
                'amenities' => ['wifi', 'pool', 'restaurant', 'beach', 'parking'],
                'rooms_available' => 40,
                'is_active' => true,
            ],
        ];

        foreach ($hotels as $hotel) {
            Hotel::create($hotel);
        }
    }
}
