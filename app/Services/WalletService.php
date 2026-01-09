<?php

namespace App\Services;

use App\Models\AdminSetting;
use App\Models\Hotel;
use App\Models\HotelWallet;
use App\Models\Restaurant;
use App\Models\RestaurantWallet;
use App\Models\CarRentalCompany;
use App\Models\CompanyWallet;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\TableReservation;
use App\Models\CarBooking;
use Illuminate\Support\Facades\DB;

class WalletService
{
    public function processPayment(Payment $payment): void
    {
        // Ensure reservation is loaded
        $payment->load('reservation');
        $reservation = $payment->reservation;

        if (!$reservation) {
            return;
        }

        $hotel = $this->getHotelFromReservation($reservation);

        if (!$hotel) {
            return;
        }

        $wallet = $hotel->getOrCreateWallet();
        $totalAmount = (float) $payment->amount;
        $commissionRate = AdminSetting::getCommissionRate();
        $commissionAmount = $totalAmount * ($commissionRate / 100);
        $hotelAmount = $totalAmount - $commissionAmount;

        DB::transaction(function () use ($wallet, $hotelAmount, $commissionAmount, $reservation) {
            // Credit hotel's pending balance (minus commission)
            $wallet->creditPendingBalance(
                $hotelAmount,
                $reservation->id,
                "Booking #{$reservation->id} payment received"
            );

            // Record commission deduction for transparency
            $wallet->deductCommission($commissionAmount, $reservation->id);
        });
    }

    public function releaseCompletedBookingFunds(): int
    {
        $count = 0;

        // Find completed reservations with pending funds
        $reservations = Reservation::where('status', 'completed')
            ->whereNotNull('completed_at')
            ->where('completed_at', '<', now())
            ->whereHas('payment', function ($query) {
                $query->where('status', 'paid');
            })
            ->get();

        foreach ($reservations as $reservation) {
            $hotel = $this->getHotelFromReservation($reservation);

            if (!$hotel || !$hotel->wallet) {
                continue;
            }

            $wallet = $hotel->wallet;

            // Check if already released (no pending balance for this reservation)
            $alreadyReleased = $wallet->transactions()
                ->where('reservation_id', $reservation->id)
                ->where('type', 'balance_release')
                ->exists();

            if ($alreadyReleased) {
                continue;
            }

            // Get the booking credit amount
            $creditTransaction = $wallet->transactions()
                ->where('reservation_id', $reservation->id)
                ->where('type', 'booking_credit')
                ->first();

            if ($creditTransaction) {
                $wallet->releaseToAvailable(
                    (float) $creditTransaction->amount,
                    $reservation->id
                );
                $count++;
            }
        }

        return $count;
    }

    public function processWithdrawal(HotelWallet $wallet, float $amount): bool
    {
        if ($wallet->available_balance < $amount) {
            return false;
        }

        $wallet->processWithdrawal($amount);
        return true;
    }

    public function refundReservation(Reservation $reservation): void
    {
        $hotel = $this->getHotelFromReservation($reservation);

        if (!$hotel || !$hotel->wallet) {
            return;
        }

        $wallet = $hotel->wallet;

        // Find the original credit
        $creditTransaction = $wallet->transactions()
            ->where('reservation_id', $reservation->id)
            ->where('type', 'booking_credit')
            ->first();

        if (!$creditTransaction) {
            return;
        }

        $refundAmount = (float) $creditTransaction->amount;

        DB::transaction(function () use ($wallet, $refundAmount, $reservation) {
            // Check if still in pending or already released
            if ($wallet->pending_balance >= $refundAmount) {
                $wallet->pending_balance -= $refundAmount;
            } else {
                $wallet->available_balance -= $refundAmount;
            }

            $wallet->total_earned -= $refundAmount;
            $wallet->save();

            $wallet->transactions()->create([
                'reservation_id' => $reservation->id,
                'type' => 'refund',
                'amount' => -$refundAmount,
                'balance_before' => $wallet->available_balance + $refundAmount,
                'balance_after' => $wallet->available_balance,
                'description' => "Refund for cancelled booking #{$reservation->id}",
            ]);
        });
    }

    protected function getHotelFromReservation(Reservation $reservation): ?Hotel
    {
        // Load room with hotel if not loaded
        if ($reservation->room_id) {
            $reservation->load('room.hotel');
            if ($reservation->room && $reservation->room->hotel) {
                return $reservation->room->hotel;
            }
        }

        // Check if reservable is a hotel
        if ($reservation->reservable_type === Hotel::class ||
            $reservation->reservable_type === 'App\\Models\\Hotel') {
            return Hotel::find($reservation->reservable_id);
        }

        return null;
    }

    public function getWalletStats(HotelWallet $wallet): array
    {
        return [
            'pending_balance' => $wallet->pending_balance,
            'available_balance' => $wallet->available_balance,
            'total_balance' => $wallet->getTotalBalance(),
            'total_earned' => $wallet->total_earned,
            'total_withdrawn' => $wallet->total_withdrawn,
            'pending_withdrawals' => $wallet->withdrawalRequests()
                ->where('status', 'pending')
                ->sum('amount'),
        ];
    }

    /**
     * Process payment for table reservation (restaurant)
     */
    public function processTableReservationPayment(Payment $payment, TableReservation $reservation): void
    {
        $restaurant = $reservation->restaurant;

        if (!$restaurant) {
            return;
        }

        $wallet = $restaurant->getOrCreateWallet();
        $totalAmount = (float) $payment->amount;
        $commissionRate = AdminSetting::getCommissionRate();
        $commissionAmount = $totalAmount * ($commissionRate / 100);
        $restaurantAmount = $totalAmount - $commissionAmount;

        DB::transaction(function () use ($wallet, $restaurantAmount, $commissionAmount, $reservation) {
            // Credit restaurant's pending balance (minus commission)
            $wallet->creditPendingBalance(
                $restaurantAmount,
                $reservation->id,
                "Table Reservation #{$reservation->id} payment received"
            );

            // Record commission deduction for transparency
            $wallet->deductCommission($commissionAmount, $reservation->id);
        });
    }

    /**
     * Process payment for car booking
     */
    public function processCarBookingPayment(Payment $payment, CarBooking $booking): void
    {
        $company = $booking->carRentalCompany;

        if (!$company) {
            return;
        }

        $wallet = $company->getOrCreateWallet();
        $totalAmount = (float) $payment->amount;
        $commissionRate = AdminSetting::getCommissionRate();
        $commissionAmount = $totalAmount * ($commissionRate / 100);
        $companyAmount = $totalAmount - $commissionAmount;

        DB::transaction(function () use ($wallet, $companyAmount, $commissionAmount, $booking) {
            // Credit company's pending balance (minus commission)
            $wallet->creditPendingBalance(
                $companyAmount,
                $booking->id,
                "Car Booking #{$booking->id} payment received"
            );

            // Record commission if wallet has that method
            if (method_exists($wallet, 'deductCommission')) {
                $wallet->transactions()->create([
                    'car_booking_id' => $booking->id,
                    'type' => 'commission_deduction',
                    'amount' => -$commissionAmount,
                    'balance_before' => $wallet->pending_balance,
                    'balance_after' => $wallet->pending_balance,
                    'description' => 'Commission deducted by platform',
                ]);
            }
        });
    }
}
