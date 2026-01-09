<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\Plat;
use App\Models\TableReservation;
use Illuminate\Http\Request;

class AdminRestaurantsController extends Controller
{
    public function index(Request $request)
    {
        $query = Restaurant::with(['wilaya', 'owner', 'images']);

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('city', 'like', "%{$search}%")
                  ->orWhere('cuisine_type', 'like', "%{$search}%");
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

        $query->withCount(['tables', 'plats']);

        $restaurants = $query->latest()->paginate($request->get('per_page', 15));

        return response()->json($restaurants);
    }

    public function show(Restaurant $restaurant)
    {
        $restaurant->load(['wilaya', 'owner', 'images', 'tables', 'plats']);
        $restaurant->loadCount(['tables', 'plats', 'tableReservations']);

        // Get revenue stats
        $restaurant->total_revenue = $restaurant->tableReservations()->sum('total_amount');
        $restaurant->total_bookings = $restaurant->tableReservations()->count();

        return response()->json($restaurant);
    }

    public function update(Request $request, Restaurant $restaurant)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'city' => 'sometimes|string|max:255',
            'address' => 'nullable|string',
            'cuisine_type' => 'nullable|string|max:100',
            'price_range' => 'nullable|integer|min:1|max:4',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email',
            'opening_time' => 'nullable|date_format:H:i',
            'closing_time' => 'nullable|date_format:H:i',
            'wilaya_id' => 'sometimes|exists:wilayas,id',
            'is_active' => 'sometimes|boolean',
            'verification_status' => 'sometimes|in:pending,verified,rejected',
        ]);

        $restaurant->update($validated);

        return response()->json([
            'message' => 'Restaurant updated successfully',
            'restaurant' => $restaurant->fresh(['wilaya', 'owner']),
        ]);
    }

    public function verify(Restaurant $restaurant)
    {
        $restaurant->update([
            'verification_status' => 'verified',
        ]);

        // Also verify the owner
        if ($restaurant->owner) {
            $restaurant->owner->update(['is_active' => true]);
        }

        return response()->json([
            'message' => 'Restaurant verified successfully',
            'restaurant' => $restaurant->fresh(),
        ]);
    }

    public function toggleActive(Restaurant $restaurant)
    {
        $restaurant->update([
            'is_active' => !$restaurant->is_active,
        ]);

        return response()->json([
            'message' => $restaurant->is_active ? 'Restaurant activated' : 'Restaurant deactivated',
            'restaurant' => $restaurant->fresh(),
        ]);
    }

    public function destroy(Restaurant $restaurant)
    {
        // Check for active reservations
        if ($restaurant->tableReservations()->whereIn('status', ['pending', 'confirmed'])->exists()) {
            return response()->json([
                'message' => 'Cannot delete restaurant with active reservations.',
            ], 422);
        }

        $restaurant->delete();

        return response()->json([
            'message' => 'Restaurant deleted successfully',
        ]);
    }

    // Table Management
    public function tables(Restaurant $restaurant)
    {
        $tables = $restaurant->tables()->orderBy('sort_order')->get();
        return response()->json($tables);
    }

    public function storeTable(Request $request, Restaurant $restaurant)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'capacity' => 'required|integer|min:1',
            'price_per_hour' => 'required|numeric|min:0',
            'location' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'is_available' => 'boolean',
        ]);

        $validated['restaurant_id'] = $restaurant->id;
        $table = RestaurantTable::create($validated);

        return response()->json([
            'message' => 'Table created successfully',
            'table' => $table,
        ], 201);
    }

    public function updateTable(Request $request, Restaurant $restaurant, RestaurantTable $table)
    {
        if ($table->restaurant_id !== $restaurant->id) {
            return response()->json(['message' => 'Table does not belong to this restaurant'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'capacity' => 'sometimes|integer|min:1',
            'price_per_hour' => 'sometimes|numeric|min:0',
            'location' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'is_available' => 'boolean',
        ]);

        $table->update($validated);

        return response()->json([
            'message' => 'Table updated successfully',
            'table' => $table->fresh(),
        ]);
    }

    public function destroyTable(Restaurant $restaurant, RestaurantTable $table)
    {
        if ($table->restaurant_id !== $restaurant->id) {
            return response()->json(['message' => 'Table does not belong to this restaurant'], 404);
        }

        // Check for active reservations
        if (TableReservation::where('restaurant_table_id', $table->id)
            ->whereIn('status', ['pending', 'confirmed'])->exists()) {
            return response()->json([
                'message' => 'Cannot delete table with active reservations.',
            ], 422);
        }

        $table->delete();

        return response()->json([
            'message' => 'Table deleted successfully',
        ]);
    }

    // Menu/Plats Management
    public function plats(Restaurant $restaurant)
    {
        $plats = $restaurant->plats()->orderBy('category')->orderBy('sort_order')->get();
        return response()->json($plats);
    }

    public function storePlat(Request $request, Restaurant $restaurant)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'name_ar' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'description_ar' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'category' => 'required|string|max:100',
            'is_available' => 'boolean',
            'is_featured' => 'boolean',
            'preparation_time' => 'nullable|integer',
            'is_vegetarian' => 'boolean',
            'is_vegan' => 'boolean',
            'is_halal' => 'boolean',
        ]);

        $validated['restaurant_id'] = $restaurant->id;
        $plat = Plat::create($validated);

        return response()->json([
            'message' => 'Plat created successfully',
            'plat' => $plat,
        ], 201);
    }

    public function updatePlat(Request $request, Restaurant $restaurant, Plat $plat)
    {
        if ($plat->restaurant_id !== $restaurant->id) {
            return response()->json(['message' => 'Plat does not belong to this restaurant'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'name_ar' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'description_ar' => 'nullable|string',
            'price' => 'sometimes|numeric|min:0',
            'category' => 'sometimes|string|max:100',
            'is_available' => 'boolean',
            'is_featured' => 'boolean',
            'preparation_time' => 'nullable|integer',
            'is_vegetarian' => 'boolean',
            'is_vegan' => 'boolean',
            'is_halal' => 'boolean',
        ]);

        $plat->update($validated);

        return response()->json([
            'message' => 'Plat updated successfully',
            'plat' => $plat->fresh(),
        ]);
    }

    public function destroyPlat(Restaurant $restaurant, Plat $plat)
    {
        if ($plat->restaurant_id !== $restaurant->id) {
            return response()->json(['message' => 'Plat does not belong to this restaurant'], 404);
        }

        $plat->delete();

        return response()->json([
            'message' => 'Plat deleted successfully',
        ]);
    }

    // Reservation Management
    public function reservations(Request $request, Restaurant $restaurant)
    {
        $query = TableReservation::with(['user', 'table'])
            ->where('restaurant_id', $restaurant->id);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $reservations = $query->latest()->paginate($request->get('per_page', 15));

        return response()->json($reservations);
    }

    public function updateReservation(Request $request, Restaurant $restaurant, TableReservation $reservation)
    {
        if ($reservation->restaurant_id !== $restaurant->id) {
            return response()->json(['message' => 'Reservation does not belong to this restaurant'], 404);
        }

        $validated = $request->validate([
            'status' => 'required|in:pending,confirmed,seated,completed,cancelled,no_show',
        ]);

        $reservation->update($validated);

        return response()->json([
            'message' => 'Reservation updated successfully',
            'reservation' => $reservation->fresh(['user', 'table']),
        ]);
    }
}
