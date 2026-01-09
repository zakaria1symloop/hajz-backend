<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'hotel_wallet_id',
        'restaurant_wallet_id',
        'company_wallet_id',
        'reservation_id',
        'table_reservation_id',
        'car_booking_id',
        'type',
        'amount',
        'balance_before',
        'balance_after',
        'description',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'balance_before' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(HotelWallet::class, 'hotel_wallet_id');
    }

    public function restaurantWallet(): BelongsTo
    {
        return $this->belongsTo(RestaurantWallet::class, 'restaurant_wallet_id');
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function tableReservation(): BelongsTo
    {
        return $this->belongsTo(TableReservation::class, 'table_reservation_id');
    }

    public function companyWallet(): BelongsTo
    {
        return $this->belongsTo(CompanyWallet::class, 'company_wallet_id');
    }

    public function carBooking(): BelongsTo
    {
        return $this->belongsTo(CarBooking::class, 'car_booking_id');
    }

    public function isCredit(): bool
    {
        return $this->amount > 0;
    }

    public function isDebit(): bool
    {
        return $this->amount < 0;
    }
}
