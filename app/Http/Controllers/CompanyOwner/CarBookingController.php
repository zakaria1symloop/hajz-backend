<?php

namespace App\Http\Controllers\CompanyOwner;

use App\Http\Controllers\Controller;
use App\Models\AdminSetting;
use App\Models\CarBooking;
use Illuminate\Http\Request;

class CarBookingController extends Controller
{
    public function index(Request $request)
    {
        $company = $request->user()->company;

        if (!$company) {
            return response()->json([
                'message' => 'No company found.',
            ], 404);
        }

        $query = $company->bookings()->with(['car', 'user']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->has('from_date')) {
            $query->where('pickup_date', '>=', $request->from_date);
        }
        if ($request->has('to_date')) {
            $query->where('pickup_date', '<=', $request->to_date);
        }

        // Filter by car
        if ($request->has('car_id')) {
            $query->where('car_id', $request->car_id);
        }

        $bookings = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json($bookings);
    }

    public function show(Request $request, CarBooking $booking)
    {
        $company = $request->user()->company;

        if (!$company || $booking->car_rental_company_id !== $company->id) {
            return response()->json([
                'message' => 'Booking not found.',
            ], 404);
        }

        return response()->json([
            'booking' => $booking->load(['car', 'user']),
        ]);
    }

    public function confirm(Request $request, CarBooking $booking)
    {
        $company = $request->user()->company;

        if (!$company || $booking->car_rental_company_id !== $company->id) {
            return response()->json([
                'message' => 'Booking not found.',
            ], 404);
        }

        if ($booking->status !== 'pending') {
            return response()->json([
                'message' => 'Booking cannot be confirmed.',
            ], 422);
        }

        $booking->update([
            'status' => 'confirmed',
            'confirmed_at' => now(),
        ]);

        return response()->json([
            'message' => 'Booking confirmed successfully',
            'booking' => $booking->fresh()->load('car'),
        ]);
    }

    public function cancel(Request $request, CarBooking $booking)
    {
        $company = $request->user()->company;

        if (!$company || $booking->car_rental_company_id !== $company->id) {
            return response()->json([
                'message' => 'Booking not found.',
            ], 404);
        }

        if (!in_array($booking->status, ['pending', 'confirmed'])) {
            return response()->json([
                'message' => 'Booking cannot be cancelled.',
            ], 422);
        }

        $request->validate([
            'cancellation_reason' => 'nullable|string|max:500',
        ]);

        $booking->update([
            'status' => 'cancelled',
            'cancellation_reason' => $request->cancellation_reason,
            'cancelled_at' => now(),
        ]);

        return response()->json([
            'message' => 'Booking cancelled successfully',
            'booking' => $booking->fresh(),
        ]);
    }

    public function pickUp(Request $request, CarBooking $booking)
    {
        $company = $request->user()->company;

        if (!$company || $booking->car_rental_company_id !== $company->id) {
            return response()->json([
                'message' => 'Booking not found.',
            ], 404);
        }

        if ($booking->status !== 'confirmed') {
            return response()->json([
                'message' => 'Only confirmed bookings can be marked as picked up.',
            ], 422);
        }

        $request->validate([
            'pickup_mileage' => 'required|integer|min:0',
            'notes' => 'nullable|string|max:500',
        ]);

        $booking->update([
            'status' => 'picked_up',
            'pickup_mileage' => $request->pickup_mileage,
            'pickup_notes' => $request->notes,
            'picked_up_at' => now(),
        ]);

        return response()->json([
            'message' => 'Car picked up successfully',
            'booking' => $booking->fresh()->load('car'),
        ]);
    }

    public function returnCar(Request $request, CarBooking $booking)
    {
        $company = $request->user()->company;

        if (!$company || $booking->car_rental_company_id !== $company->id) {
            return response()->json([
                'message' => 'Booking not found.',
            ], 404);
        }

        if ($booking->status !== 'picked_up') {
            return response()->json([
                'message' => 'Only picked up bookings can be returned.',
            ], 422);
        }

        $request->validate([
            'return_mileage' => 'required|integer|min:0',
            'notes' => 'nullable|string|max:500',
            'extra_charges' => 'nullable|numeric|min:0',
            'extra_charges_reason' => 'nullable|string|max:500',
        ]);

        // Calculate extra KM charges
        $car = $booking->car;
        $kmDriven = $request->return_mileage - ($booking->pickup_mileage ?? 0);
        $rentalDays = max(1, $booking->rental_days);
        $allowedKm = ($car->mileage_limit ?? 0) * $rentalDays;
        $extraKm = max(0, $kmDriven - $allowedKm);
        $extraKmCharge = $extraKm * ($car->extra_km_price ?? 0);

        $extraCharges = ($request->extra_charges ?? 0) + $extraKmCharge;
        $totalAmount = $booking->subtotal + $extraCharges;

        $booking->update([
            'status' => 'returned',
            'return_mileage' => $request->return_mileage,
            'return_notes' => $request->notes,
            'km_driven' => $kmDriven,
            'extra_km' => $extraKm,
            'extra_km_charge' => $extraKmCharge,
            'extra_charges' => $extraCharges,
            'extra_charges_reason' => $request->extra_charges_reason,
            'total_amount' => $totalAmount,
            'returned_at' => now(),
        ]);

        return response()->json([
            'message' => 'Car returned successfully',
            'booking' => $booking->fresh()->load('car'),
            'km_driven' => $kmDriven,
            'extra_km' => $extraKm,
            'extra_km_charge' => $extraKmCharge,
            'total_amount' => $totalAmount,
        ]);
    }

