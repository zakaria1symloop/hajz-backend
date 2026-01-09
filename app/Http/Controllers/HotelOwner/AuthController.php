<?php

namespace App\Http\Controllers\HotelOwner;

use App\Http\Controllers\Controller;
use App\Models\HotelOwner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:hotel_owners',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string|max:20',
            'business_license' => 'nullable|string|max:100',
        ]);

        $hotelOwner = HotelOwner::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'phone' => $validated['phone'] ?? null,
            'business_license' => $validated['business_license'] ?? null,
            'status' => 'pending',
        ]);

        $token = $hotelOwner->createToken('hotel-owner-token')->plainTextToken;

        return response()->json([
            'message' => 'Registration successful. Your account is pending approval.',
            'hotel_owner' => $hotelOwner,
            'token' => $token,
        ], 201);
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        $hotelOwner = HotelOwner::where('email', $validated['email'])->first();

        if (!$hotelOwner || !Hash::check($validated['password'], $hotelOwner->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if ($hotelOwner->isSuspended()) {
            throw ValidationException::withMessages([
                'email' => ['Your account has been suspended. Please contact support.'],
            ]);
        }

        $token = $hotelOwner->createToken('hotel-owner-token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'hotel_owner' => $hotelOwner->load('hotel'),
            'token' => $token,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }

    public function me(Request $request)
    {
        return response()->json([
            'hotel_owner' => $request->user()->load(['hotel', 'hotel.wallet']),
        ]);
    }

    public function updateProfile(Request $request)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'nullable|string|max:20',
            'business_license' => 'nullable|string|max:100',
        ]);

        $request->user()->update($validated);

        return response()->json([
            'message' => 'Profile updated successfully',
            'hotel_owner' => $request->user(),
        ]);
    }

    public function changePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        return response()->json([
            'message' => 'Password changed successfully',
        ]);
    }
}
