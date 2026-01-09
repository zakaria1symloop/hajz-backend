<?php

namespace App\Http\Controllers\HotelOwner;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Services\WalletService;
use App\Services\RoomAvailabilityService;
use Illuminate\Http\Request;

class ReservationController extends Controller
{
    public function __construct(
        protected WalletService $walletService,
        protected RoomAvailabilityService $roomAvailabilityService
    ) {}

    public function index(Request $request)
    {
        $hotel = $request->user()->hotel;

        if (!$hotel) {
            return response()->json([
                'message' => 'No hotel found.',
            ], 404);
        }

        $query = Reservation::query()
            ->where(function ($q) use ($hotel) {
                $q->where(function ($q2) use ($hotel) {
                    $q2->where('reservable_type', 'App\\Models\\Hotel')
                       ->where('reservable_id', $hotel->id);
                })->orWhereHas('room', function ($q2) use ($hotel) {
                    $q2->where('hotel_id', $hotel->id);
                });
            })
            ->with(['user', 'room', 'payment'])
            ->orderBy('created_at', 'desc');

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->has('from_date')) {
            $query->where('check_in', '>=', $request->from_date);
        }
        if ($request->has('to_date')) {
            $query->where('check_out', '<=', $request->to_date);
        }

        $reservations = $query->paginate(20);

