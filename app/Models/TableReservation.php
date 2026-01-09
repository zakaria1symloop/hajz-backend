<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TableReservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'restaurant_id',
        'restaurant_table_id',
        'user_id',
        'guest_name',
        'guest_email',
        'guest_phone',
        'reservation_date',
        'reservation_time',
        'guests_count',
        'duration_minutes',
        'table_price_per_hour',
        'table_total',
        'special_requests',
        'plats_total',
        'total_amount',
        'commission_rate',
        'commission_amount',
        'net_amount',
        'status',
        'payment_status',
        'payment_id',
        'cancellation_reason',
        'confirmed_at',
        'cancelled_at',
    ];

    protected $casts = [
        'reservation_date' => 'date',
        'table_price_per_hour' => 'decimal:2',
        'table_total' => 'decimal:2',
        'plats_total' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'commission_rate' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'confirmed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(RestaurantTable::class, 'restaurant_table_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plats(): BelongsToMany
    {
        return $this->belongsToMany(Plat::class, 'reservation_plats')
            ->withPivot(['quantity', 'unit_price', 'total_price', 'notes'])
            ->withTimestamps();
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function calculatePlatsTotal(): float
    {
        return $this->plats->sum(fn($plat) => $plat->pivot->total_price);
    }
}
