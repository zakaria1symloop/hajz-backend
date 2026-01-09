<?php

namespace Database\Seeders;

use App\Models\Flight;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class FlightSeeder extends Seeder
{
    public function run(): void
    {
        $flights = [
            [
                'airline' => 'Air Algerie',
                'flight_number' => 'AH1001',
                'departure_city' => 'Algiers',
                'arrival_city' => 'Paris',
                'departure_time' => Carbon::now()->addDays(3)->setTime(8, 0),
                'arrival_time' => Carbon::now()->addDays(3)->setTime(11, 30),
                'price' => 45000,
                'seats_available' => 120,
                'class' => 'economy',
                'image' => 'https://images.unsplash.com/photo-1436491865332-7a61a109cc05?w=800',
                'is_active' => true,
            ],
            [
                'airline' => 'Air Algerie',
                'flight_number' => 'AH1002',
                'departure_city' => 'Algiers',
                'arrival_city' => 'Istanbul',
                'departure_time' => Carbon::now()->addDays(5)->setTime(14, 0),
                'arrival_time' => Carbon::now()->addDays(5)->setTime(19, 30),
                'price' => 38000,
                'seats_available' => 100,
                'class' => 'economy',
                'image' => 'https://images.unsplash.com/photo-1569629743817-70d8db6c323b?w=800',
                'is_active' => true,
            ],
            [
                'airline' => 'Tassili Airlines',
                'flight_number' => 'SF201',
                'departure_city' => 'Algiers',
                'arrival_city' => 'Oran',
                'departure_time' => Carbon::now()->addDays(1)->setTime(7, 0),
                'arrival_time' => Carbon::now()->addDays(1)->setTime(8, 15),
                'price' => 8500,
                'seats_available' => 80,
                'class' => 'economy',
                'image' => 'https://images.unsplash.com/photo-1464037866556-6812c9d1c72e?w=800',
                'is_active' => true,
            ],
            [
                'airline' => 'Air Algerie',
                'flight_number' => 'AH2050',
                'departure_city' => 'Oran',
                'arrival_city' => 'Dubai',
                'departure_time' => Carbon::now()->addDays(7)->setTime(23, 0),
                'arrival_time' => Carbon::now()->addDays(8)->setTime(8, 0),
                'price' => 65000,
                'seats_available' => 90,
                'class' => 'business',
                'image' => 'https://images.unsplash.com/photo-1529074963764-98f45c47344b?w=800',
                'is_active' => true,
            ],
            [
                'airline' => 'Tassili Airlines',
                'flight_number' => 'SF305',
                'departure_city' => 'Algiers',
                'arrival_city' => 'Constantine',
                'departure_time' => Carbon::now()->addDays(2)->setTime(10, 30),
                'arrival_time' => Carbon::now()->addDays(2)->setTime(11, 45),
                'price' => 7500,
                'seats_available' => 70,
                'class' => 'economy',
                'image' => 'https://images.unsplash.com/photo-1556388158-158ea5ccacbd?w=800',
                'is_active' => true,
            ],
            [
                'airline' => 'Air Algerie',
                'flight_number' => 'AH3001',
                'departure_city' => 'Constantine',
                'arrival_city' => 'Tunis',
                'departure_time' => Carbon::now()->addDays(4)->setTime(16, 0),
                'arrival_time' => Carbon::now()->addDays(4)->setTime(17, 30),
                'price' => 15000,
                'seats_available' => 85,
                'class' => 'economy',
                'image' => 'https://images.unsplash.com/photo-1517479149777-5f3b1511d5ad?w=800',
                'is_active' => true,
            ],
        ];

        foreach ($flights as $flight) {
            Flight::create($flight);
        }
    }
}
