<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HotelOwner;
use App\Models\RestaurantOwner;
use App\Models\CompanyOwner;
use Illuminate\Http\Request;

class AdminProfessionalsController extends Controller
{
    public function index(Request $request)
    {
        // Get all professionals from different owner tables
        // hotel_owners has 'status' column, others have 'is_active'
        $hotelOwners = HotelOwner::with('hotel')->get()->map(function ($owner) {
            return [
                'id' => 'hotel_' . $owner->id,
                'original_id' => $owner->id,
                'type' => 'hotel',
                'name' => $owner->name,
                'email' => $owner->email,
                'phone' => $owner->phone,
                'business_name' => $owner->hotel?->name ?? 'N/A',
                'business_type' => 'hotel',
                'verification_status' => $owner->status ?? 'pending',
                'is_active' => $owner->status === 'active',
                'created_at' => $owner->created_at,
            ];
        });

        $restaurantOwners = RestaurantOwner::with('restaurant')->get()->map(function ($owner) {
            return [
                'id' => 'restaurant_' . $owner->id,
                'original_id' => $owner->id,
                'type' => 'restaurant',
                'name' => $owner->name,
                'email' => $owner->email,
                'phone' => $owner->phone,
                'business_name' => $owner->restaurant?->name ?? 'N/A',
                'business_type' => 'restaurant',
                'verification_status' => $owner->is_active ? 'verified' : 'pending',
                'is_active' => $owner->is_active ?? false,
                'created_at' => $owner->created_at,
            ];
        });

        $companyOwners = CompanyOwner::with('company')->get()->map(function ($owner) {
            return [
                'id' => 'company_' . $owner->id,
                'original_id' => $owner->id,
                'type' => 'car_rental',
                'name' => $owner->name,
                'email' => $owner->email,
                'phone' => $owner->phone,
                'business_name' => $owner->company?->name ?? 'N/A',
                'business_type' => 'car_rental',
                'verification_status' => $owner->is_active ? 'verified' : 'pending',
                'is_active' => $owner->is_active ?? false,
                'created_at' => $owner->created_at,
            ];
        });

        // Merge all professionals
        $professionals = collect()
            ->merge($hotelOwners)
            ->merge($restaurantOwners)
            ->merge($companyOwners);

        // Apply search filter
        if ($request->has('search') && $request->search) {
            $search = strtolower($request->search);
            $professionals = $professionals->filter(function ($p) use ($search) {
                return str_contains(strtolower($p['name']), $search) ||
                       str_contains(strtolower($p['email']), $search) ||
                       str_contains(strtolower($p['business_name']), $search);
            });
        }

        // Apply status filter
        if ($request->has('verification_status') && $request->verification_status) {
            $professionals = $professionals->where('verification_status', $request->verification_status);
        }

        // Apply type filter
        if ($request->has('business_type') && $request->business_type) {
            $professionals = $professionals->where('business_type', $request->business_type);
        }

        // Sort by created_at desc
        $professionals = $professionals->sortByDesc('created_at')->values();

        // Paginate manually
        $page = $request->get('page', 1);
        $perPage = $request->get('per_page', 15);
        $total = $professionals->count();
        $data = $professionals->forPage($page, $perPage)->values();

        return response()->json([
            'data' => $data,
            'current_page' => (int) $page,
            'per_page' => (int) $perPage,
            'total' => $total,
            'last_page' => ceil($total / $perPage),
        ]);
    }

    public function show(string $type, $id)
    {
        try {
            $owner = $this->getOwner($type, $id);
            if (!$owner) {
                return response()->json(['message' => 'Professional not found', 'type' => $type, 'id' => $id], 404);
            }

            $business = $this->getBusiness($owner, $type);

            return response()->json([
                'id' => $type . '_' . $owner->id,
                'original_id' => $owner->id,
                'type' => $type,
                'name' => $owner->name,
                'email' => $owner->email,
                'phone' => $owner->phone,
                'business_name' => $business?->name ?? 'N/A',
                'business_type' => $type === 'car_rental' ? 'car_rental' : $type,
                'verification_status' => $type === 'hotel' ? ($owner->status ?? 'pending') : ($owner->is_active ? 'verified' : 'pending'),
                'is_active' => $type === 'hotel' ? ($owner->status === 'active') : ($owner->is_active ?? false),
                'created_at' => $owner->created_at,
                'business' => $business ? [
                    'id' => $business->id,
                    'name' => $business->name,
                    'address' => $business->address ?? null,
                    'city' => $business->city ?? null,
                    'wilaya' => $business->wilaya ?? null,
                    'description' => $business->description ?? null,
                    'is_active' => $business->is_active ?? false,
                    'verification_status' => $business->verification_status ?? 'pending',
                ] : null,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error fetching professional',
                'error' => $e->getMessage(),
                'type' => $type,
                'id' => $id,
            ], 500);
        }
    }

    public function verify(Request $request, string $type, $id)
    {
        $owner = $this->getOwner($type, $id);
        if (!$owner) {
            return response()->json(['message' => 'Professional not found'], 404);
        }

        // Use correct column based on type
        if ($type === 'hotel') {
            $owner->update(['status' => 'active']);
        } else {
            $owner->update(['is_active' => true]);
        }

        // Also verify associated business
        $business = $this->getBusiness($owner, $type);
        if ($business) {
            $business->update(['verification_status' => 'verified']);
        }

        return response()->json([
            'message' => 'Professional verified successfully',
        ]);
    }

    public function reject(Request $request, string $type, $id)
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $owner = $this->getOwner($type, $id);
        if (!$owner) {
            return response()->json(['message' => 'Professional not found'], 404);
        }

        // Use correct column based on type
        if ($type === 'hotel') {
            $owner->update(['status' => 'rejected']);
        } else {
            $owner->update(['is_active' => false]);
        }

        return response()->json([
            'message' => 'Professional rejected',
        ]);
    }

    public function toggleActive(Request $request, string $type, $id)
    {
        $owner = $this->getOwner($type, $id);
        if (!$owner) {
            return response()->json(['message' => 'Professional not found'], 404);
        }

        // Use correct column based on type
        if ($type === 'hotel') {
            $newStatus = $owner->status === 'active' ? 'inactive' : 'active';
            $owner->update(['status' => $newStatus]);
            $isActive = $newStatus === 'active';
        } else {
            $owner->update(['is_active' => !$owner->is_active]);
            $isActive = $owner->is_active;
        }

        return response()->json([
            'message' => $isActive ? 'Professional activated' : 'Professional deactivated',
        ]);
    }

    private function getOwner(string $type, $id)
    {
        $id = (int) $id;
        return match ($type) {
            'hotel' => HotelOwner::find($id),
            'restaurant' => RestaurantOwner::find($id),
            'car_rental', 'company' => CompanyOwner::find($id),
            default => null,
        };
    }

    private function getBusiness($owner, string $type)
    {
        return match ($type) {
            'hotel' => $owner->hotel,
            'restaurant' => $owner->restaurant,
            'car_rental', 'company' => $owner->company,
            default => null,
        };
    }
}
