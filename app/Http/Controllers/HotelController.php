<?php

namespace App\Http\Controllers;

use App\Models\Hotel;
use Illuminate\Http\Request;

class HotelController extends Controller
{
    public function index(Request $request)
    {
        // Only show verified and active hotels to users
        $query = Hotel::with(['images', 'wilaya'])
            ->where('verification_status', 'verified')
            ->where('is_active', true);

        // Filter by wilaya
        if ($request->has('wilaya_id') || $request->has('wilaya')) {
            $wilayaId = $request->wilaya_id ?? $request->wilaya;
            $query->where('wilaya_id', $wilayaId);
        }

        // Filter by city
        if ($request->has('city')) {
            $query->where('city', 'like', '%' . $request->city . '%');
        }

        // Filter by search query
        if ($request->has('q')) {
            $search = $request->q;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filter by star rating
        if ($request->has('star_rating')) {
            $query->where('star_rating', '>=', $request->star_rating);
        }

        // Filter by price range (from rooms)
        if ($request->has('min_price')) {
            $query->whereHas('rooms', function ($q) use ($request) {
                $q->where('price_per_night', '>=', $request->min_price);
            });
        }

        if ($request->has('max_price')) {
            $query->whereHas('rooms', function ($q) use ($request) {
                $q->where('price_per_night', '<=', $request->max_price);
            });
        }

        // Filter by amenities
        if ($request->has('amenities')) {
            $amenities = is_array($request->amenities) ? $request->amenities : explode(',', $request->amenities);
            foreach ($amenities as $amenity) {
                $query->whereJsonContains('amenities', $amenity);
            }
        }

        // Filter by verification status
        if ($request->has('verified')) {
            $query->where('verification_status', 'verified');
        }

        // Sorting
        $sortBy = $request->get('sort', 'rating');
        $sortOrder = $request->get('order', 'desc');

        switch ($sortBy) {
            case 'price':
                $query->orderByRaw('(SELECT MIN(price_per_night) FROM rooms WHERE rooms.hotel_id = hotels.id) ' . $sortOrder);
                break;
            case 'name':
                $query->orderBy('name', $sortOrder);
                break;
            case 'star_rating':
                $query->orderBy('star_rating', $sortOrder);
                break;
            default:
                $query->orderBy('star_rating', 'desc');
        }

        $hotels = $query->paginate($request->get('per_page', 12));

        // Add min_price to each hotel
        $hotels->getCollection()->transform(function ($hotel) {
            $hotel->min_price = $hotel->rooms()->min('price_per_night');
            $hotel->rooms_count = $hotel->rooms()->count();
            return $hotel;
        });

        return response()->json($hotels);
    }

    public function show(Hotel $hotel)
    {
        return response()->json($hotel);
    }

    public function featured()
    {
        $hotels = Hotel::with(['images', 'wilaya'])
            ->where('verification_status', 'verified')
            ->where('is_active', true)
            ->orderBy('star_rating', 'desc')
            ->limit(6)
            ->get();

        // Add extra info
        $hotels->transform(function ($hotel) {
            $hotel->rooms_count = $hotel->rooms()->count();
            $hotel->min_price = $hotel->rooms()->min('price_per_night');
            return $hotel;
        });

        return response()->json($hotels);
    }

    public function search(Request $request)
    {
        // Only show verified and active hotels
        $query = Hotel::query()
            ->where('verification_status', 'verified')
            ->where('is_active', true);

        if ($request->has('q')) {
            $search = $request->q;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")
                    ->orWhere('country', 'like', "%{$search}%");
            });
        }

        if ($request->has('check_in') && $request->has('check_out')) {
            $query->where('rooms_available', '>', 0);
        }

        return response()->json($query->paginate(12));
    }
}
