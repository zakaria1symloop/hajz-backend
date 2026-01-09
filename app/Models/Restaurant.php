<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Restaurant extends Model
{
    use HasFactory;

    protected $fillable = [
        'restaurant_owner_id',
        'wilaya_id',
        'name',
        'name_ar',
        'description',
        'address',
        'city',
        'cuisine_type',
        'price_range',
        'rating',
        'image',
        'phone',
        'email',
        'opening_time',
        'closing_time',
        'total_tables',
        'verification_status',
        'rejection_reason',
        'verified_at',
        'is_active',
        'latitude',
        'longitude',
    ];

    protected $casts = [
        'price_range' => 'decimal:2',
        'is_active' => 'boolean',
        'verified_at' => 'datetime',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    protected $appends = ['primary_image_url'];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(RestaurantOwner::class, 'restaurant_owner_id');
    }

    public function wilaya(): BelongsTo
    {
        return $this->belongsTo(Wilaya::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(RestaurantImage::class)->orderBy('sort_order');
    }

    public function plats(): HasMany
    {
        return $this->hasMany(Plat::class);
    }

    public function tables(): HasMany
    {
        return $this->hasMany(RestaurantTable::class);
    }

    public function tableReservations(): HasMany
    {
        return $this->hasMany(TableReservation::class);
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(RestaurantWallet::class);
    }

    public function getOrCreateWallet(): RestaurantWallet
    {
        // First check if wallet exists in database
        $wallet = $this->wallet()->first();

        if ($wallet) {
            return $wallet;
        }

        // Create new wallet if it doesn't exist
        return $this->wallet()->create([
            'pending_balance' => 0,
            'available_balance' => 0,
            'total_earned' => 0,
            'total_withdrawn' => 0,
        ]);
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

    public function isVerified(): bool
    {
        return $this->verification_status === 'verified';
    }

    public function isPending(): bool
    {
        return $this->verification_status === 'pending';
    }
}
