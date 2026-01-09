<?php

namespace App\Http\Controllers\CompanyOwner;

use App\Http\Controllers\Controller;
use App\Models\Car;
use App\Models\CarImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CarController extends Controller
{
    public function index(Request $request)
    {
        $company = $request->user()->company;

        if (!$company) {
            return response()->json([
                'message' => 'No company found.',
            ], 404);
        }

        $query = $company->cars()->with('images');

        // Filter by availability
        if ($request->has('available')) {
            $query->where('is_available', $request->boolean('available'));
        }

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        $cars = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'cars' => $cars,
        ]);
    }

    public function store(Request $request)
    {
        $company = $request->user()->company;

        if (!$company) {
            return response()->json([
                'message' => 'No company found.',
            ], 404);
        }

        $validated = $request->validate([
            'brand' => 'required|string|max:100',
            'model' => 'required|string|max:100',
            'year' => 'required|integer|min:1990|max:' . (date('Y') + 1),
            'color' => 'nullable|string|max:50',
            'license_plate' => 'nullable|string|max:20',
            'type' => 'required|in:sedan,suv,hatchback,coupe,van,truck,convertible,wagon,economy,compact,midsize,fullsize,luxury,sports',
            'transmission' => 'required|in:automatic,manual',
            'fuel_type' => 'required|in:petrol,gasoline,diesel,electric,hybrid',
            'seats' => 'required|integer|min:1|max:15',
            'doors' => 'required|integer|min:2|max:5',
            'has_ac' => 'boolean',
            'has_gps' => 'boolean',
            'has_bluetooth' => 'boolean',
            'has_usb' => 'boolean',
            'has_child_seat' => 'boolean',
            'price_per_day' => 'required|numeric|min:0',
            'price_per_hour' => 'nullable|numeric|min:0',
            'min_rental_days' => 'nullable|integer|min:1',
            'max_rental_days' => 'nullable|integer|min:1',
            'deposit_amount' => 'nullable|numeric|min:0',
            'mileage_limit' => 'nullable|integer|min:0',
            'extra_km_price' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'features' => 'nullable|array',
            'is_available' => 'boolean',
        ]);

        $car = Car::create([
            'car_rental_company_id' => $company->id,
            'is_available' => true,
            ...$validated,
        ]);

        return response()->json([
            'message' => 'Car added successfully',
            'car' => $car,
        ], 201);
    }

    public function show(Request $request, Car $car)
    {
        $company = $request->user()->company;

        if (!$company || $car->car_rental_company_id !== $company->id) {
            return response()->json([
                'message' => 'Car not found.',
            ], 404);
        }

        return response()->json([
            'car' => $car->load('images'),
            'bookings_count' => $car->bookings()->whereIn('status', ['pending', 'confirmed', 'picked_up'])->count(),
        ]);
    }

    public function update(Request $request, Car $car)
    {
        $company = $request->user()->company;

        if (!$company || $car->car_rental_company_id !== $company->id) {
            return response()->json([
                'message' => 'Car not found.',
            ], 404);
        }

        $validated = $request->validate([
            'brand' => 'sometimes|string|max:100',
            'model' => 'sometimes|string|max:100',
            'year' => 'sometimes|integer|min:1990|max:' . (date('Y') + 1),
            'color' => 'sometimes|string|max:50',
            'license_plate' => 'sometimes|string|max:20',
            'type' => 'sometimes|in:sedan,suv,hatchback,coupe,van,truck,convertible,wagon,economy,compact,midsize,fullsize,luxury,sports',
            'transmission' => 'sometimes|in:automatic,manual',
            'fuel_type' => 'sometimes|in:petrol,gasoline,diesel,electric,hybrid',
            'seats' => 'sometimes|integer|min:1|max:15',
            'doors' => 'sometimes|integer|min:2|max:5',
            'has_ac' => 'boolean',
            'has_gps' => 'boolean',
            'has_bluetooth' => 'boolean',
            'has_usb' => 'boolean',
            'has_child_seat' => 'boolean',
            'price_per_day' => 'sometimes|numeric|min:0',
            'price_per_hour' => 'nullable|numeric|min:0',
            'min_rental_days' => 'nullable|integer|min:1',
            'max_rental_days' => 'nullable|integer|min:1',
            'deposit_amount' => 'nullable|numeric|min:0',
            'mileage_limit' => 'nullable|integer|min:0',
            'extra_km_price' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'features' => 'nullable|array',
            'is_available' => 'boolean',
        ]);

        $car->update($validated);

        return response()->json([
            'message' => 'Car updated successfully',
            'car' => $car->fresh()->load('images'),
        ]);
    }

    public function destroy(Request $request, Car $car)
    {
        $company = $request->user()->company;

        if (!$company || $car->car_rental_company_id !== $company->id) {
            return response()->json([
                'message' => 'Car not found.',
            ], 404);
        }

        // Check if car has active bookings
        $hasActiveBookings = $car->bookings()
            ->whereIn('status', ['pending', 'confirmed', 'picked_up'])
            ->exists();

        if ($hasActiveBookings) {
            return response()->json([
                'message' => 'Cannot delete car with active bookings.',
            ], 422);
        }

        // Delete car images
        foreach ($car->images as $image) {
            Storage::disk('public')->delete($image->image_path);
        }

        $car->delete();

        return response()->json([
            'message' => 'Car deleted successfully',
        ]);
    }

    public function uploadImages(Request $request, Car $car)
    {
        $company = $request->user()->company;

        if (!$company || $car->car_rental_company_id !== $company->id) {
            return response()->json([
                'message' => 'Car not found.',
            ], 404);
        }

        $request->validate([
            'images' => 'required|array|max:10',
            'images.*' => 'image|mimes:jpeg,png,jpg,webp|max:5120',
        ]);

        $uploadedImages = [];
        $isFirstImage = $car->images()->count() === 0;

        foreach ($request->file('images') as $index => $image) {
            $path = $image->store('cars/' . $car->id, 'public');

            $carImage = CarImage::create([
                'car_id' => $car->id,
                'image_path' => $path,
                'is_primary' => $isFirstImage && $index === 0,
                'sort_order' => $car->images()->count(),
            ]);

            $uploadedImages[] = $carImage;
        }

        return response()->json([
            'message' => 'Images uploaded successfully',
            'images' => $uploadedImages,
        ]);
    }

    public function deleteImage(Request $request, Car $car, $imageId)
    {
        $company = $request->user()->company;

        if (!$company || $car->car_rental_company_id !== $company->id) {
            return response()->json([
                'message' => 'Car not found.',
            ], 404);
        }

        $image = $car->images()->find($imageId);

        if (!$image) {
            return response()->json([
                'message' => 'Image not found.',
            ], 404);
        }

        Storage::disk('public')->delete($image->image_path);
        $wasPrimary = $image->is_primary;
        $image->delete();

        if ($wasPrimary) {
            $newPrimary = $car->images()->first();
            if ($newPrimary) {
                $newPrimary->update(['is_primary' => true]);
            }
        }

        return response()->json([
            'message' => 'Image deleted successfully',
        ]);
    }

    public function setPrimaryImage(Request $request, Car $car, $imageId)
    {
        $company = $request->user()->company;

        if (!$company || $car->car_rental_company_id !== $company->id) {
            return response()->json([
                'message' => 'Car not found.',
            ], 404);
        }

        $image = $car->images()->find($imageId);

        if (!$image) {
            return response()->json([
                'message' => 'Image not found.',
            ], 404);
        }

        $car->images()->update(['is_primary' => false]);
        $image->update(['is_primary' => true]);

        return response()->json([
            'message' => 'Primary image updated successfully',
        ]);
    }

    public function toggleAvailability(Request $request, Car $car)
    {
        $company = $request->user()->company;

        if (!$company || $car->car_rental_company_id !== $company->id) {
            return response()->json([
                'message' => 'Car not found.',
            ], 404);
        }

        $car->update(['is_available' => !$car->is_available]);

        return response()->json([
            'message' => 'Car availability updated',
            'is_available' => $car->is_available,
        ]);
    }

    public function getAvailability(Request $request, Car $car)
    {
        $company = $request->user()->company;

        if (!$company || $car->car_rental_company_id !== $company->id) {
            return response()->json([
                'message' => 'Car not found.',
            ], 404);
        }

        $request->validate([
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after:start_date',
        ]);

        $bookings = $car->bookings()
            ->whereIn('status', ['pending', 'confirmed', 'picked_up'])
            ->where(function ($query) use ($request) {
                $query->whereBetween('pickup_date', [$request->start_date, $request->end_date])
                    ->orWhereBetween('return_date', [$request->start_date, $request->end_date])
                    ->orWhere(function ($q) use ($request) {
                        $q->where('pickup_date', '<=', $request->start_date)
                            ->where('return_date', '>=', $request->end_date);
                    });
            })
            ->get(['id', 'pickup_date', 'return_date', 'status', 'customer_name']);

        return response()->json([
            'car' => $car,
            'bookings' => $bookings,
            'is_available' => $bookings->isEmpty(),
        ]);
    }
}
