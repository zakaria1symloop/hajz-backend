<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CarRentalCompany;
use App\Models\Car;
use App\Models\CarBooking;
use Illuminate\Http\Request;

class AdminCarRentalsController extends Controller
{
    public function index(Request $request)
    {
        $query = CarRentalCompany::with(['wilaya', 'owner', 'images']);

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

        $query->withCount('cars');

        $companies = $query->latest()->paginate($request->get('per_page', 15));

        return response()->json($companies);
    }

    public function show(CarRentalCompany $carRental)
    {
        $carRental->load(['wilaya', 'owner', 'images', 'cars.images']);
        $carRental->loadCount(['cars', 'bookings']);

        // Get revenue stats
        $carRental->total_revenue = $carRental->bookings()->sum('total_amount');
        $carRental->total_bookings = $carRental->bookings()->count();

        return response()->json($carRental);
    }

    public function update(Request $request, CarRentalCompany $carRental)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'city' => 'sometimes|string|max:255',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email',
            'wilaya_id' => 'sometimes|exists:wilayas,id',
            'is_active' => 'sometimes|boolean',
            'verification_status' => 'sometimes|in:pending,verified,rejected',
        ]);

        $carRental->update($validated);

        return response()->json([
            'message' => 'Car rental company updated successfully',
            'car_rental' => $carRental->fresh(['wilaya', 'owner']),
        ]);
    }

    public function verify(CarRentalCompany $carRental)
    {
        $carRental->update([
            'verification_status' => 'verified',
        ]);

        // Also verify the owner
        if ($carRental->owner) {
            $carRental->owner->update(['is_active' => true]);
        }

        return response()->json([
            'message' => 'Car rental company verified successfully',
            'car_rental' => $carRental->fresh(),
        ]);
    }

    public function toggleActive(CarRentalCompany $carRental)
    {
        $carRental->update([
            'is_active' => !$carRental->is_active,
        ]);

        return response()->json([
            'message' => $carRental->is_active ? 'Car rental activated' : 'Car rental deactivated',
            'car_rental' => $carRental->fresh(),
        ]);
    }

    public function destroy(CarRentalCompany $carRental)
    {
        // Check for active bookings
        if ($carRental->bookings()->whereIn('status', ['pending', 'confirmed', 'picked_up'])->exists()) {
            return response()->json([
                'message' => 'Cannot delete car rental company with active bookings.',
            ], 422);
        }

        $carRental->delete();

        return response()->json([
            'message' => 'Car rental company deleted successfully',
        ]);
    }

    // Car Management
    public function cars(CarRentalCompany $carRental)
    {
        $cars = $carRental->cars()->with('images')->get();
        return response()->json($cars);
    }

    public function storeCar(Request $request, CarRentalCompany $carRental)
    {
        $validated = $request->validate([
            'brand' => 'required|string|max:100',
            'model' => 'required|string|max:100',
            'year' => 'required|integer|min:1990|max:' . (date('Y') + 1),
            'color' => 'nullable|string|max:50',
            'license_plate' => 'nullable|string|max:20',
            'type' => 'required|string|max:50',
            'transmission' => 'required|in:manual,automatic',
            'fuel_type' => 'required|in:petrol,diesel,electric,hybrid',
            'seats' => 'required|integer|min:1|max:20',
            'doors' => 'nullable|integer|min:2|max:6',
            'has_ac' => 'boolean',
            'has_gps' => 'boolean',
            'has_bluetooth' => 'boolean',
            'has_usb' => 'boolean',
            'has_child_seat' => 'boolean',
            'price_per_day' => 'required|numeric|min:0',
            'price_per_hour' => 'nullable|numeric|min:0',
            'deposit_amount' => 'nullable|numeric|min:0',
            'mileage_limit' => 'nullable|integer',
            'extra_km_price' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'is_available' => 'boolean',
            'is_featured' => 'boolean',
        ]);

        $validated['car_rental_company_id'] = $carRental->id;
        $car = Car::create($validated);

        return response()->json([
            'message' => 'Car created successfully',
            'car' => $car,
        ], 201);
    }

    public function updateCar(Request $request, CarRentalCompany $carRental, Car $car)
    {
        if ($car->car_rental_company_id !== $carRental->id) {
            return response()->json(['message' => 'Car does not belong to this company'], 404);
        }

        $validated = $request->validate([
            'brand' => 'sometimes|string|max:100',
            'model' => 'sometimes|string|max:100',
            'year' => 'sometimes|integer|min:1990|max:' . (date('Y') + 1),
            'color' => 'nullable|string|max:50',
            'license_plate' => 'nullable|string|max:20',
            'type' => 'sometimes|string|max:50',
            'transmission' => 'sometimes|in:manual,automatic',
            'fuel_type' => 'sometimes|in:petrol,diesel,electric,hybrid',
            'seats' => 'sometimes|integer|min:1|max:20',
            'doors' => 'nullable|integer|min:2|max:6',
            'has_ac' => 'boolean',
            'has_gps' => 'boolean',
            'has_bluetooth' => 'boolean',
            'has_usb' => 'boolean',
            'has_child_seat' => 'boolean',
            'price_per_day' => 'sometimes|numeric|min:0',
            'price_per_hour' => 'nullable|numeric|min:0',
            'deposit_amount' => 'nullable|numeric|min:0',
            'mileage_limit' => 'nullable|integer',
            'extra_km_price' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'is_available' => 'boolean',
            'is_featured' => 'boolean',
        ]);

        $car->update($validated);

        return response()->json([
            'message' => 'Car updated successfully',
            'car' => $car->fresh(),
        ]);
    }

    public function destroyCar(CarRentalCompany $carRental, Car $car)
    {
        if ($car->car_rental_company_id !== $carRental->id) {
            return response()->json(['message' => 'Car does not belong to this company'], 404);
        }

        // Check for active bookings
        if ($car->bookings()->whereIn('status', ['pending', 'confirmed', 'picked_up'])->exists()) {
            return response()->json([
                'message' => 'Cannot delete car with active bookings.',
            ], 422);
        }

        $car->delete();

        return response()->json([
            'message' => 'Car deleted successfully',
        ]);
    }

    // Booking Management
    public function bookings(Request $request, CarRentalCompany $carRental)
    {
        $query = CarBooking::with(['user', 'car'])
            ->where('car_rental_company_id', $carRental->id);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $bookings = $query->latest()->paginate($request->get('per_page', 15));

        return response()->json($bookings);
    }

    public function updateBooking(Request $request, CarRentalCompany $carRental, CarBooking $booking)
    {
        if ($booking->car_rental_company_id !== $carRental->id) {
            return response()->json(['message' => 'Booking does not belong to this company'], 404);
        }

        $validated = $request->validate([
            'status' => 'required|in:pending,confirmed,picked_up,returned,completed,cancelled,no_show',
        ]);

        $booking->update($validated);

        return response()->json([
            'message' => 'Booking updated successfully',
            'booking' => $booking->fresh(['user', 'car']),
        ]);
    }
}
