<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\Room;
use App\Models\Reservation;
use Illuminate\Http\Request;

class AdminHotelsController extends Controller
{
    public function index(Request $request)
    {
        $query = Hotel::with(['wilaya', 'owner', 'images']);

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('city', 'like', "%{$search}%");
            });
        }

        if ($request->has('wilaya_id')) {
            $query->where('wilaya_id', $request->wilaya_id);
        }

        if ($request->has('verification_status')) {
            $query->where('verification_status', $request->verification_status);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $query->withCount('rooms');

        $hotels = $query->latest()->paginate($request->get('per_page', 15));

        return response()->json($hotels);
    }

    public function show(Hotel $hotel)
    {
        $hotel->load(['wilaya', 'owner', 'images', 'rooms.images']);
        $hotel->loadCount(['rooms', 'reservations']);

        // Get revenue stats
        $hotel->total_revenue = $hotel->reservations()->sum('total_price');
        $hotel->total_bookings = $hotel->reservations()->count();

        return response()->json($hotel);
    }

    public function update(Request $request, Hotel $hotel)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'city' => 'sometimes|string|max:255',
            'address' => 'nullable|string',
            'star_rating' => 'nullable|integer|min:1|max:5',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email',
            'wilaya_id' => 'sometimes|exists:wilayas,id',
            'is_active' => 'sometimes|boolean',
            'verification_status' => 'sometimes|in:pending,verified,rejected',
            'amenities' => 'nullable|array',
        ]);

        $hotel->update($validated);

        return response()->json([
            'message' => 'Hotel updated successfully',
            'hotel' => $hotel->fresh(['wilaya', 'owner']),
        ]);
    }

    public function verify(Hotel $hotel)
    {
        $hotel->update([
            'verification_status' => 'verified',
        ]);

        // Also verify the owner
        if ($hotel->owner) {
            $hotel->owner->update(['status' => 'active']);
        }

        return response()->json([
            'message' => 'Hotel verified successfully',
            'hotel' => $hotel->fresh(),
        ]);
    }

    public function toggleActive(Hotel $hotel)
    {
        $hotel->update([
            'is_active' => !$hotel->is_active,
        ]);

        return response()->json([
            'message' => $hotel->is_active ? 'Hotel activated' : 'Hotel deactivated',
            'hotel' => $hotel->fresh(),
        ]);
    }

    public function destroy(Hotel $hotel)
    {
        // Check for active reservations
        if ($hotel->reservations()->whereIn('status', ['pending', 'confirmed'])->exists()) {
            return response()->json([
                'message' => 'Cannot delete hotel with active reservations.',
            ], 422);
        }

        $hotel->delete();

        return response()->json([
            'message' => 'Hotel deleted successfully',
        ]);
    }

    // Room Management
    public function rooms(Hotel $hotel)
    {
        $rooms = $hotel->rooms()->with('images')->get();
        return response()->json($rooms);
    }

    public function storeRoom(Request $request, Hotel $hotel)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'room_type' => 'required|string|max:100',
            'price_per_night' => 'required|numeric|min:0',
            'capacity' => 'required|integer|min:1',
            'size' => 'nullable|numeric',
            'bed_type' => 'nullable|string|max:100',
            'amenities' => 'nullable|array',
            'is_available' => 'boolean',
        ]);

        $validated['hotel_id'] = $hotel->id;
        $room = Room::create($validated);

        return response()->json([
            'message' => 'Room created successfully',
            'room' => $room,
        ], 201);
    }

    public function updateRoom(Request $request, Hotel $hotel, Room $room)
    {
        if ($room->hotel_id !== $hotel->id) {
            return response()->json(['message' => 'Room does not belong to this hotel'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'room_type' => 'sometimes|string|max:100',
            'price_per_night' => 'sometimes|numeric|min:0',
            'capacity' => 'sometimes|integer|min:1',
            'size' => 'nullable|numeric',
            'bed_type' => 'nullable|string|max:100',
            'amenities' => 'nullable|array',
            'is_available' => 'boolean',
        ]);

        $room->update($validated);

        return response()->json([
            'message' => 'Room updated successfully',
            'room' => $room->fresh(),
        ]);
    }

    public function destroyRoom(Hotel $hotel, Room $room)
    {
        if ($room->hotel_id !== $hotel->id) {
            return response()->json(['message' => 'Room does not belong to this hotel'], 404);
        }

        // Check for active reservations
        if ($room->reservations()->whereIn('status', ['pending', 'confirmed'])->exists()) {
            return response()->json([
                'message' => 'Cannot delete room with active reservations.',
            ], 422);
        }

        $room->delete();

        return response()->json([
            'message' => 'Room deleted successfully',
        ]);
    }

    // Booking/Reservation Management
    public function reservations(Request $request, Hotel $hotel)
    {
        $query = Reservation::with(['user', 'room'])
            ->whereHas('room', fn($q) => $q->where('hotel_id', $hotel->id));

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $reservations = $query->latest()->paginate($request->get('per_page', 15));

        return response()->json($reservations);
    }

    public function updateReservation(Request $request, Hotel $hotel, Reservation $reservation)
    {
        // Verify reservation belongs to this hotel
        if ($reservation->room->hotel_id !== $hotel->id) {
            return response()->json(['message' => 'Reservation does not belong to this hotel'], 404);
        }

        $validated = $request->validate([
            'status' => 'required|in:pending,confirmed,checked_in,checked_out,cancelled',
        ]);

        $reservation->update($validated);

        return response()->json([
            'message' => 'Reservation updated successfully',
            'reservation' => $reservation->fresh(['user', 'room']),
        ]);
    }
}
