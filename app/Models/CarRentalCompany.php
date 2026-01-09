<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CarRentalCompany extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_owner_id',
        'wilaya_id',
        'name',
        'name_ar',
        'description',
        'address',
        'city',
        'phone',
        'email',
        'website',
        'opening_time',
        'closing_time',
        'services',
        'is_active',
        'verification_status',
        'rejection_reason',
        'verified_at',
        'latitude',
        'longitude',
    ];

    protected $casts = [
        'services' => 'array',
        'is_active' => 'boolean',
        'verified_at' => 'datetime',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(CompanyOwner::class, 'company_owner_id');
    }

    public function wilaya(): BelongsTo
    {
        return $this->belongsTo(Wilaya::class);
    }

    public function cars(): HasMany
    {
        return $this->hasMany(Car::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(CarBooking::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(CompanyImage::class)->orderBy('sort_order');
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(CompanyWallet::class);
    }

    public function getOrCreateWallet(): CompanyWallet
    {
        if (!$this->wallet) {
            $this->wallet()->create([
                'pending_balance' => 0,
                'available_balance' => 0,
                'total_earned' => 0,
                'total_withdrawn' => 0,
            ]);
            $this->load('wallet');
        }
        return $this->wallet;
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
