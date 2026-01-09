<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Wilaya;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class WilayaController extends Controller
{
    public function index(Request $request)
    {
        $query = Wilaya::query();

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('name_ar', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('is_featured')) {
            $query->where('is_featured', $request->boolean('is_featured'));
        }

        $wilayas = $query->orderBy('sort_order')->orderBy('code')->paginate(20);

        return response()->json($wilayas);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'name_ar' => 'nullable|string|max:255',
            'code' => 'required|string|max:10|unique:wilayas,code',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
        ]);

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('wilayas', 'public');
        }

        $wilaya = Wilaya::create($validated);

        return response()->json([
            'message' => 'Wilaya created successfully',
            'wilaya' => $wilaya,
        ], 201);
    }

    public function show(Wilaya $wilaya)
    {
        return response()->json([
            'wilaya' => $wilaya,
        ]);
    }

    public function update(Request $request, Wilaya $wilaya)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'name_ar' => 'nullable|string|max:255',
            'code' => 'sometimes|string|max:10|unique:wilayas,code,' . $wilaya->id,
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
        ]);

        if ($request->hasFile('image')) {
            // Delete old image
            if ($wilaya->image) {
                Storage::disk('public')->delete($wilaya->image);
            }
            $validated['image'] = $request->file('image')->store('wilayas', 'public');
        }

        $wilaya->update($validated);

        return response()->json([
            'message' => 'Wilaya updated successfully',
            'wilaya' => $wilaya->fresh(),
        ]);
    }

    public function destroy(Wilaya $wilaya)
    {
        // Check if wilaya has hotels or restaurants
        if ($wilaya->hotels()->count() > 0 || $wilaya->restaurants()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete wilaya with associated hotels or restaurants.',
            ], 422);
        }

        // Delete image
        if ($wilaya->image) {
            Storage::disk('public')->delete($wilaya->image);
        }

        $wilaya->delete();

        return response()->json([
            'message' => 'Wilaya deleted successfully',
        ]);
    }

    public function uploadImage(Request $request, Wilaya $wilaya)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,webp|max:5120',
        ]);

        // Delete old image
        if ($wilaya->image) {
            Storage::disk('public')->delete($wilaya->image);
        }

        $path = $request->file('image')->store('wilayas', 'public');
        $wilaya->update(['image' => $path]);

        return response()->json([
            'message' => 'Image uploaded successfully',
            'wilaya' => $wilaya->fresh(),
        ]);
    }

    public function deleteImage(Wilaya $wilaya)
    {
        if ($wilaya->image) {
            Storage::disk('public')->delete($wilaya->image);
            $wilaya->update(['image' => null]);
        }

        return response()->json([
            'message' => 'Image deleted successfully',
            'wilaya' => $wilaya->fresh(),
        ]);
    }

    // Public endpoints
    public function publicIndex()
    {
        $wilayas = Wilaya::active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json($wilayas);
    }

    public function featured()
    {
        $wilayas = Wilaya::active()
            ->featured()
            ->orderBy('sort_order')
            ->limit(8)
            ->get();

        return response()->json($wilayas);
    }

    public function popular()
    {
        $wilayas = Wilaya::active()
            ->withCount(['hotels', 'restaurants', 'carRentalCompanies'])
            ->get()
            ->map(function ($wilaya) {
                $wilaya->total_count = $wilaya->hotels_count + $wilaya->restaurants_count + $wilaya->car_rental_companies_count;
                return $wilaya;
            })
            ->sortByDesc('total_count')
            ->take(4)
            ->values();

        return response()->json($wilayas);
    }
}
