<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use Illuminate\Http\Request;

class HotelController extends Controller
{
    public function index(Request $request)
    {
        $query = Hotel::with(['owner', 'wallet', 'images'])
            ->withCount('rooms');

        // Filter by verification status
        if ($request->has('verification_status')) {
            $query->where('verification_status', $request->verification_status);
        }

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('city', 'like', "%{$search}%")
                  ->orWhere('wilaya', 'like', "%{$search}%");
            });
        }

        $hotels = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json($hotels);
    }

    public function show(Hotel $hotel)
    {
        return response()->json([
            'hotel' => $hotel->load(['owner', 'rooms', 'images', 'wallet']),
        ]);
    }

    public function verify(Request $request, Hotel $hotel)
    {
        if ($hotel->isVerified()) {
            return response()->json([
                'message' => 'Hotel is already verified.',
            ], 422);
        }

        $hotel->verify();

        // Activate the hotel owner if pending
        if ($hotel->owner && $hotel->owner->isPending()) {
            $hotel->owner->update(['status' => 'active']);
        }

        return response()->json([
            'message' => 'Hotel verified successfully',
            'hotel' => $hotel->fresh()->load(['owner', 'wallet']),
        ]);
    }

    public function reject(Request $request, Hotel $hotel)
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $hotel->reject($validated['reason']);

        return response()->json([
            'message' => 'Hotel rejected',
            'hotel' => $hotel->fresh(),
        ]);
    }

    public function suspend(Hotel $hotel)
    {
        $hotel->update([
            'is_active' => false,
        ]);

        return response()->json([
            'message' => 'Hotel suspended',
            'hotel' => $hotel->fresh(),
        ]);
    }

    public function activate(Hotel $hotel)
    {
        if (!$hotel->isVerified()) {
            return response()->json([
                'message' => 'Hotel must be verified first.',
            ], 422);
        }

        $hotel->update([
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'Hotel activated',
            'hotel' => $hotel->fresh(),
        ]);
    }
}
