<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WithdrawalRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'hotel_wallet_id',
        'restaurant_wallet_id',
        'company_wallet_id',
        'amount',
        'status',
        'bank_name',
        'account_number',
        'account_holder_name',
        'notes',
        'admin_notes',
        'processed_at',
        'processed_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'processed_at' => 'datetime',
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

    public function companyWallet(): BelongsTo
    {
        return $this->belongsTo(CompanyWallet::class, 'company_wallet_id');
    }

    public function getWallet()
    {
        return $this->wallet ?? $this->restaurantWallet ?? $this->companyWallet;
    }

    public function processedByAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function approve(int $adminId): void
    {
        $this->update([
            'status' => 'approved',
            'processed_by' => $adminId,
            'processed_at' => now(),
        ]);
    }

    public function reject(int $adminId, ?string $reason = null): void
    {
        $this->update([
            'status' => 'rejected',
            'processed_by' => $adminId,
            'processed_at' => now(),
            'admin_notes' => $reason,
        ]);

        // Return held funds to wallet
        $wallet = $this->getWallet();
        if ($wallet && method_exists($wallet, 'releaseHeldFunds')) {
            $wallet->releaseHeldFunds($this->amount);
        }
    }

    public function complete(): void
    {
        $this->update([
            'status' => 'completed',
        ]);

        $wallet = $this->getWallet();
        if ($wallet) {
            $wallet->processWithdrawal($this->amount);
        }
    }
}
