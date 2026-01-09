<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class Room extends Model
{
    use HasFactory;

    protected $fillable = [
        'hotel_id',
        'name',
        'type',
        'description',
        'capacity',
        'bed_configuration',
        'price_per_night',
        'size_sqm',
        'amenities',
        'is_active',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'amenities' => 'array',
            'price_per_night' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(RoomImage::class)->orderBy('sort_order');
    }

    public function primaryImage(): HasMany
    {
        return $this->hasMany(RoomImage::class)->where('is_primary', true);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(RoomBooking::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->where('status', 'available');
    }

    public function scopeAvailableBetween(Builder $query, Carbon $checkIn, Carbon $checkOut): Builder
    {
        return $query->active()
            ->whereDoesntHave('bookings', function ($q) use ($checkIn, $checkOut) {
                $q->whereBetween('date', [$checkIn->format('Y-m-d'), $checkOut->subDay()->format('Y-m-d')]);
            });
    }

    public function scopeWithCapacity(Builder $query, int $guests): Builder
    {
        return $query->where('capacity', '>=', $guests);
    }

    public function isAvailableBetween(Carbon $checkIn, Carbon $checkOut): bool
    {
        if (!$this->is_active || $this->status !== 'available') {
            return false;
        }

        return !$this->bookings()
            ->whereBetween('date', [$checkIn->format('Y-m-d'), $checkOut->subDay()->format('Y-m-d')])
            ->exists();
    }

    public function getBookedDates(Carbon $startDate, Carbon $endDate): array
    {
        return $this->bookings()
            ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->pluck('date')
            ->map(fn($date) => $date->format('Y-m-d'))
            ->toArray();
    }
}
