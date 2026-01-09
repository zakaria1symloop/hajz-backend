<?php

namespace App\Http\Controllers;

use App\Models\TableReservation;
use App\Models\CarBooking;
use Illuminate\Http\Request;

class UserBookingsController extends Controller
{
    /**
     * Get user's table reservations
     */
    public function tableReservations(Request $request)
    {
        $reservations = TableReservation::where('user_id', $request->user()->id)
            ->with(['restaurant.images', 'table', 'plats'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        // Add primary_image_url to restaurant
        $reservations->getCollection()->transform(function ($reservation) {
            if ($reservation->restaurant && $reservation->restaurant->images->isNotEmpty()) {
                $reservation->restaurant->primary_image_url = $reservation->restaurant->images->first()->image_url ?? null;
            }
            return $reservation;
        });

        return response()->json($reservations);
    }

    /**
     * Cancel a table reservation
     */
    public function cancelTableReservation(Request $request, TableReservation $tableReservation)
    {
        // Verify ownership
        if ($tableReservation->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (!$tableReservation->isPending()) {
            return response()->json([
                'message' => 'Only pending reservations can be cancelled'
            ], 422);
        }

        $tableReservation->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => $request->reason ?? 'Cancelled by customer'
        ]);

        return response()->json([
            'message' => 'Reservation cancelled successfully',
            'reservation' => $tableReservation
        ]);
    }

    /**
     * Get user's car bookings
     */
    public function carBookings(Request $request)
    {
        $bookings = CarBooking::where('user_id', $request->user()->id)
            ->with(['car.images', 'company'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        // Add primary_image_url to car
        $bookings->getCollection()->transform(function ($booking) {
            if ($booking->car && $booking->car->images->isNotEmpty()) {
                $booking->car->primary_image_url = $booking->car->images->first()->image_url ?? null;
            }
            return $booking;
        });

        return response()->json($bookings);
    }

    /**
     * Cancel a car booking
     */
    public function cancelCarBooking(Request $request, CarBooking $carBooking)
    {
        // Verify ownership
        if ($carBooking->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (!$carBooking->isPending()) {
            return response()->json([
                'message' => 'Only pending bookings can be cancelled'
            ], 422);
        }

        $carBooking->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => $request->reason ?? 'Cancelled by customer'
        ]);

        return response()->json([
            'message' => 'Booking cancelled successfully',
            'booking' => $carBooking
        ]);
    }
}
