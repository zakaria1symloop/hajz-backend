<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CarBooking extends Model
{
    use HasFactory;

    protected $fillable = [
        'car_id',
        'car_rental_company_id',
        'user_id',
        'customer_name',
        'customer_email',
        'customer_phone',
        'customer_id_number',
        'driver_license_number',
        'pickup_date',
        'pickup_time',
        'return_date',
        'return_time',
        'pickup_location',
        'return_location',
        'pickup_mileage',
        'return_mileage',
        'km_driven',
        'extra_km',
        'extra_km_charge',
        'rental_days',
        'price_per_day',
        'subtotal',
        'deposit_amount',
        'extra_charges',
        'extra_charges_description',
        'extra_charges_reason',
        'total_amount',
        'status',
        'payment_status',
        'payment_id',
        'notes',
        'pickup_notes',
        'return_notes',
        'cancellation_reason',
        'confirmed_at',
        'picked_up_at',
        'returned_at',
        'cancelled_at',
    ];

    protected $casts = [
        'pickup_date' => 'date',
        'return_date' => 'date',
        'price_per_day' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'deposit_amount' => 'decimal:2',
        'extra_charges' => 'decimal:2',
        'extra_km_charge' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'confirmed_at' => 'datetime',
        'picked_up_at' => 'datetime',
        'returned_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(CarRentalCompany::class, 'car_rental_company_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }

    public function isPickedUp(): bool
    {
        return $this->status === 'picked_up';
    }

    public function isReturned(): bool
    {
        return $this->status === 'returned';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function calculateTotalAmount(): float
    {
        return ($this->price_per_day * $this->rental_days) + $this->extra_charges;
    }
}
