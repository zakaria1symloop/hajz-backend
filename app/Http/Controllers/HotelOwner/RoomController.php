<?php

namespace App\Http\Controllers\HotelOwner;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\RoomImage;
use App\Services\RoomAvailabilityService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class RoomController extends Controller
{
    public function __construct(
        protected RoomAvailabilityService $availabilityService
    ) {}

    public function index(Request $request)
    {
        $hotel = $request->user()->hotel;

        if (!$hotel) {
            return response()->json([
                'message' => 'No hotel found.',
            ], 404);
        }

        $rooms = $hotel->rooms()
            ->with('images')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($rooms);
    }

    public function store(Request $request)
    {
        $hotel = $request->user()->hotel;

        if (!$hotel) {
            return response()->json([
                'message' => 'No hotel found.',
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:single,double,twin,triple,quad,suite,deluxe,presidential',
            'description' => 'nullable|string',
            'capacity' => 'required|integer|min:1|max:20',
            'bed_configuration' => 'nullable|string|max:100',
            'price_per_night' => 'required|numeric|min:0',
            'size_sqm' => 'nullable|integer|min:1',
            'amenities' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        $room = $hotel->rooms()->create($validated);

        return response()->json([
            'message' => 'Room created successfully',
            'room' => $room->load('images'),
        ], 201);
    }

    public function show(Request $request, Room $room)
    {
        $hotel = $request->user()->hotel;

        if (!$hotel || $room->hotel_id !== $hotel->id) {
            return response()->json([
                'message' => 'Room not found.',
            ], 404);
        }

        return response()->json([
            'room' => $room->load(['images', 'bookings']),
        ]);
    }

    public function update(Request $request, Room $room)
    {
        $hotel = $request->user()->hotel;

        if (!$hotel || $room->hotel_id !== $hotel->id) {
            return response()->json([
                'message' => 'Room not found.',
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'type' => 'sometimes|in:single,double,twin,triple,quad,suite,deluxe,presidential',
            'description' => 'nullable|string',
            'capacity' => 'sometimes|integer|min:1|max:20',
            'bed_configuration' => 'nullable|string|max:100',
            'price_per_night' => 'sometimes|numeric|min:0',
            'size_sqm' => 'nullable|integer|min:1',
            'amenities' => 'nullable|array',
            'is_active' => 'boolean',
            'status' => 'sometimes|in:available,maintenance,out_of_service',
        ]);

        $room->update($validated);

        return response()->json([
            'message' => 'Room updated successfully',
            'room' => $room->fresh()->load('images'),
        ]);
    }

    public function destroy(Request $request, Room $room)
    {
        $hotel = $request->user()->hotel;

        if (!$hotel || $room->hotel_id !== $hotel->id) {
            return response()->json([
                'message' => 'Room not found.',
            ], 404);
        }

        // Check for active reservations
        $hasActiveReservations = $room->reservations()
            ->whereIn('status', ['pending', 'confirmed'])
            ->exists();

        if ($hasActiveReservations) {
            return response()->json([
                'message' => 'Cannot delete room with active reservations.',
            ], 422);
        }

        // Delete images from storage
        foreach ($room->images as $image) {
            Storage::disk('public')->delete($image->image_path);
        }

        $room->delete();

        return response()->json([
            'message' => 'Room deleted successfully',
        ]);
    }

    public function uploadImages(Request $request, Room $room)
    {
        $hotel = $request->user()->hotel;

        if (!$hotel || $room->hotel_id !== $hotel->id) {
            return response()->json([
                'message' => 'Room not found.',
            ], 404);
        }

        $request->validate([
            'images' => 'required|array|min:1',
            'images.*' => 'required|image|mimes:jpeg,png,jpg,webp|max:5120',
        ]);

        $uploadedImages = [];

        foreach ($request->file('images') as $index => $image) {
            $path = $image->store('rooms/' . $room->id, 'public');

            $roomImage = RoomImage::create([
                'room_id' => $room->id,
                'image_path' => $path,
                'is_primary' => $room->images()->count() === 0 && $index === 0,
                'sort_order' => $room->images()->count(),
            ]);

            $uploadedImages[] = $roomImage;
        }

        return response()->json([
            'message' => 'Images uploaded successfully',
            'images' => $uploadedImages,
        ]);
    }

    public function deleteImage(Request $request, RoomImage $image)
    {
        $hotel = $request->user()->hotel;
        $room = $image->room;

        if (!$hotel || !$room || $room->hotel_id !== $hotel->id) {
            return response()->json([
                'message' => 'Image not found.',
            ], 404);
        }

        Storage::disk('public')->delete($image->image_path);
        $image->delete();

        return response()->json([
            'message' => 'Image deleted successfully',
        ]);
    }

    public function getCalendar(Request $request, Room $room)
    {
        $hotel = $request->user()->hotel;

        if (!$hotel || $room->hotel_id !== $hotel->id) {
            return response()->json([
                'message' => 'Room not found.',
            ], 404);
        }

        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
        ]);

        $calendar = $this->availabilityService->getCalendar(
            $room,
            Carbon::parse($validated['start_date']),
            Carbon::parse($validated['end_date'])
        );

        return response()->json([
            'room' => $room,
            'calendar' => $calendar,
        ]);
    }

    public function updateAvailability(Request $request, Room $room)
    {
        $hotel = $request->user()->hotel;

        if (!$hotel || $room->hotel_id !== $hotel->id) {
            return response()->json([
                'message' => 'Room not found.',
            ], 404);
        }

        $validated = $request->validate([
            'dates' => 'required|array|min:1',
            'dates.*' => 'required|date',
            'action' => 'required|in:block,unblock',
        ]);

        $dates = array_map(fn($date) => Carbon::parse($date)->format('Y-m-d'), $validated['dates']);

        if ($validated['action'] === 'block') {
            $this->availabilityService->blockSpecificDates($room, $dates);
            $message = 'Dates blocked successfully';
        } else {
            $this->availabilityService->unblockSpecificDates($room, $dates);
            $message = 'Dates unblocked successfully';
        }

        return response()->json([
            'message' => $message,
        ]);
    }
}