        return response()->json($reservations);
    }

    public function stats(Request $request)
    {
        $hotel = $request->user()->hotel;

        if (!$hotel) {
            return response()->json([
                'message' => 'No hotel found.',
            ], 404);
        }

        $baseQuery = Reservation::query()
            ->where(function ($q) use ($hotel) {
                $q->where(function ($q2) use ($hotel) {
                    $q2->where('reservable_type', 'App\\Models\\Hotel')
                       ->where('reservable_id', $hotel->id);
                })->orWhereHas('room', function ($q2) use ($hotel) {
                    $q2->where('hotel_id', $hotel->id);
                });
            });

        // Today's stats
        $today = now()->toDateString();
        $todayConfirmed = (clone $baseQuery)->where('status', 'confirmed')
            ->whereDate('check_in', '<=', $today)
            ->whereDate('check_out', '>=', $today)
            ->count();

        $todayPending = (clone $baseQuery)->where('status', 'pending')->count();
        $todayCheckedIn = (clone $baseQuery)->where('status', 'checked_in')->count();

        // Overall stats
        $totalReservations = (clone $baseQuery)->count();
        $completedReservations = (clone $baseQuery)->where('status', 'completed')->count();
        $cancelledReservations = (clone $baseQuery)->where('status', 'cancelled')->count();

        // Revenue stats
        $totalRevenue = (clone $baseQuery)
            ->whereIn('status', ['confirmed', 'checked_in', 'checked_out', 'completed'])
            ->sum('total_price');

        return response()->json([
            'today' => [
                'confirmed' => $todayConfirmed,
                'pending' => $todayPending,
                'checked_in' => $todayCheckedIn,
            ],
            'overall' => [
                'total' => $totalReservations,
                'completed' => $completedReservations,
                'cancelled' => $cancelledReservations,
            ],
            'revenue' => [
                'total' => $totalRevenue,
            ],
        ]);
    }

    public function show(Request $request, Reservation $reservation)
    {
        $hotel = $request->user()->hotel;

        if (!$hotel) {
            return response()->json([
                'message' => 'No hotel found.',
            ], 404);
        }

        // Verify reservation belongs to this hotel
        $belongsToHotel = ($reservation->reservable_type === 'App\\Models\\Hotel' && $reservation->reservable_id === $hotel->id)
            || ($reservation->room && $reservation->room->hotel_id === $hotel->id);

        if (!$belongsToHotel) {
            return response()->json([
                'message' => 'Reservation not found.',
            ], 404);
        }

        return response()->json([
            'reservation' => $reservation->load(['user', 'room', 'payment', 'roomBookings']),
        ]);
    }

    public function confirm(Request $request, Reservation $reservation)
    {
        $hotel = $request->user()->hotel;

        if (!$hotel) {
            return response()->json([
                'message' => 'No hotel found.',
            ], 404);
        }

        // Verify reservation belongs to this hotel
        $belongsToHotel = ($reservation->reservable_type === 'App\\Models\\Hotel' && $reservation->reservable_id === $hotel->id)
            || ($reservation->room && $reservation->room->hotel_id === $hotel->id);

        if (!$belongsToHotel) {
            return response()->json([
                'message' => 'Reservation not found.',
            ], 404);
        }

        if (!$reservation->isPending()) {
            return response()->json([
                'message' => 'Only pending reservations can be confirmed.',
            ], 422);
        }

        $reservation->confirm();

        return response()->json([
            'message' => 'Reservation confirmed successfully',
            'reservation' => $reservation->fresh()->load(['user', 'room', 'payment']),
        ]);
    }

    public function cancel(Request $request, Reservation $reservation)
    {
        $hotel = $request->user()->hotel;

        if (!$hotel) {
            return response()->json([
                'message' => 'No hotel found.',
            ], 404);
        }

        // Verify reservation belongs to this hotel
        $belongsToHotel = ($reservation->reservable_type === 'App\\Models\\Hotel' && $reservation->reservable_id === $hotel->id)
            || ($reservation->room && $reservation->room->hotel_id === $hotel->id);

        if (!$belongsToHotel) {
            return response()->json([
                'message' => 'Reservation not found.',
            ], 404);
        }

        if ($reservation->isCompleted() || $reservation->isCancelled()) {
            return response()->json([
                'message' => 'This reservation cannot be cancelled.',
            ], 422);
        }

        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $reservation->cancel($validated['reason'] ?? 'Cancelled by hotel');

        return response()->json([
            'message' => 'Reservation cancelled successfully',
            'reservation' => $reservation->fresh()->load(['user', 'room', 'payment']),
        ]);
    }

    public function checkIn(Request $request, Reservation $reservation)
    {
        $hotel = $request->user()->hotel;

        if (!$hotel) {
            return response()->json([
                'message' => 'No hotel found.',
            ], 404);
        }

        // Verify reservation belongs to this hotel
        $belongsToHotel = ($reservation->reservable_type === 'App\\Models\\Hotel' && $reservation->reservable_id === $hotel->id)
            || ($reservation->room && $reservation->room->hotel_id === $hotel->id);

        if (!$belongsToHotel) {
            return response()->json([
                'message' => 'Reservation not found.',
            ], 404);
        }

        if ($reservation->status !== 'confirmed') {
            return response()->json([
                'message' => 'Only confirmed reservations can be checked in.',
            ], 422);
        }

        $reservation->update([
            'status' => 'checked_in',
            'checked_in_at' => now(),
        ]);

        return response()->json([
            'message' => 'Guest checked in successfully',
            'reservation' => $reservation->fresh()->load(['user', 'room', 'payment']),
        ]);
    }

    public function checkOut(Request $request, Reservation $reservation)
    {
        $hotel = $request->user()->hotel;

        if (!$hotel) {
            return response()->json([
                'message' => 'No hotel found.',
            ], 404);
        }

        // Verify reservation belongs to this hotel
        $belongsToHotel = ($reservation->reservable_type === 'App\\Models\\Hotel' && $reservation->reservable_id === $hotel->id)
            || ($reservation->room && $reservation->room->hotel_id === $hotel->id);

        if (!$belongsToHotel) {
            return response()->json([
                'message' => 'Reservation not found.',
            ], 404);
        }

        if ($reservation->status !== 'checked_in') {
            return response()->json([
                'message' => 'Only checked-in reservations can be checked out.',
            ], 422);
        }

        $reservation->update([
            'status' => 'checked_out',
            'checked_out_at' => now(),
        ]);

        // Mark as completed
        $reservation->complete();

        // Release room dates for the completed reservation
        if ($reservation->room_id) {
            $this->roomAvailabilityService->releaseDatesForReservation($reservation);
        }

        // Release funds from pending to available balance
        $wallet = $hotel->wallet()->first();
        if ($wallet) {
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
            }
        }

        return response()->json([
            'message' => 'Guest checked out successfully',
            'reservation' => $reservation->fresh()->load(['user', 'room', 'payment']),
        ]);
    }
}
