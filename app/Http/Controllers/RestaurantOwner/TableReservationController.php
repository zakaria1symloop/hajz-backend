<?php

namespace App\Http\Controllers\RestaurantOwner;

use App\Http\Controllers\Controller;
use App\Models\TableReservation;
use App\Models\RestaurantTable;
use App\Models\AdminSetting;
use Illuminate\Http\Request;
use Carbon\Carbon;

class TableReservationController extends Controller
{
    // Default time slots (can be customized per restaurant later)
    private function getTimeSlots($openingTime, $closingTime, $slotDuration = 60)
    {
        $slots = [];

        // Handle both H:i and H:i:s formats
        $openingTime = substr($openingTime, 0, 5); // Get first 5 chars (HH:MM)
        $closingTime = substr($closingTime, 0, 5);

        try {
            $start = Carbon::createFromFormat('H:i', $openingTime);
            $end = Carbon::createFromFormat('H:i', $closingTime);
        } catch (\Exception $e) {
            // Fallback to default times
            $start = Carbon::createFromFormat('H:i', '09:00');
            $end = Carbon::createFromFormat('H:i', '23:00');
        }

        while ($start->lt($end)) {
            $slots[] = $start->format('H:i');
            $start->addMinutes($slotDuration);
        }

        return $slots;
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
            'table_id' => 'nullable|exists:restaurant_tables,id',
            'table_ids' => 'nullable|array',
            'table_ids.*' => 'exists:restaurant_tables,id',
            'reservation_date' => 'required|date|after_or_equal:today',
            'reservation_time' => 'required|date_format:H:i',
            'duration_minutes' => 'nullable|integer|min:30|max:240',
            'guests_count' => 'required|integer|min:1',
            'guest_name' => 'required|string|max:255',
            'guest_phone' => 'nullable|string|max:20',
            'guest_email' => 'nullable|email|max:255',
            'special_requests' => 'nullable|string|max:500',
            'status' => 'nullable|in:pending,confirmed',
            'plats' => 'nullable|array',
            'plats.*.plat_id' => 'required|exists:plats,id',
            'plats.*.quantity' => 'required|integer|min:1',
        ]);

        // Get table IDs (support both single and multiple)
        $tableIds = $validated['table_ids'] ?? [];
        if (empty($tableIds) && !empty($validated['table_id'])) {
            $tableIds = [$validated['table_id']];
        }

        if (empty($tableIds)) {
            return response()->json([
                'message' => 'Please select at least one table.',
            ], 422);
        }

        // Verify all tables belong to this restaurant
        $tables = $restaurant->tables()->whereIn('id', $tableIds)->get();
        if ($tables->count() !== count($tableIds)) {
            return response()->json([
                'message' => 'One or more tables not found.',
            ], 404);
        }

        $duration = $validated['duration_minutes'] ?? 60;
        $startTime = Carbon::createFromFormat('H:i', $validated['reservation_time']);
        $endTime = $startTime->copy()->addMinutes($duration);

        // Check availability for all selected tables
        $conflictingTables = [];
        foreach ($tableIds as $tableId) {
            $conflicting = TableReservation::where('restaurant_table_id', $tableId)
                ->where('reservation_date', $validated['reservation_date'])
                ->whereIn('status', ['pending', 'confirmed'])
                ->where(function ($query) use ($startTime, $endTime) {
                    $query->where(function ($q) use ($startTime, $endTime) {
                        $q->whereRaw('reservation_time < ?', [$endTime->format('H:i:s')])
                          ->whereRaw('ADDTIME(reservation_time, SEC_TO_TIME(duration_minutes * 60)) > ?', [$startTime->format('H:i:s')]);
                    });
                })
                ->exists();

            if ($conflicting) {
                $table = $tables->find($tableId);
                $conflictingTables[] = $table->name;
            }
        }

        if (!empty($conflictingTables)) {
            return response()->json([
                'message' => 'The following tables are not available: ' . implode(', ', $conflictingTables),
            ], 422);
        }

        // Calculate plats total if plats are provided
        $platsTotal = 0;
        $platsData = [];
        if (!empty($validated['plats'])) {
            foreach ($validated['plats'] as $platItem) {
                $plat = $restaurant->plats()->find($platItem['plat_id']);
                if ($plat) {
                    $totalPrice = $plat->price * $platItem['quantity'];
                    $platsTotal += $totalPrice;
                    $platsData[$plat->id] = [
                        'quantity' => $platItem['quantity'],
                        'unit_price' => $plat->price,
                        'total_price' => $totalPrice,
                    ];
                }
            }
        }

        // Create reservations for all selected tables
        $reservations = [];
        foreach ($tableIds as $tableId) {
            $reservation = TableReservation::create([
                'restaurant_id' => $restaurant->id,
                'restaurant_table_id' => $tableId,
                'reservation_date' => $validated['reservation_date'],
                'reservation_time' => $validated['reservation_time'],
                'duration_minutes' => $duration,
                'guests_count' => $validated['guests_count'],
                'guest_name' => $validated['guest_name'],
                'guest_phone' => $validated['guest_phone'] ?? null,
                'guest_email' => $validated['guest_email'] ?? null,
                'special_requests' => $validated['special_requests'] ?? null,
                'status' => $validated['status'] ?? 'confirmed',
                'confirmed_at' => ($validated['status'] ?? 'confirmed') === 'confirmed' ? now() : null,
                'plats_total' => $platsTotal,
                'total_amount' => $platsTotal,
            ]);

            // Attach plats to the reservation
            if (!empty($platsData)) {
                $reservation->plats()->attach($platsData);
            }

            $reservations[] = $reservation->load(['table', 'plats']);
        }

        $message = count($reservations) === 1
            ? 'Reservation created successfully'
            : count($reservations) . ' reservations created successfully';

        return response()->json([
            'message' => $message,
            'reservations' => $reservations,
        ], 201);
    }

    public function getAvailableSlots(Request $request)
    {
        $restaurant = $request->user()->restaurant;

        if (!$restaurant) {
            return response()->json([
                'message' => 'No restaurant found.',
            ], 404);
        }

        $request->validate([
            'date' => 'required|date|after_or_equal:today',
            'table_id' => 'nullable|exists:restaurant_tables,id',
            'guests_count' => 'nullable|integer|min:1',
        ]);

        $date = $request->date;
        $openingTime = $restaurant->opening_time ? (string)$restaurant->opening_time : '09:00';
        $closingTime = $restaurant->closing_time ? (string)$restaurant->closing_time : '23:00';
        $slotDuration = 60; // 1 hour slots

        $allSlots = $this->getTimeSlots($openingTime, $closingTime, $slotDuration);

        // Get tables (filter by capacity if guests_count provided)
        $tablesQuery = $restaurant->tables()->where('is_available', true);
        if ($request->guests_count) {
            $tablesQuery->where('capacity', '>=', $request->guests_count);
        }
        if ($request->table_id) {
            $tablesQuery->where('id', $request->table_id);
        }
        $tables = $tablesQuery->get();

        // Get existing reservations for this date
        $existingReservations = TableReservation::where('restaurant_id', $restaurant->id)
            ->where('reservation_date', $date)
            ->whereIn('status', ['pending', 'confirmed'])
            ->get();

        // Calculate available slots per table
        $availability = [];
        foreach ($tables as $table) {
            $tableSlots = [];
            foreach ($allSlots as $slot) {
                $slotStart = Carbon::createFromFormat('H:i', $slot);
                $slotEnd = $slotStart->copy()->addMinutes($slotDuration);

                // Check if slot conflicts with any reservation
                $isAvailable = true;
                foreach ($existingReservations as $reservation) {
                    if ($reservation->restaurant_table_id !== $table->id) continue;

                    $resStart = Carbon::createFromFormat('H:i:s', $reservation->reservation_time);
                    $resEnd = $resStart->copy()->addMinutes($reservation->duration_minutes);

                    if ($slotStart->lt($resEnd) && $slotEnd->gt($resStart)) {
                        $isAvailable = false;
                        break;
                    }
                }

                $tableSlots[] = [
                    'time' => $slot,
                    'available' => $isAvailable,
                ];
            }

            $availability[] = [
                'table' => $table,
                'slots' => $tableSlots,
                'available_count' => count(array_filter($tableSlots, fn($s) => $s['available'])),
            ];
        }

        return response()->json([
            'date' => $date,
            'opening_time' => $openingTime,
            'closing_time' => $closingTime,
            'slot_duration' => $slotDuration,
            'tables' => $availability,
        ]);
    }

    public function index(Request $request)
    {
        $restaurant = $request->user()->restaurant;

        if (!$restaurant) {
            return response()->json([
                'message' => 'No restaurant found.',
            ], 404);
        }

        $query = $restaurant->tableReservations()
            ->with(['table', 'plats', 'user']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date
        if ($request->has('date')) {
            $query->where('reservation_date', $request->date);
        }

        // Filter by date range
        if ($request->has('from_date')) {
            $query->where('reservation_date', '>=', $request->from_date);
        }
        if ($request->has('to_date')) {
            $query->where('reservation_date', '<=', $request->to_date);
        }

        $reservations = $query->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($reservations);
    }

    public function show(Request $request, TableReservation $reservation)
    {
        $restaurant = $request->user()->restaurant;

        if (!$restaurant || $reservation->restaurant_id !== $restaurant->id) {
            return response()->json([
                'message' => 'Reservation not found.',
            ], 404);
        }

        return response()->json([
            'reservation' => $reservation->load(['table', 'plats', 'user']),
        ]);
    }

    public function confirm(Request $request, TableReservation $reservation)
    {
        $restaurant = $request->user()->restaurant;

        if (!$restaurant || $reservation->restaurant_id !== $restaurant->id) {
            return response()->json([
                'message' => 'Reservation not found.',
            ], 404);
        }

        if ($reservation->status !== 'pending') {
            return response()->json([
                'message' => 'Reservation cannot be confirmed.',
            ], 422);
        }

        $reservation->update([
            'status' => 'confirmed',
            'confirmed_at' => now(),
        ]);

        return response()->json([
            'message' => 'Reservation confirmed successfully',
            'reservation' => $reservation->fresh()->load(['table', 'plats']),
        ]);
    }

    public function cancel(Request $request, TableReservation $reservation)
    {
        $restaurant = $request->user()->restaurant;

        if (!$restaurant || $reservation->restaurant_id !== $restaurant->id) {
            return response()->json([
                'message' => 'Reservation not found.',
            ], 404);
        }

        if (!in_array($reservation->status, ['pending', 'confirmed'])) {
            return response()->json([
                'message' => 'Reservation cannot be cancelled.',
            ], 422);
        }

        $request->validate([
            'cancellation_reason' => 'nullable|string|max:500',
        ]);

        $reservation->update([
            'status' => 'cancelled',
            'cancellation_reason' => $request->cancellation_reason,
            'cancelled_at' => now(),
        ]);

        return response()->json([
            'message' => 'Reservation cancelled successfully',
            'reservation' => $reservation->fresh(),
        ]);
    }

    public function complete(Request $request, TableReservation $reservation)
    {
        $restaurant = $request->user()->restaurant;

        if (!$restaurant || $reservation->restaurant_id !== $restaurant->id) {
            return response()->json([
                'message' => 'Reservation not found.',
            ], 404);
        }

        if ($reservation->status !== 'confirmed') {
            return response()->json([
                'message' => 'Only confirmed reservations can be completed.',
            ], 422);
        }

        // Allow setting the final bill amount when completing
        $validated = $request->validate([
            'bill_amount' => 'nullable|numeric|min:0',
        ]);

        $billAmount = $validated['bill_amount'] ?? $reservation->total_amount ?? 0;

        // Calculate commission
        $commissionRate = AdminSetting::getCommissionRate();
        $commissionAmount = $billAmount * ($commissionRate / 100);
        $netAmount = $billAmount - $commissionAmount;

        $reservation->update([
            'status' => 'completed',
            'total_amount' => $billAmount,
            'commission_rate' => $commissionRate,
            'commission_amount' => $commissionAmount,
            'net_amount' => $netAmount,
        ]);

        // Credit wallet if there's a bill amount
        if ($billAmount > 0) {
            $wallet = $restaurant->getOrCreateWallet();

            // Credit net amount (after commission) to available balance
            $wallet->creditAvailableBalance(
                $netAmount,
                $reservation->id,
                "Reservation #{$reservation->id} - {$reservation->guest_name} (after {$commissionRate}% commission)"
            );

            // Record commission deduction for transparency
            if ($commissionAmount > 0) {
                $wallet->deductCommission($commissionAmount, $reservation->id);
            }
        }

        return response()->json([
            'message' => 'Reservation completed successfully',
            'reservation' => $reservation->fresh(),
            'wallet_credited' => $billAmount > 0,
            'total_amount' => $billAmount,
            'commission_rate' => $commissionRate,
            'commission_amount' => $commissionAmount,
            'net_amount' => $netAmount,
        ]);
    }

    public function noShow(Request $request, TableReservation $reservation)
    {
        $restaurant = $request->user()->restaurant;

        if (!$restaurant || $reservation->restaurant_id !== $restaurant->id) {
            return response()->json([
                'message' => 'Reservation not found.',
            ], 404);
        }

        if ($reservation->status !== 'confirmed') {
            return response()->json([
                'message' => 'Only confirmed reservations can be marked as no-show.',
            ], 422);
        }

        $reservation->update([
            'status' => 'no_show',
        ]);

        return response()->json([
            'message' => 'Reservation marked as no-show',
            'reservation' => $reservation->fresh(),
        ]);
    }

    public function updatePlats(Request $request, TableReservation $reservation)
    {
        $restaurant = $request->user()->restaurant;

        if (!$restaurant || $reservation->restaurant_id !== $restaurant->id) {
            return response()->json([
                'message' => 'Reservation not found.',
            ], 404);
        }

        $request->validate([
            'plats' => 'required|array',
            'plats.*.plat_id' => 'required|exists:plats,id',
            'plats.*.quantity' => 'required|integer|min:1',
            'plats.*.notes' => 'nullable|string',
        ]);

        // Clear existing plats
        $reservation->plats()->detach();

        // Add new plats
        $platsTotal = 0;
        foreach ($request->plats as $platData) {
            $plat = $restaurant->plats()->find($platData['plat_id']);
            if ($plat) {
                $totalPrice = $plat->price * $platData['quantity'];
                $platsTotal += $totalPrice;

                $reservation->plats()->attach($plat->id, [
                    'quantity' => $platData['quantity'],
                    'unit_price' => $plat->price,
                    'total_price' => $totalPrice,
                    'notes' => $platData['notes'] ?? null,
                ]);
            }
        }

        $reservation->update([
            'plats_total' => $platsTotal,
            'total_amount' => $platsTotal,
        ]);

        return response()->json([
            'message' => 'Plats updated successfully',
            'reservation' => $reservation->fresh()->load('plats'),
        ]);
    }

    public function getStats(Request $request)
    {
        $restaurant = $request->user()->restaurant;

        if (!$restaurant) {
            return response()->json([
                'message' => 'No restaurant found.',
            ], 404);
        }

        $today = now()->toDateString();
        $thisMonth = now()->startOfMonth()->toDateString();

        $stats = [
            'today' => [
                'total' => $restaurant->tableReservations()->where('reservation_date', $today)->count(),
                'pending' => $restaurant->tableReservations()->where('reservation_date', $today)->where('status', 'pending')->count(),
                'confirmed' => $restaurant->tableReservations()->where('reservation_date', $today)->where('status', 'confirmed')->count(),
            ],
            'this_month' => [
                'total' => $restaurant->tableReservations()->where('reservation_date', '>=', $thisMonth)->count(),
                'completed' => $restaurant->tableReservations()->where('reservation_date', '>=', $thisMonth)->where('status', 'completed')->count(),
                'cancelled' => $restaurant->tableReservations()->where('reservation_date', '>=', $thisMonth)->where('status', 'cancelled')->count(),
                'revenue' => $restaurant->tableReservations()
                    ->where('reservation_date', '>=', $thisMonth)
                    ->where('status', 'completed')
                    ->sum('total_amount'),
            ],
        ];

        return response()->json($stats);
    }
}
