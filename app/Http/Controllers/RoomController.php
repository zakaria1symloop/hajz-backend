<?php

namespace App\Http\Controllers;

use App\Models\Hotel;
use App\Models\Room;
use App\Services\RoomAvailabilityService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class RoomController extends Controller
{
    public function __construct(
        protected RoomAvailabilityService $availabilityService
    ) {}

    public function index(Request $request, Hotel $hotel)
    {
        // Only show rooms from verified and active hotels
        if (!$hotel->isVerified() || !$hotel->is_active) {
            return response()->json([
                'message' => 'Hotel not available.',
            ], 404);
        }

        $query = $hotel->rooms()
            ->where('is_active', true)
            ->where('status', 'available')
            ->with('images');

        // Filter by capacity
        if ($request->has('guests')) {
            $query->where('capacity', '>=', (int) $request->guests);
        }

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by price range
        if ($request->has('min_price')) {
            $query->where('price_per_night', '>=', $request->min_price);
        }
        if ($request->has('max_price')) {
            $query->where('price_per_night', '<=', $request->max_price);
        }

        // Filter by availability if dates provided
        if ($request->has('check_in') && $request->has('check_out')) {
            $checkIn = Carbon::parse($request->check_in);
            $checkOut = Carbon::parse($request->check_out);

            $query->whereDoesntHave('bookings', function ($q) use ($checkIn, $checkOut) {
                $dates = [];
                $current = $checkIn->copy();
                while ($current < $checkOut) {
                    $dates[] = $current->format('Y-m-d');
                    $current->addDay();
                }
                $q->whereIn('date', $dates);
            });
        }

        $rooms = $query->orderBy('price_per_night', 'asc')->get();

        return response()->json([
            'hotel' => $hotel->load('images'),
            'rooms' => $rooms,
        ]);
    }

    public function show(Hotel $hotel, Room $room)
    {
        // Verify room belongs to hotel
        if ($room->hotel_id !== $hotel->id) {
            return response()->json([
                'message' => 'Room not found.',
            ], 404);
        }

        // Only show rooms from verified and active hotels
        if (!$hotel->isVerified() || !$hotel->is_active) {
            return response()->json([
                'message' => 'Hotel not available.',
            ], 404);
        }

        if (!$room->is_active) {
            return response()->json([
                'message' => 'Room not available.',
            ], 404);
        }

        return response()->json([
            'room' => $room->load(['images', 'hotel.images']),
        ]);
    }

    public function checkAvailability(Request $request, Room $room)
    {
        $validated = $request->validate([
            'check_in' => 'required|date|after_or_equal:today',
            'check_out' => 'required|date|after:check_in',
        ]);

        $checkIn = Carbon::parse($validated['check_in']);
        $checkOut = Carbon::parse($validated['check_out']);

        $isAvailable = $this->availabilityService->isRoomAvailable($room, $checkIn, $checkOut);
        $totalPrice = $this->availabilityService->calculatePrice($room, $checkIn, $checkOut);
        $nights = $checkIn->diffInDays($checkOut);

        return response()->json([
            'room_id' => $room->id,
            'check_in' => $checkIn->format('Y-m-d'),
            'check_out' => $checkOut->format('Y-m-d'),
            'is_available' => $isAvailable,
            'nights' => $nights,
            'price_per_night' => $room->price_per_night,
            'total_price' => $totalPrice,
        ]);
    }

    public function getBookedDates(Room $room)
    {
        // Get all booked dates for this room for the next 6 months
        $startDate = Carbon::today();
        $endDate = Carbon::today()->addMonths(6);

        $bookedDates = $room->bookings()
            ->whereBetween('date', [$startDate, $endDate])
            ->pluck('date')
            ->map(fn($date) => Carbon::parse($date)->format('Y-m-d'))
            ->unique()
            ->values()
            ->toArray();

        return response()->json([
            'room_id' => $room->id,
            'booked_dates' => $bookedDates,
        ]);
    }

    public function search(Request $request)
    {
        $validated = $request->validate([
            'city' => 'nullable|string',
            'wilaya' => 'nullable|string',
            'check_in' => 'nullable|date|after_or_equal:today',
            'check_out' => 'nullable|date|after:check_in',
            'guests' => 'nullable|integer|min:1',
            'min_price' => 'nullable|numeric|min:0',
            'max_price' => 'nullable|numeric|min:0',
            'type' => 'nullable|string',
        ]);

        $query = Room::query()
            ->with(['hotel.images', 'images'])
            ->whereHas('hotel', function ($q) {
                $q->where('verification_status', 'verified')
                  ->where('is_active', true);
            })
            ->where('is_active', true)
            ->where('status', 'available');

        // Filter by location
        if (!empty($validated['city'])) {
            $query->whereHas('hotel', function ($q) use ($validated) {
                $q->where('city', 'like', '%' . $validated['city'] . '%');
            });
        }
        if (!empty($validated['wilaya'])) {
            $query->whereHas('hotel', function ($q) use ($validated) {
                $q->where('wilaya', 'like', '%' . $validated['wilaya'] . '%');
            });
        }

        // Filter by guests
        if (!empty($validated['guests'])) {
            $query->where('capacity', '>=', $validated['guests']);
        }

        // Filter by price
        if (!empty($validated['min_price'])) {
            $query->where('price_per_night', '>=', $validated['min_price']);
        }
        if (!empty($validated['max_price'])) {
            $query->where('price_per_night', '<=', $validated['max_price']);
        }

        // Filter by type
        if (!empty($validated['type'])) {
            $query->where('type', $validated['type']);
        }

        // Filter by availability
        if (!empty($validated['check_in']) && !empty($validated['check_out'])) {
            $checkIn = Carbon::parse($validated['check_in']);
            $checkOut = Carbon::parse($validated['check_out']);

            $dates = [];
            $current = $checkIn->copy();
            while ($current < $checkOut) {
                $dates[] = $current->format('Y-m-d');
                $current->addDay();
            }

            $query->whereDoesntHave('bookings', function ($q) use ($dates) {
                $q->whereIn('date', $dates);
            });
        }

        $rooms = $query->orderBy('price_per_night', 'asc')->paginate(20);

        return response()->json($rooms);
    }
}
