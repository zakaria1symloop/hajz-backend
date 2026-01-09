<?php

namespace App\Http\Controllers;

use App\Models\HotelOwner;
use App\Models\RestaurantOwner;
use App\Models\CompanyOwner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ProAuthController extends Controller
{
    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
            'type' => 'required|string|in:hotel,restaurant,car_rental',
        ]);

        $owner = null;
        $tokenName = '';
        $responseKey = '';

        switch ($validated['type']) {
            case 'hotel':
                $owner = HotelOwner::where('email', $validated['email'])->first();
                $tokenName = 'hotel-owner-token';
                $responseKey = 'hotel_owner';
                break;
            case 'restaurant':
                $owner = RestaurantOwner::where('email', $validated['email'])->first();
                $tokenName = 'restaurant-owner-token';
                $responseKey = 'restaurant_owner';
                break;
            case 'car_rental':
                $owner = CompanyOwner::where('email', $validated['email'])->first();
                $tokenName = 'company-owner-token';
                $responseKey = 'company_owner';
                break;
        }

        if (!$owner || !Hash::check($validated['password'], $owner->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Check if owner is active/not suspended
        if ($validated['type'] === 'hotel') {
            if ($owner->isSuspended()) {
                throw ValidationException::withMessages([
                    'email' => ['Your account has been suspended. Please contact support.'],
                ]);
            }
        } else {
            if (!$owner->isActive()) {
                throw ValidationException::withMessages([
                    'email' => ['Your account has been suspended. Please contact support.'],
                ]);
            }
        }

        $token = $owner->createToken($tokenName)->plainTextToken;

        // Load related data based on type
        switch ($validated['type']) {
            case 'hotel':
                $owner->load('hotel');
                break;
            case 'restaurant':
                $owner->load('restaurant');
                break;
            case 'car_rental':
                $owner->load('company');
                break;
        }

        return response()->json([
            'message' => 'Login successful',
            'type' => $validated['type'],
            $responseKey => $owner,
            'token' => $token,
        ]);
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string|max:20',
            'type' => 'required|string|in:hotel,restaurant,car_rental',
        ]);

        // Check if email exists in the appropriate table
        $emailExists = false;
        switch ($validated['type']) {
            case 'hotel':
                $emailExists = HotelOwner::where('email', $validated['email'])->exists();
                break;
            case 'restaurant':
                $emailExists = RestaurantOwner::where('email', $validated['email'])->exists();
                break;
            case 'car_rental':
                $emailExists = CompanyOwner::where('email', $validated['email'])->exists();
                break;
        }

        if ($emailExists) {
            throw ValidationException::withMessages([
                'email' => ['An account with this email already exists.'],
            ]);
        }

        $owner = null;
        $tokenName = '';
        $responseKey = '';

        switch ($validated['type']) {
            case 'hotel':
                $owner = HotelOwner::create([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'password' => Hash::make($validated['password']),
                    'phone' => $validated['phone'] ?? null,
                    'status' => 'pending',
                ]);
                $tokenName = 'hotel-owner-token';
                $responseKey = 'hotel_owner';
                break;
            case 'restaurant':
                $owner = RestaurantOwner::create([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'password' => Hash::make($validated['password']),
                    'phone' => $validated['phone'] ?? null,
                    'is_active' => true,
                ]);
                $tokenName = 'restaurant-owner-token';
                $responseKey = 'restaurant_owner';
                break;
            case 'car_rental':
                $owner = CompanyOwner::create([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'password' => Hash::make($validated['password']),
                    'phone' => $validated['phone'] ?? null,
                    'is_active' => true,
                ]);
                $tokenName = 'company-owner-token';
                $responseKey = 'company_owner';
                break;
        }

        $token = $owner->createToken($tokenName)->plainTextToken;

        return response()->json([
            'message' => 'Registration successful.',
            'type' => $validated['type'],
            $responseKey => $owner,
            'token' => $token,
        ], 201);
    }
}
