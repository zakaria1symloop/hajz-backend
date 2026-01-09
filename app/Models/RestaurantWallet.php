<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class RestaurantWallet extends Model
{
    use HasFactory;

    protected $fillable = [
        'restaurant_id',
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

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class, 'restaurant_wallet_id')->orderBy('created_at', 'desc');
    }

    public function withdrawalRequests(): HasMany
    {
        return $this->hasMany(WithdrawalRequest::class, 'restaurant_wallet_id')->orderBy('created_at', 'desc');
    }

    public function creditPendingBalance(float $amount, ?int $reservationId = null, ?string $description = null): WalletTransaction
    {
        return DB::transaction(function () use ($amount, $reservationId, $description) {
            $balanceBefore = $this->pending_balance;
            $this->pending_balance += $amount;
            $this->total_earned += $amount;
            $this->save();

            return $this->transactions()->create([
                'table_reservation_id' => $reservationId,
                'type' => 'booking_credit',
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $this->pending_balance,
                'description' => $description ?? 'Reservation payment credited',
            ]);
        });
    }

    public function deductCommission(float $amount, ?int $reservationId = null): WalletTransaction
    {
        return DB::transaction(function () use ($amount, $reservationId) {
            return $this->transactions()->create([
                'table_reservation_id' => $reservationId,
                'type' => 'commission_deduction',
                'amount' => -$amount,
                'balance_before' => $this->pending_balance,
                'balance_after' => $this->pending_balance,
                'description' => 'Commission deducted by platform',
            ]);
        });
    }

    public function releaseToAvailable(float $amount, ?int $reservationId = null): WalletTransaction
    {
        return DB::transaction(function () use ($amount, $reservationId) {
            $balanceBefore = $this->available_balance;
            $this->pending_balance -= $amount;
            $this->available_balance += $amount;
            $this->save();

            return $this->transactions()->create([
                'table_reservation_id' => $reservationId,
                'type' => 'balance_release',
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $this->available_balance,
                'description' => 'Balance released after completed reservation',
            ]);
        });
    }

    public function creditAvailableBalance(float $amount, ?int $reservationId = null, ?string $description = null): WalletTransaction
    {
        return DB::transaction(function () use ($amount, $reservationId, $description) {
            $balanceBefore = $this->available_balance;
            $this->available_balance += $amount;
            $this->total_earned += $amount;
            $this->save();

            return $this->transactions()->create([
                'table_reservation_id' => $reservationId,
                'type' => 'earning',
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $this->available_balance,
                'description' => $description ?? 'Reservation earning credited',
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
            // Money already held, just update total_withdrawn
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