    public function complete(Request $request, CarBooking $booking)
    {
        $company = $request->user()->company;

        if (!$company || $booking->car_rental_company_id !== $company->id) {
            return response()->json([
                'message' => 'Booking not found.',
            ], 404);
        }

        if ($booking->status !== 'returned') {
            return response()->json([
                'message' => 'Only returned bookings can be completed.',
            ], 422);
        }

        $booking->update([
            'status' => 'completed',
            'payment_status' => 'fully_paid',
        ]);

        // Credit wallet with commission calculation
        $wallet = $company->getOrCreateWallet();
        $totalAmount = (float) ($booking->total_amount ?? $booking->subtotal ?? 0);
        $commissionRate = AdminSetting::getCommissionRate();
        $commissionAmount = $totalAmount * ($commissionRate / 100);
        $companyAmount = $totalAmount - $commissionAmount;

        // Credit the company's wallet (after commission deduction)
        // For car rentals, we credit directly to available since rental is complete
        $wallet->available_balance += $companyAmount;
        $wallet->total_earned += $companyAmount;
        $wallet->save();

        // Build detailed description
        $car = $booking->car;
        $pickupDate = $booking->pickup_date ? $booking->pickup_date->format('d M') : '';
        $returnDate = $booking->return_date ? $booking->return_date->format('d M Y') : '';
        $description = "{$car->brand} {$car->model} • {$booking->customer_name} • {$pickupDate} - {$returnDate} • {$booking->rental_days} days";

        // Record the transaction
        $wallet->transactions()->create([
            'car_booking_id' => $booking->id,
            'type' => 'booking_credit',
            'amount' => $companyAmount,
            'balance_before' => $wallet->available_balance - $companyAmount,
            'balance_after' => $wallet->available_balance,
            'description' => $description,
            'metadata' => [
                'booking_id' => $booking->id,
                'car' => "{$car->brand} {$car->model}",
                'customer' => $booking->customer_name,
                'pickup_date' => $pickupDate,
                'return_date' => $returnDate,
                'rental_days' => $booking->rental_days,
                'total_amount' => $totalAmount,
                'commission_rate' => $commissionRate,
                'commission_amount' => $commissionAmount,
                'net_amount' => $companyAmount,
            ],
        ]);

        return response()->json([
            'message' => 'Booking completed successfully',
            'booking' => $booking->fresh(),
            'wallet_credited' => $companyAmount,
        ]);
    }

    public function noShow(Request $request, CarBooking $booking)
    {
        $company = $request->user()->company;

        if (!$company || $booking->car_rental_company_id !== $company->id) {
            return response()->json([
                'message' => 'Booking not found.',
            ], 404);
        }

        if ($booking->status !== 'confirmed') {
            return response()->json([
                'message' => 'Only confirmed bookings can be marked as no-show.',
            ], 422);
        }

        $booking->update([
            'status' => 'no_show',
        ]);

        return response()->json([
            'message' => 'Booking marked as no-show',
            'booking' => $booking->fresh(),
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
            'today' => [
                'pickups' => $company->bookings()->where('pickup_date', $today)->where('status', 'confirmed')->count(),
                'returns' => $company->bookings()->where('return_date', $today)->where('status', 'picked_up')->count(),
                'pending' => $company->bookings()->where('status', 'pending')->count(),
            ],
            'this_month' => [
                'total' => $company->bookings()->where('pickup_date', '>=', $thisMonth)->count(),
                'completed' => $company->bookings()->where('pickup_date', '>=', $thisMonth)->whereIn('status', ['returned', 'completed'])->count(),
                'cancelled' => $company->bookings()->where('pickup_date', '>=', $thisMonth)->where('status', 'cancelled')->count(),
                'revenue' => $company->bookings()
                    ->where('pickup_date', '>=', $thisMonth)
                    ->whereIn('status', ['returned', 'completed'])
                    ->sum('total_amount'),
            ],
            'active_rentals' => $company->bookings()->where('status', 'picked_up')->count(),
        ]);
    }
}
