<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RestaurantTable extends Model
{
    use HasFactory;

    protected $fillable = [
        'restaurant_id',
        'name',
        'capacity',
        'price_per_hour',
        'location',
        'is_available',
        'description',
        'sort_order',
    ];

    protected $casts = [
        'is_available' => 'boolean',
        'price_per_hour' => 'decimal:2',
    ];

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(TableReservation::class);
    }

    public function isAvailableAt(string $date, string $time, int $duration = 120): bool
    {
        $startTime = strtotime("$date $time");
        $endTime = $startTime + ($duration * 60);

        return !$this->reservations()
            ->where('reservation_date', $date)
            ->whereIn('status', ['pending', 'confirmed'])
            ->get()
            ->contains(function ($reservation) use ($startTime, $endTime) {
                $resStart = strtotime("{$reservation->reservation_date} {$reservation->reservation_time}");
                $resEnd = $resStart + ($reservation->duration_minutes * 60);
                return ($startTime < $resEnd && $endTime > $resStart);
            });
    }
}
