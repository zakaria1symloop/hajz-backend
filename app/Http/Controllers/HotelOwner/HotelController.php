<?php

namespace App\Http\Controllers\HotelOwner;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\HotelImage;
use App\Models\Wilaya;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class HotelController extends Controller
{
    public function show(Request $request)
    {
        $hotel = $request->user()->hotel;

        if (!$hotel) {
            return response()->json([
                'message' => 'No hotel found. Please create your hotel first.',
                'hotel' => null,
            ]);
        }

        return response()->json([
            'hotel' => $hotel->load(['images', 'rooms', 'wallet']),
        ]);
    }

    public function store(Request $request)
    {
        if ($request->user()->hotel) {
            return response()->json([
                'message' => 'You already have a hotel registered.',
            ], 422);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'address' => 'required|string|max:255',
            'city' => 'required|string|max:100',
            'state' => 'required|string|max:100',
            'wilaya_id' => 'nullable|exists:wilayas,id',
            'zip_code' => 'nullable|string|max:20',
            'star_rating' => 'nullable|integer|min:1|max:5',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|url|max:255',
            'check_in_time' => 'nullable|string',
            'check_out_time' => 'nullable|string',
            'amenities' => 'nullable|array',
            'cancellation_policy' => 'nullable|string',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);

        // Auto-detect wilaya_id from state name if not provided
        if (empty($validated['wilaya_id']) && !empty($validated['state'])) {
            $wilaya = Wilaya::where('name', $validated['state'])
                ->orWhere('name_ar', $validated['state'])
                ->first();
            if ($wilaya) {
                $validated['wilaya_id'] = $wilaya->id;
            }
        }

        $hotel = Hotel::create([
            'hotel_owner_id' => $request->user()->id,
            ...$validated,
            'country' => 'Algeria',
            'is_active' => true,
            'verification_status' => 'pending',
        ]);

        // Create wallet for the hotel
        $hotel->getOrCreateWallet();

        return response()->json([
            'message' => 'Hotel created successfully. Pending verification.',
            'hotel' => $hotel->load(['images', 'wallet']),
        ], 201);
    }

    public function update(Request $request)
    {
        $hotel = $request->user()->hotel;

        if (!$hotel) {
            return response()->json([
                'message' => 'No hotel found.',
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'address' => 'sometimes|string|max:255',
            'city' => 'sometimes|string|max:100',
            'state' => 'sometimes|string|max:100',
            'wilaya_id' => 'nullable|exists:wilayas,id',
            'zip_code' => 'nullable|string|max:20',
            'star_rating' => 'nullable|integer|min:1|max:5',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|url|max:255',
            'check_in_time' => 'nullable|string',
            'check_out_time' => 'nullable|string',
            'amenities' => 'nullable|array',
            'cancellation_policy' => 'nullable|string',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'is_active' => 'sometimes|boolean',
        ]);

        // Auto-detect wilaya_id from state name if state changed
        if (!empty($validated['state']) && empty($validated['wilaya_id'])) {
            $wilaya = Wilaya::where('name', $validated['state'])
                ->orWhere('name_ar', $validated['state'])
                ->first();
            if ($wilaya) {
                $validated['wilaya_id'] = $wilaya->id;
            }
        }

        $hotel->update($validated);

        return response()->json([
            'message' => 'Hotel updated successfully',
            'hotel' => $hotel->fresh()->load(['images', 'rooms', 'wallet']),
        ]);
    }

    public function uploadImages(Request $request)
    {
        $hotel = $request->user()->hotel;

        if (!$hotel) {
            return response()->json([
                'message' => 'No hotel found.',
            ], 404);
        }

        $request->validate([
            'images' => 'required|array|min:1',
            'images.*' => 'required|image|mimes:jpeg,png,jpg,webp|max:5120',
        ]);

        $uploadedImages = [];

        foreach ($request->file('images') as $index => $image) {
            $path = $image->store('hotels/' . $hotel->id, 'public');

            $hotelImage = HotelImage::create([
                'hotel_id' => $hotel->id,
                'image_path' => $path,
                'is_primary' => $hotel->images()->count() === 0 && $index === 0,
                'sort_order' => $hotel->images()->count(),
            ]);

            $uploadedImages[] = $hotelImage;
        }

        return response()->json([
            'message' => 'Images uploaded successfully',
            'images' => $uploadedImages,
        ]);
    }

    public function deleteImage(Request $request, HotelImage $image)
    {
        $hotel = $request->user()->hotel;

        if (!$hotel || $image->hotel_id !== $hotel->id) {
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

    public function setImagePrimary(Request $request, HotelImage $image)
    {
        $hotel = $request->user()->hotel;

        if (!$hotel || $image->hotel_id !== $hotel->id) {
            return response()->json([
                'message' => 'Image not found.',
            ], 404);
        }

        // Reset all other images
        $hotel->images()->update(['is_primary' => false]);

        // Set this as primary
        $image->update(['is_primary' => true]);

        return response()->json([
            'message' => 'Primary image set successfully',
            'image' => $image,
        ]);
    }
}
