<?php

namespace App\Http\Controllers\RestaurantOwner;

use App\Http\Controllers\Controller;
use App\Models\RestaurantTable;
use Illuminate\Http\Request;

class TableController extends Controller
{
    public function index(Request $request)
    {
        $restaurant = $request->user()->restaurant;

        if (!$restaurant) {
            return response()->json([
                'message' => 'No restaurant found.',
            ], 404);
        }

        $tables = $restaurant->tables()
            ->withCount(['reservations' => function ($query) {
                $query->whereIn('status', ['pending', 'confirmed'])
                    ->where('reservation_date', '>=', now()->toDateString());
            }])
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'tables' => $tables,
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
            'capacity' => 'required|integer|min:1',
            'price_per_hour' => 'required|numeric|min:0',
            'location' => 'nullable|string|max:100',
            'is_available' => 'boolean',
            'description' => 'nullable|string',
        ]);

        $table = RestaurantTable::create([
            'restaurant_id' => $restaurant->id,
            ...$validated,
            'sort_order' => $restaurant->tables()->count(),
        ]);

        // Update restaurant total_tables count
        $restaurant->update(['total_tables' => $restaurant->tables()->count()]);

        return response()->json([
            'message' => 'Table created successfully',
            'table' => $table,
        ], 201);
    }

    public function show(Request $request, RestaurantTable $table)
    {
        $restaurant = $request->user()->restaurant;

        if (!$restaurant || $table->restaurant_id !== $restaurant->id) {
            return response()->json([
                'message' => 'Table not found.',
            ], 404);
        }

        return response()->json([
            'table' => $table->loadCount(['reservations' => function ($query) {
                $query->whereIn('status', ['pending', 'confirmed'])
                    ->where('reservation_date', '>=', now()->toDateString());
            }]),
        ]);
    }

    public function update(Request $request, RestaurantTable $table)
    {
        $restaurant = $request->user()->restaurant;

        if (!$restaurant || $table->restaurant_id !== $restaurant->id) {
            return response()->json([
                'message' => 'Table not found.',
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'capacity' => 'sometimes|integer|min:1',
            'price_per_hour' => 'sometimes|numeric|min:0',
            'location' => 'nullable|string|max:100',
            'is_available' => 'boolean',
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $table->update($validated);

        return response()->json([
            'message' => 'Table updated successfully',
            'table' => $table->fresh(),
        ]);
    }

    public function destroy(Request $request, RestaurantTable $table)
    {
        $restaurant = $request->user()->restaurant;

        if (!$restaurant || $table->restaurant_id !== $restaurant->id) {
            return response()->json([
                'message' => 'Table not found.',
            ], 404);
        }

        // Check if table has future reservations
        $hasReservations = $table->reservations()
            ->whereIn('status', ['pending', 'confirmed'])
            ->where('reservation_date', '>=', now()->toDateString())
            ->exists();

        if ($hasReservations) {
            return response()->json([
                'message' => 'Cannot delete table with active reservations.',
            ], 422);
        }

        $table->delete();

        // Update restaurant total_tables count
        $restaurant->update(['total_tables' => $restaurant->tables()->count()]);

        return response()->json([
            'message' => 'Table deleted successfully',
        ]);
    }

    public function getAvailability(Request $request, RestaurantTable $table)
    {
        $restaurant = $request->user()->restaurant;

        if (!$restaurant || $table->restaurant_id !== $restaurant->id) {
            return response()->json([
                'message' => 'Table not found.',
            ], 404);
        }

        $request->validate([
            'date' => 'required|date|after_or_equal:today',
        ]);

        $date = $request->date;

        $reservations = $table->reservations()
            ->where('reservation_date', $date)
            ->whereIn('status', ['pending', 'confirmed'])
            ->orderBy('reservation_time')
            ->get(['id', 'reservation_time', 'duration_minutes', 'guests_count', 'status', 'guest_name']);

        return response()->json([
            'date' => $date,
            'table' => $table,
            'reservations' => $reservations,
        ]);
    }
}
