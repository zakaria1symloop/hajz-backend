<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Flight extends Model
{
    use HasFactory;

    protected $fillable = [
        'airline',
        'flight_number',
        'departure_city',
        'arrival_city',
        'departure_time',
        'arrival_time',
        'price',
        'seats_available',
        'class',
        'image',
        'is_active',
    ];

    protected $casts = [
        'departure_time' => 'datetime',
        'arrival_time' => 'datetime',
        'price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function reservations()
    {
        return $this->morphMany(Reservation::class, 'reservable');
    }
}
