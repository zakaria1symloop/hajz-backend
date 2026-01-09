<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Reservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'reservable_type',
        'reservable_id',
        'room_id',
        'check_in',
        'check_out',
        'reservation_date',
        'guests',
        'total_price',
        'status',
        'special_requests',
        'confirmed_at',
        'completed_at',
        'cancelled_at',
        'cancellation_reason',
        'checked_in_at',
        'checked_out_at',
        'guest_name',
        'guest_email',
        'guest_phone',
    ];

    protected $casts = [
        'check_in' => 'date',
        'check_out' => 'date',
        'reservation_date' => 'datetime',
        'total_price' => 'decimal:2',
        'confirmed_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'checked_in_at' => 'datetime',
        'checked_out_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reservable()
    {
        return $this->morphTo();
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function roomBookings(): HasMany
    {
        return $this->hasMany(RoomBooking::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
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

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function confirm(): void
    {
        $this->update([
            'status' => 'confirmed',
            'confirmed_at' => now(),
        ]);
    }

    public function cancel(?string $reason = null): void
    {
        $this->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ]);

        // Release room bookings
        $this->roomBookings()->delete();
    }

    public function complete(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    public function getNightsCount(): int
    {
        if (!$this->check_in || !$this->check_out) {
            return 0;
        }
        return $this->check_in->diffInDays($this->check_out);
    }

    public function getHotel(): ?Hotel
    {
        if ($this->reservable_type === Hotel::class) {
            return $this->reservable;
        }
        if ($this->room) {
            return $this->room->hotel;
        }
        return null;
    }
}
