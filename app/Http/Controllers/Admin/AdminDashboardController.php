<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\HotelOwner;
use App\Models\RestaurantOwner;
use App\Models\CompanyOwner;
use App\Models\Hotel;
use App\Models\Restaurant;
use App\Models\CarRentalCompany;
use App\Models\Reservation;
use App\Models\TableReservation;
use App\Models\CarBooking;
use App\Models\Payment;
use Illuminate\Http\Request;
use Carbon\Carbon;

class AdminDashboardController extends Controller
{
    public function stats()
    {
        // Count all professionals (hotel, restaurant, car rental owners)
        $totalProfessionals = HotelOwner::count() + RestaurantOwner::count() + CompanyOwner::count();

        // hotel_owners has 'status' column, others have 'is_active'
        $pendingProfessionals = HotelOwner::where('status', 'pending')->count();
        $verifiedProfessionals = HotelOwner::where('status', 'active')->count() +
                                 RestaurantOwner::where('is_active', true)->count() +
                                 CompanyOwner::where('is_active', true)->count();

        // Get counts
        $stats = [
            'users' => [
                'total' => User::count(),
                'this_month' => User::whereMonth('created_at', Carbon::now()->month)->count(),
            ],
            'professionals' => [
                'total' => $totalProfessionals,
                'pending' => $pendingProfessionals,
                'verified' => $verifiedProfessionals,
            ],
            'hotels' => [
                'total' => Hotel::count(),
                'active' => Hotel::where('is_active', true)->count(),
                'pending' => Hotel::where('verification_status', 'pending')->count(),
            ],
            'restaurants' => [
                'total' => Restaurant::count(),
                'active' => Restaurant::where('is_active', true)->count(),
                'pending' => Restaurant::where('verification_status', 'pending')->count(),
            ],
            'car_rentals' => [
                'total' => CarRentalCompany::count(),
                'active' => CarRentalCompany::where('is_active', true)->count(),
                'pending' => CarRentalCompany::where('verification_status', 'pending')->count(),
            ],
            'bookings' => [
                'hotel_reservations' => Reservation::count(),
                'table_reservations' => TableReservation::count(),
                'car_bookings' => CarBooking::count(),
                'pending' => Reservation::where('status', 'pending')->count() +
                             TableReservation::where('status', 'pending')->count() +
                             CarBooking::where('status', 'pending')->count(),
            ],
            'revenue' => [
                'total' => Payment::where('status', 'completed')->sum('amount'),
                'this_month' => Payment::where('status', 'completed')
                    ->whereMonth('created_at', Carbon::now()->month)
                    ->sum('amount'),
                'pending' => Payment::where('status', 'pending')->sum('amount'),
            ],
        ];

        return response()->json($stats);
    }

    public function recentActivity()
    {
        // Get recent bookings
        $recentHotelBookings = Reservation::with(['user', 'room.hotel'])
            ->latest()
            ->take(5)
            ->get()
            ->map(fn($r) => [
                'type' => 'hotel',
                'id' => $r->id,
                'user' => $r->user?->name ?? $r->guest_name,
                'item' => $r->room?->hotel?->name,
                'status' => $r->status,
                'amount' => $r->total_price,
                'created_at' => $r->created_at,
            ]);

        $recentTableBookings = TableReservation::with(['restaurant'])
            ->latest()
            ->take(5)
            ->get()
            ->map(fn($r) => [
                'type' => 'restaurant',
                'id' => $r->id,
                'user' => $r->guest_name,
                'item' => $r->restaurant?->name,
                'status' => $r->status,
                'amount' => $r->total_amount,
                'created_at' => $r->created_at,
            ]);

        $recentCarBookings = CarBooking::with(['car.company'])
            ->latest()
            ->take(5)
            ->get()
            ->map(fn($r) => [
                'type' => 'car',
                'id' => $r->id,
                'user' => $r->customer_name,
                'item' => $r->car?->company?->name,
                'status' => $r->status,
                'amount' => $r->total_amount,
                'created_at' => $r->created_at,
            ]);

        // Merge and sort by date
        $recent = collect()
            ->merge($recentHotelBookings)
            ->merge($recentTableBookings)
            ->merge($recentCarBookings)
            ->sortByDesc('created_at')
            ->take(10)
            ->values();

        return response()->json($recent);
    }

    public function chartData(Request $request)
    {
        $period = $request->get('period', 'week'); // week, month, year

        if ($period === 'week') {
            $startDate = Carbon::now()->subDays(6)->startOfDay();
            $format = 'D';
        } elseif ($period === 'month') {
            $startDate = Carbon::now()->subDays(29)->startOfDay();
            $format = 'M d';
        } else {
            $startDate = Carbon::now()->subMonths(11)->startOfMonth();
            $format = 'M Y';
        }

        // Get bookings per day/month
        $bookings = [];
        $revenue = [];

        if ($period === 'year') {
            for ($i = 11; $i >= 0; $i--) {
                $date = Carbon::now()->subMonths($i);
                $label = $date->format($format);

                $bookings[$label] = Reservation::whereYear('created_at', $date->year)
                    ->whereMonth('created_at', $date->month)->count() +
                    TableReservation::whereYear('created_at', $date->year)
                    ->whereMonth('created_at', $date->month)->count() +
                    CarBooking::whereYear('created_at', $date->year)
                    ->whereMonth('created_at', $date->month)->count();

                $revenue[$label] = Payment::where('status', 'completed')
                    ->whereYear('created_at', $date->year)
                    ->whereMonth('created_at', $date->month)
                    ->sum('amount');
            }
        } else {
            $days = $period === 'week' ? 7 : 30;
            for ($i = $days - 1; $i >= 0; $i--) {
                $date = Carbon::now()->subDays($i);
                $label = $date->format($format);

                $bookings[$label] = Reservation::whereDate('created_at', $date)->count() +
                    TableReservation::whereDate('created_at', $date)->count() +
                    CarBooking::whereDate('created_at', $date)->count();

                $revenue[$label] = Payment::where('status', 'completed')
                    ->whereDate('created_at', $date)
                    ->sum('amount');
            }
        }

        return response()->json([
            'labels' => array_keys($bookings),
            'bookings' => array_values($bookings),
            'revenue' => array_values($revenue),
        ]);
    }
}
