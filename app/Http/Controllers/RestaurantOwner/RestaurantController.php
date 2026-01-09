<?php

namespace App\Http\Controllers\RestaurantOwner;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Models\RestaurantImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class RestaurantController extends Controller
{
    public function show(Request $request)
    {
        $restaurant = $request->user()->restaurant;

        if (!$restaurant) {
            return response()->json([
                'message' => 'No restaurant found. Please create your restaurant first.',
                'restaurant' => null,
            ]);
        }

        return response()->json([
            'restaurant' => $restaurant->load(['images', 'plats', 'tables', 'wilaya']),
        ]);
    }

    public function store(Request $request)
    {
        if ($request->user()->restaurant) {
            return response()->json([
                'message' => 'You already have a restaurant registered.',
            ], 422);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'name_ar' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'address' => 'required|string|max:255',
            'city' => 'required|string|max:100',
            'wilaya_id' => 'nullable|exists:wilayas,id',
            'cuisine_type' => 'nullable|string|max:100',
            'price_range' => 'nullable|numeric',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'opening_time' => 'nullable|string',
            'closing_time' => 'nullable|string',
            'total_tables' => 'nullable|integer|min:0',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);

        $restaurant = Restaurant::create([
            'restaurant_owner_id' => $request->user()->id,
            ...$validated,
            'is_active' => true,
            'verification_status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Restaurant created successfully. Pending verification.',
            'restaurant' => $restaurant->load(['images', 'wilaya']),
        ], 201);
    }

    public function update(Request $request)
    {
        $restaurant = $request->user()->restaurant;

        if (!$restaurant) {
            return response()->json([
                'message' => 'No restaurant found.',
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'name_ar' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'address' => 'sometimes|string|max:255',
            'city' => 'sometimes|string|max:100',
            'wilaya_id' => 'nullable|exists:wilayas,id',
            'cuisine_type' => 'nullable|string|max:100',
            'price_range' => 'nullable|numeric',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'opening_time' => 'nullable|string',
            'closing_time' => 'nullable|string',
            'total_tables' => 'nullable|integer|min:0',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'is_active' => 'sometimes|boolean',
        ]);

        $restaurant->update($validated);

        return response()->json([
            'message' => 'Restaurant updated successfully',
            'restaurant' => $restaurant->fresh()->load(['images', 'plats', 'tables', 'wilaya']),
        ]);
    }

    public function uploadImages(Request $request)
    {
        $restaurant = $request->user()->restaurant;

        if (!$restaurant) {
            return response()->json([
                'message' => 'No restaurant found.',
            ], 404);
        }

        $request->validate([
            'images' => 'required|array|min:1',
            'images.*' => 'required|image|mimes:jpeg,png,jpg,webp|max:5120',
        ]);

        $uploadedImages = [];

        foreach ($request->file('images') as $index => $image) {
            $path = $image->store('restaurants/' . $restaurant->id, 'public');

            $restaurantImage = RestaurantImage::create([
                'restaurant_id' => $restaurant->id,
                'image_path' => $path,
                'is_primary' => $restaurant->images()->count() === 0 && $index === 0,
                'sort_order' => $restaurant->images()->count(),
            ]);

            $uploadedImages[] = $restaurantImage;
        }

        return response()->json([
            'message' => 'Images uploaded successfully',
            'images' => $uploadedImages,
        ]);
    }

    public function deleteImage(Request $request, RestaurantImage $image)
    {
        $restaurant = $request->user()->restaurant;

        if (!$restaurant || $image->restaurant_id !== $restaurant->id) {
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

    public function setImagePrimary(Request $request, RestaurantImage $image)
    {
        $restaurant = $request->user()->restaurant;

        if (!$restaurant || $image->restaurant_id !== $restaurant->id) {
            return response()->json([
                'message' => 'Image not found.',
            ], 404);
        }

        $restaurant->images()->update(['is_primary' => false]);
        $image->update(['is_primary' => true]);

        return response()->json([
            'message' => 'Primary image set successfully',
            'image' => $image,
        ]);
    }
}
