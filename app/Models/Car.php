<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Car extends Model
{
    use HasFactory;

    protected $fillable = [
        'car_rental_company_id',
        'brand',
        'model',
        'year',
        'color',
        'license_plate',
        'type',
        'transmission',
        'fuel_type',
        'seats',
        'doors',
        'has_ac',
        'has_gps',
        'has_bluetooth',
        'has_usb',
        'has_child_seat',
        'price_per_day',
        'price_per_hour',
        'min_rental_days',
        'max_rental_days',
        'deposit_amount',
        'mileage_limit',
        'extra_km_price',
        'description',
        'features',
        'is_available',
        'is_featured',
    ];

    protected $casts = [
        'has_ac' => 'boolean',
        'has_gps' => 'boolean',
        'has_bluetooth' => 'boolean',
        'has_usb' => 'boolean',
        'has_child_seat' => 'boolean',
        'is_available' => 'boolean',
        'is_featured' => 'boolean',
        'price_per_day' => 'decimal:2',
        'price_per_hour' => 'decimal:2',
        'deposit_amount' => 'decimal:2',
        'extra_km_price' => 'decimal:2',
        'features' => 'array',
    ];

    protected $appends = ['primary_image_url', 'full_name'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(CarRentalCompany::class, 'car_rental_company_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(CarImage::class)->orderBy('sort_order');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(CarBooking::class);
    }

    public function getPrimaryImageUrlAttribute(): ?string
    {
        $primaryImage = $this->images()->where('is_primary', true)->first();
        if ($primaryImage) {
            return asset('storage/' . $primaryImage->image_path);
        }
        $firstImage = $this->images()->first();
        return $firstImage ? asset('storage/' . $firstImage->image_path) : null;
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->brand} {$this->model} ({$this->year})";
    }

    public function isAvailableForDates(string $pickupDate, string $returnDate): bool
    {
        return !$this->bookings()
            ->whereIn('status', ['pending', 'confirmed', 'picked_up'])
            ->where(function ($query) use ($pickupDate, $returnDate) {
                $query->whereBetween('pickup_date', [$pickupDate, $returnDate])
                    ->orWhereBetween('return_date', [$pickupDate, $returnDate])
                    ->orWhere(function ($q) use ($pickupDate, $returnDate) {
                        $q->where('pickup_date', '<=', $pickupDate)
                            ->where('return_date', '>=', $returnDate);
                    });
            })
            ->exists();
    }
}
