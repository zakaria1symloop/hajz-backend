<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class CompanyWallet extends Model
{
    use HasFactory;

    protected $fillable = [
        'car_rental_company_id',
        'pending_balance',
        'available_balance',
        'total_earned',
        'total_withdrawn',
    ];

    protected function casts(): array
    {
        return [
            'pending_balance' => 'decimal:2',
            'available_balance' => 'decimal:2',
            'total_earned' => 'decimal:2',
            'total_withdrawn' => 'decimal:2',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(CarRentalCompany::class, 'car_rental_company_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class, 'company_wallet_id')->orderBy('created_at', 'desc');
    }

    public function withdrawalRequests(): HasMany
    {
        return $this->hasMany(WithdrawalRequest::class, 'company_wallet_id')->orderBy('created_at', 'desc');
    }

    public function creditPendingBalance(float $amount, ?int $bookingId = null, ?string $description = null): WalletTransaction
    {
        return DB::transaction(function () use ($amount, $bookingId, $description) {
            $balanceBefore = $this->pending_balance;
            $this->pending_balance += $amount;
            $this->total_earned += $amount;
            $this->save();

            return $this->transactions()->create([
                'car_booking_id' => $bookingId,
                'type' => 'booking_credit',
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $this->pending_balance,
                'description' => $description ?? 'Booking payment credited',
            ]);
        });
    }

    public function releaseToAvailable(float $amount, ?int $bookingId = null): WalletTransaction
    {
        return DB::transaction(function () use ($amount, $bookingId) {
            $balanceBefore = $this->available_balance;
            $this->pending_balance -= $amount;
            $this->available_balance += $amount;
            $this->save();

            return $this->transactions()->create([
                'car_booking_id' => $bookingId,
                'type' => 'balance_release',
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $this->available_balance,
                'description' => 'Balance released after rental completion',
            ]);
        });
    }

    public function holdForWithdrawal(float $amount): void
    {
        DB::transaction(function () use ($amount) {
            $this->available_balance -= $amount;
            $this->save();
        });
    }

    public function releaseHeldFunds(float $amount): void
    {
        DB::transaction(function () use ($amount) {
            $this->available_balance += $amount;
            $this->save();
        });
    }

    public function processWithdrawal(float $amount): WalletTransaction
    {
        return DB::transaction(function () use ($amount) {
            $this->total_withdrawn += $amount;
            $this->save();

            return $this->transactions()->create([
                'type' => 'withdrawal',
                'amount' => -$amount,
                'balance_before' => $this->available_balance + $amount,
                'balance_after' => $this->available_balance,
                'description' => 'Withdrawal completed',
            ]);
        });
    }

    public function getTotalBalance(): float
    {
        return $this->pending_balance + $this->available_balance;
    }
}
