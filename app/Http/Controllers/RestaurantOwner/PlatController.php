<?php

namespace App\Http\Controllers\RestaurantOwner;

use App\Http\Controllers\Controller;
use App\Models\Plat;
use App\Models\PlatImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PlatController extends Controller
{
    public function index(Request $request)
    {
        $restaurant = $request->user()->restaurant;

        if (!$restaurant) {
            return response()->json([
                'message' => 'No restaurant found.',
            ], 404);
        }

        $plats = $restaurant->plats()
            ->with('images')
            ->orderBy('category')
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'plats' => $plats,
        ]);
    }

    public function store(Request $request)
    {
        $restaurant = $request->user()->restaurant;

        if (!$restaurant) {
            return response()->json([
                'message' => 'No restaurant found.',
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'name_ar' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'description_ar' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'category' => 'nullable|string|max:100',
            'is_available' => 'boolean',
            'is_featured' => 'boolean',
            'preparation_time' => 'nullable|integer|min:1',
            'ingredients' => 'nullable|array',
            'allergens' => 'nullable|array',
            'is_vegetarian' => 'boolean',
            'is_vegan' => 'boolean',
            'is_halal' => 'boolean',
        ]);

        $plat = Plat::create([
            'restaurant_id' => $restaurant->id,
            ...$validated,
            'sort_order' => $restaurant->plats()->count(),
        ]);

        return response()->json([
            'message' => 'Plat created successfully',
            'plat' => $plat->load('images'),
        ], 201);
    }

    public function show(Request $request, Plat $plat)
    {
        $restaurant = $request->user()->restaurant;

        if (!$restaurant || $plat->restaurant_id !== $restaurant->id) {
            return response()->json([
                'message' => 'Plat not found.',
            ], 404);
        }

        return response()->json([
            'plat' => $plat->load('images'),
        ]);
    }

    public function update(Request $request, Plat $plat)
    {
        $restaurant = $request->user()->restaurant;

        if (!$restaurant || $plat->restaurant_id !== $restaurant->id) {
            return response()->json([
                'message' => 'Plat not found.',
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'name_ar' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'description_ar' => 'nullable|string',
            'price' => 'sometimes|numeric|min:0',
            'category' => 'nullable|string|max:100',
            'is_available' => 'boolean',
            'is_featured' => 'boolean',
            'preparation_time' => 'nullable|integer|min:1',
            'ingredients' => 'nullable|array',
            'allergens' => 'nullable|array',
            'is_vegetarian' => 'boolean',
            'is_vegan' => 'boolean',
            'is_halal' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $plat->update($validated);

        return response()->json([
            'message' => 'Plat updated successfully',
            'plat' => $plat->fresh()->load('images'),
        ]);
    }

    public function destroy(Request $request, Plat $plat)
    {
        $restaurant = $request->user()->restaurant;

        if (!$restaurant || $plat->restaurant_id !== $restaurant->id) {
            return response()->json([
                'message' => 'Plat not found.',
            ], 404);
        }

        // Delete images
        foreach ($plat->images as $image) {
            Storage::disk('public')->delete($image->image_path);
        }

        $plat->delete();

        return response()->json([
            'message' => 'Plat deleted successfully',
        ]);
    }

    public function uploadImages(Request $request, Plat $plat)
    {
        $restaurant = $request->user()->restaurant;

        if (!$restaurant || $plat->restaurant_id !== $restaurant->id) {
            return response()->json([
                'message' => 'Plat not found.',
            ], 404);
        }

        $request->validate([
            'images' => 'required|array|min:1',
            'images.*' => 'required|image|mimes:jpeg,png,jpg,webp|max:5120',
        ]);

        $uploadedImages = [];

        foreach ($request->file('images') as $index => $image) {
            $path = $image->store('plats/' . $plat->id, 'public');

            $platImage = PlatImage::create([
                'plat_id' => $plat->id,
                'image_path' => $path,
                'is_primary' => $plat->images()->count() === 0 && $index === 0,
                'sort_order' => $plat->images()->count(),
            ]);

            $uploadedImages[] = $platImage;
        }

        return response()->json([
            'message' => 'Images uploaded successfully',
            'images' => $uploadedImages,
        ]);
    }

    public function deleteImage(Request $request, PlatImage $image)
    {
        $restaurant = $request->user()->restaurant;
        $plat = $image->plat;

        if (!$restaurant || !$plat || $plat->restaurant_id !== $restaurant->id) {
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
}
