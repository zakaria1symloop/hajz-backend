<?php

namespace App\Http\Controllers\CompanyOwner;

use App\Http\Controllers\Controller;
use App\Models\CarRentalCompany;
use App\Models\CompanyImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CompanyController extends Controller
{
    public function show(Request $request)
    {
        $company = $request->user()->company;

        if (!$company) {
            return response()->json([
                'message' => 'No company found. Please create one first.',
            ], 404);
        }

        return response()->json([
            'company' => $company->load(['wilaya', 'cars', 'images']),
            'cars_count' => $company->cars()->count(),
            'available_cars' => $company->cars()->where('is_available', true)->count(),
        ]);
    }

    public function store(Request $request)
    {
        $owner = $request->user();

        if ($owner->company) {
            return response()->json([
                'message' => 'You already have a company.',
            ], 422);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'name_ar' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'address' => 'required|string|max:255',
            'city' => 'required|string|max:100',
            'wilaya_id' => 'required|exists:wilayas,id',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|url|max:255',
            'opening_time' => 'nullable|date_format:H:i',
            'closing_time' => 'nullable|date_format:H:i',
            'services' => 'nullable|array',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        $company = CarRentalCompany::create([
            'company_owner_id' => $owner->id,
            ...$validated,
            'is_active' => true,
            'verification_status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Company created successfully',
            'company' => $company->load('wilaya'),
        ], 201);
    }

    public function update(Request $request)
    {
        $company = $request->user()->company;

        if (!$company) {
            return response()->json([
                'message' => 'No company found.',
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'name_ar' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'address' => 'sometimes|string|max:255',
            'city' => 'sometimes|string|max:100',
            'wilaya_id' => 'sometimes|exists:wilayas,id',
            'phone' => 'sometimes|string|max:20',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|url|max:255',
            'opening_time' => 'nullable|date_format:H:i',
            'closing_time' => 'nullable|date_format:H:i',
            'services' => 'nullable|array',
            'is_active' => 'boolean',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        $company->update($validated);

        return response()->json([
            'message' => 'Company updated successfully',
            'company' => $company->fresh()->load('wilaya'),
        ]);
    }

    public function uploadImages(Request $request)
    {
        $company = $request->user()->company;

        if (!$company) {
            return response()->json([
                'message' => 'No company found.',
            ], 404);
        }

        $request->validate([
            'images' => 'required|array|max:10',
            'images.*' => 'image|mimes:jpeg,png,jpg,webp|max:5120',
        ]);

        $uploadedImages = [];
        $isFirstImage = $company->images()->count() === 0;

        foreach ($request->file('images') as $index => $image) {
            $path = $image->store('companies/' . $company->id, 'public');

            $companyImage = CompanyImage::create([
                'car_rental_company_id' => $company->id,
                'image_path' => $path,
                'is_primary' => $isFirstImage && $index === 0,
                'sort_order' => $company->images()->count(),
            ]);

            $uploadedImages[] = $companyImage;
        }

        return response()->json([
            'message' => 'Images uploaded successfully',
            'images' => $uploadedImages,
        ]);
    }

    public function deleteImage(Request $request, $imageId)
    {
        $company = $request->user()->company;

        if (!$company) {
            return response()->json([
                'message' => 'No company found.',
            ], 404);
        }

        $image = $company->images()->find($imageId);

        if (!$image) {
            return response()->json([
                'message' => 'Image not found.',
            ], 404);
        }

        Storage::disk('public')->delete($image->image_path);
        $wasPrimary = $image->is_primary;
        $image->delete();

        // If deleted image was primary, set another as primary
        if ($wasPrimary) {
            $newPrimary = $company->images()->first();
            if ($newPrimary) {
                $newPrimary->update(['is_primary' => true]);
            }
        }

        return response()->json([
            'message' => 'Image deleted successfully',
        ]);
    }

    public function setPrimaryImage(Request $request, $imageId)
    {
        $company = $request->user()->company;

        if (!$company) {
            return response()->json([
                'message' => 'No company found.',
            ], 404);
        }

        $image = $company->images()->find($imageId);

        if (!$image) {
            return response()->json([
                'message' => 'Image not found.',
            ], 404);
        }

        // Remove primary from all images
        $company->images()->update(['is_primary' => false]);

        // Set this image as primary
        $image->update(['is_primary' => true]);

        return response()->json([
            'message' => 'Primary image updated successfully',
        ]);
    }

    public function getStats(Request $request)
    {
        $company = $request->user()->company;

        if (!$company) {
            return response()->json([
                'message' => 'No company found.',
            ], 404);
        }

        $today = now()->toDateString();
        $thisMonth = now()->startOfMonth()->toDateString();

        return response()->json([
            'total_cars' => $company->cars()->count(),
            'available_cars' => $company->cars()->where('is_available', true)->count(),
            'today_pickups' => $company->bookings()->where('pickup_date', $today)->where('status', 'confirmed')->count(),
            'today_returns' => $company->bookings()->where('return_date', $today)->where('status', 'picked_up')->count(),
            'active_rentals' => $company->bookings()->where('status', 'picked_up')->count(),
            'pending_bookings' => $company->bookings()->where('status', 'pending')->count(),
            'monthly_bookings' => $company->bookings()->where('pickup_date', '>=', $thisMonth)->count(),
            'monthly_revenue' => $company->bookings()
                ->where('pickup_date', '>=', $thisMonth)
                ->whereIn('status', ['picked_up', 'returned', 'completed'])
                ->sum('total_amount'),
        ]);
    }
}
