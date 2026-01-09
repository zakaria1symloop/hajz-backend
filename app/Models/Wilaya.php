<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Wilaya extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'name_ar',
        'code',
        'description',
        'image',
        'is_active',
        'is_featured',
        'sort_order',
    ];

    protected $appends = ['image_url', 'hotels_count', 'restaurants_count', 'car_rentals_count', 'total_services_count'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
        ];
    }

    public function getImageUrlAttribute(): ?string
    {
        if (!$this->image) return null;
        return asset('storage/' . $this->image);
    }

    public function getHotelsCountAttribute(): int
    {
        return $this->hotels()->count();
    }

    public function getRestaurantsCountAttribute(): int
    {
        return $this->restaurants()->count();
    }

    public function getCarRentalsCountAttribute(): int
    {
        return $this->carRentalCompanies()->count();
    }

    public function getTotalServicesCountAttribute(): int
    {
        return $this->hotels_count + $this->restaurants_count + $this->car_rentals_count;
    }

    public function hotels(): HasMany
    {
        return $this->hasMany(Hotel::class);
    }

    public function restaurants(): HasMany
    {
        return $this->hasMany(Restaurant::class);
    }

    public function carRentalCompanies(): HasMany
    {
        return $this->hasMany(CarRentalCompany::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }
}
