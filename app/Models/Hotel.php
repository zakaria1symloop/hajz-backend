<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Builder;

class Hotel extends Model
{
    use HasFactory;

    protected $fillable = [
        'hotel_owner_id',
        'wilaya_id',
        'name',
        'description',
        'address',
        'city',
        'state',
        'zip_code',
        'country',
        'price_per_night',
        'rating',
        'star_rating',
        'image',
        'amenities',
        'rooms_available',
        'is_active',
        'phone',
        'email',
        'website',
        'check_in_time',
        'check_out_time',
        'cancellation_policy',
        'verification_status',
        'rejection_reason',
        'verified_at',
        'latitude',
        'longitude',
    ];

    protected $casts = [
        'amenities' => 'array',
        'price_per_night' => 'decimal:2',
        'is_active' => 'boolean',
        'verified_at' => 'datetime',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(HotelOwner::class, 'hotel_owner_id');
    }

    public function wilaya(): BelongsTo
    {
        return $this->belongsTo(Wilaya::class);
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    public function activeRooms(): HasMany
    {
        return $this->hasMany(Room::class)->where('is_active', true)->where('status', 'available');
    }

    public function images(): HasMany
    {
        return $this->hasMany(HotelImage::class)->orderBy('sort_order');
    }

    public function primaryImage(): HasOne
    {
        return $this->hasOne(HotelImage::class)->where('is_primary', true);
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(HotelWallet::class);
    }

    public function reservations()
    {
        return $this->morphMany(Reservation::class, 'reservable');
    }

    public function scopeVerified(Builder $query): Builder
    {
        return $query->where('verification_status', 'verified');
    }

    public function scopePendingVerification(Builder $query): Builder
    {
        return $query->where('verification_status', 'pending');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->verified();
    }

    public function isVerified(): bool
    {
        return $this->verification_status === 'verified';
    }

    public function isPending(): bool
    {
        return $this->verification_status === 'pending';
    }

    public function isRejected(): bool
    {
        return $this->verification_status === 'rejected';
    }

    public function verify(): void
    {
        $this->update([
            'verification_status' => 'verified',
            'verified_at' => now(),
            'rejection_reason' => null,
        ]);
    }

    public function reject(string $reason): void
    {
        $this->update([
            'verification_status' => 'rejected',
            'rejection_reason' => $reason,
        ]);
    }

    public function getOrCreateWallet(): HotelWallet
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

    public function getMinRoomPrice(): ?float
    {
        return $this->activeRooms()->min('price_per_night');
    }

    public function getMaxRoomPrice(): ?float
    {
        return $this->activeRooms()->max('price_per_night');
    }
}
