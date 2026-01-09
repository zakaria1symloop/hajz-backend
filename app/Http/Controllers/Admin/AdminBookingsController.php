<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\TableReservation;
use App\Models\CarBooking;
use Illuminate\Http\Request;

class AdminBookingsController extends Controller
{
    public function hotelReservations(Request $request)
    {
        $query = Reservation::with(['user', 'room.hotel.wilaya', 'payment']);

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('guest_name', 'like', "%{$search}%")
                  ->orWhere('guest_email', 'like', "%{$search}%")
                  ->orWhereHas('room.hotel', function ($q2) use ($search) {
                      $q2->where('name', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('hotel_id')) {
            $query->whereHas('room', function ($q) use ($request) {
                $q->where('hotel_id', $request->hotel_id);
            });
        }

        $reservations = $query->latest()->paginate($request->get('per_page', 15));

        return response()->json($reservations);
    }

    public function showHotelReservation(Reservation $reservation)
    {
        $reservation->load(['user', 'room.hotel.wilaya', 'payment']);

        return response()->json($reservation);
    }

    public function updateHotelReservation(Request $request, Reservation $reservation)
    {
        $validated = $request->validate([
            'status' => 'sometimes|in:pending,confirmed,checked_in,checked_out,cancelled',
            'payment_status' => 'sometimes|in:pending,paid,refunded',
        ]);

        $reservation->update($validated);

        return response()->json([
            'message' => 'Reservation updated successfully',
            'reservation' => $reservation->fresh(),
        ]);
    }

    public function tableReservations(Request $request)
    {
        $query = TableReservation::with(['restaurant.wilaya', 'table']);

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('guest_name', 'like', "%{$search}%")
                  ->orWhere('guest_email', 'like', "%{$search}%")
                  ->orWhereHas('restaurant', function ($q2) use ($search) {
                      $q2->where('name', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('restaurant_id')) {
            $query->where('restaurant_id', $request->restaurant_id);
        }

        $reservations = $query->latest()->paginate($request->get('per_page', 15));

        return response()->json($reservations);
    }

    public function showTableReservation(TableReservation $reservation)
    {
        $reservation->load(['restaurant.wilaya', 'table', 'plats']);

        return response()->json($reservation);
    }

    public function updateTableReservation(Request $request, TableReservation $reservation)
    {
        $validated = $request->validate([
            'status' => 'sometimes|in:pending,confirmed,seated,completed,cancelled,no_show',
        ]);

        $reservation->update($validated);

        return response()->json([
            'message' => 'Reservation updated successfully',
            'reservation' => $reservation->fresh(),
        ]);
    }

    public function carBookings(Request $request)
    {
        $query = CarBooking::with(['car.company.wilaya', 'user']);

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('customer_name', 'like', "%{$search}%")
                  ->orWhere('customer_email', 'like', "%{$search}%")
                  ->orWhereHas('car.company', function ($q2) use ($search) {
                      $q2->where('name', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('company_id')) {
            $query->where('car_rental_company_id', $request->company_id);
        }

        $bookings = $query->latest()->paginate($request->get('per_page', 15));

        return response()->json($bookings);
    }

    public function showCarBooking(CarBooking $booking)
    {
        $booking->load(['car.company.wilaya', 'car.images', 'user']);

        return response()->json($booking);
    }

    public function updateCarBooking(Request $request, CarBooking $booking)
    {
        $validated = $request->validate([
            'status' => 'sometimes|in:pending,confirmed,picked_up,returned,cancelled',
            'payment_status' => 'sometimes|in:pending,paid,refunded',
        ]);

        $booking->update($validated);

        return response()->json([
            'message' => 'Booking updated successfully',
            'booking' => $booking->fresh(),
        ]);
    }
}
