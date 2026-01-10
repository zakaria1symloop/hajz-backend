<?php

namespace App\Http\Controllers;

use App\Models\Restaurant;
use App\Models\TableReservation;
use App\Models\Payment;
use App\Services\ChargilyService;
use Illuminate\Http\Request;
use Carbon\Carbon;

class RestaurantController extends Controller
{
    protected ChargilyService $chargilyService;

    public function __construct(ChargilyService $chargilyService)
    {
        $this->chargilyService = $chargilyService;
    }
    public function index(Request $request)
    {
        // Show all restaurants (active and inactive) - frontend will display badge for inactive ones
        $query = Restaurant::with(['images', 'wilaya']);

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
                    ->orWhere('cuisine_type', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filter by cuisine type
        if ($request->has('cuisine_type')) {
            $query->where('cuisine_type', $request->cuisine_type);
        }

        // Filter by price range
        if ($request->has('price_range')) {
            $query->where('price_range', $request->price_range);
        }

        // Filter by rating
        if ($request->has('rating')) {
            $query->where('rating', '>=', $request->rating);
        }

        // Filter by verification status
        if ($request->has('verified')) {
            $query->where('verification_status', 'verified');
        }

        // Sorting
        $sortBy = $request->get('sort', 'rating');
        $sortOrder = $request->get('order', 'desc');

        switch ($sortBy) {
            case 'name':
                $query->orderBy('name', $sortOrder);
                break;
            case 'price':
                $query->orderBy('price_range', $sortOrder);
                break;
            default:
                $query->orderBy('rating', 'desc');
        }

        $restaurants = $query->paginate($request->get('per_page', 12));

        // Add tables and plats count
        $restaurants->getCollection()->transform(function ($restaurant) {
            $restaurant->tables_count = $restaurant->tables()->count();
            $restaurant->available_tables = $restaurant->tables()->where('is_available', true)->count();
            $restaurant->plats_count = $restaurant->plats()->where('is_available', true)->count();
            return $restaurant;
        });

        return response()->json($restaurants);
    }

    public function show(Restaurant $restaurant)
    {
        $restaurant->load(['images', 'plats' => function ($query) {
            $query->where('is_available', true)->with('images')->orderBy('category');
        }, 'tables' => function ($query) {
            $query->where('is_available', true);
        }, 'wilaya']);

        // Group plats by category
        $platsByCategory = $restaurant->plats->groupBy('category');

        return response()->json([
            'restaurant' => $restaurant,
            'plats_by_category' => $platsByCategory,
            'tables_count' => $restaurant->tables->count(),
        ]);
    }

    public function featured()
    {
        $restaurants = Restaurant::with(['images', 'wilaya'])
            ->where('is_active', true)
            ->orderBy('rating', 'desc')
            ->limit(6)
            ->get();

        // Add extra info
        $restaurants->transform(function ($restaurant) {
            $restaurant->tables_count = $restaurant->tables()->count();
            $restaurant->plats_count = $restaurant->plats()->where('is_available', true)->count();
            return $restaurant;
        });

        return response()->json($restaurants);
    }

    public function cuisineTypes()
    {
        $types = Restaurant::where('is_active', true)
            ->distinct()
            ->pluck('cuisine_type');

        return response()->json($types);
    }

    public function search(Request $request)
    {
        // Show all restaurants - frontend will display badge for inactive ones
        $query = Restaurant::query();

        if ($request->has('q')) {
            $search = $request->q;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")
                    ->orWhere('cuisine_type', 'like', "%{$search}%");
            });
        }

        return response()->json($query->paginate(12));
    }

    public function getAvailableSlots(Request $request, Restaurant $restaurant)
    {
        $request->validate([
            'date' => 'required|date|after_or_equal:today',
            'guests_count' => 'nullable|integer|min:1',
        ]);

        $date = $request->date;
        $openingTime = $restaurant->opening_time ? substr($restaurant->opening_time, 0, 5) : '09:00';
        $closingTime = $restaurant->closing_time ? substr($restaurant->closing_time, 0, 5) : '23:00';
        $slotDuration = 60; // 1 hour slots

        // Generate time slots
        $slots = [];
        try {
            $start = Carbon::createFromFormat('H:i', $openingTime);
            $end = Carbon::createFromFormat('H:i', $closingTime);
        } catch (\Exception $e) {
            $start = Carbon::createFromFormat('H:i', '09:00');
            $end = Carbon::createFromFormat('H:i', '23:00');
        }

        while ($start->lt($end)) {
            $slots[] = $start->format('H:i');
            $start->addMinutes($slotDuration);
        }

        // Get available tables
        $tablesQuery = $restaurant->tables()->where('is_available', true);
        if ($request->guests_count) {
            $tablesQuery->where('capacity', '>=', $request->guests_count);
        }
        $tables = $tablesQuery->get();

        // Get existing reservations
        $existingReservations = TableReservation::where('restaurant_id', $restaurant->id)
            ->where('reservation_date', $date)
            ->whereIn('status', ['pending', 'confirmed'])
            ->get();

        // Calculate available slots using minutes from midnight for reliable comparison
        $availableSlots = [];
        foreach ($slots as $slot) {
            $slotParts = explode(':', $slot);
            $slotStartMinutes = (int)$slotParts[0] * 60 + (int)$slotParts[1];
            $slotEndMinutes = $slotStartMinutes + $slotDuration;

            $availableTablesForSlot = 0;
            foreach ($tables as $table) {
                $isAvailable = true;
                foreach ($existingReservations as $reservation) {
                    if ($reservation->restaurant_table_id !== $table->id) continue;

                    // Parse reservation time to minutes
                    $resTimeParts = explode(':', $reservation->reservation_time);
                    $resStartMinutes = (int)$resTimeParts[0] * 60 + (int)$resTimeParts[1];
                    $resEndMinutes = $resStartMinutes + $reservation->duration_minutes;

                    // Check for time overlap
                    if ($slotStartMinutes < $resEndMinutes && $slotEndMinutes > $resStartMinutes) {
                        $isAvailable = false;
                        break;
                    }
                }
                if ($isAvailable) {
                    $availableTablesForSlot++;
                }
            }

            $availableSlots[] = [
                'time' => $slot,
                'available' => $availableTablesForSlot > 0,
                'tables_available' => $availableTablesForSlot,
            ];
        }

        return response()->json([
            'date' => $date,
            'opening_time' => $openingTime,
            'closing_time' => $closingTime,
            'slots' => $availableSlots,
            'total_tables' => $tables->count(),
        ]);
    }

    public function getAvailableTables(Request $request, Restaurant $restaurant)
    {
        $request->validate([
            'date' => 'required|date|after_or_equal:today',
            'start_time' => 'required|date_format:H:i',
            'duration_hours' => 'required|integer|min:1|max:8',
            'guests_count' => 'required|integer|min:1',
        ]);

        $date = $request->date;
        $durationHours = (int) $request->duration_hours;

        // Convert times to minutes from midnight for easier comparison
        $timeParts = explode(':', $request->start_time);
        $startMinutes = (int)$timeParts[0] * 60 + (int)$timeParts[1];
        $endMinutes = $startMinutes + ($durationHours * 60);

        // Get existing reservations for this date
        $existingReservations = TableReservation::where('restaurant_id', $restaurant->id)
            ->where('reservation_date', $date)
            ->whereIn('status', ['pending', 'confirmed'])
            ->get();

        // Get all tables that can accommodate the guests
        $tables = $restaurant->tables()
            ->where('is_available', true)
            ->where('capacity', '>=', $request->guests_count)
            ->get();

        // Filter tables that are available for the entire duration
        $availableTables = $tables->filter(function ($table) use ($existingReservations, $startMinutes, $endMinutes) {
            foreach ($existingReservations as $reservation) {
                if ($reservation->restaurant_table_id !== $table->id) continue;

                // Parse reservation time to minutes
                $resTimeParts = explode(':', $reservation->reservation_time);
                $resStartMinutes = (int)$resTimeParts[0] * 60 + (int)$resTimeParts[1];
                $resEndMinutes = $resStartMinutes + $reservation->duration_minutes;

                // Check for overlap: two ranges overlap if start1 < end2 AND end1 > start2
                if ($startMinutes < $resEndMinutes && $endMinutes > $resStartMinutes) {
                    return false; // This table has a conflict
                }
            }
            return true; // No conflicts found
        })->values();

        return response()->json([
            'tables' => $availableTables,
            'date' => $date,
            'start_time' => $request->start_time,
            'end_time' => sprintf('%02d:%02d', floor($endMinutes / 60), $endMinutes % 60),
            'duration_hours' => $durationHours,
        ]);
    }

    public function guestReservation(Request $request, Restaurant $restaurant)
    {
        $validated = $request->validate([
            'reservation_date' => 'required|date|after_or_equal:today',
            'reservation_time' => 'required|date_format:H:i',
            'guests_count' => 'required|integer|min:1',
            'guest_name' => 'required|string|max:255',
            'guest_email' => 'required|email|max:255',
            'guest_phone' => 'required|string|max:20',
            'special_requests' => 'nullable|string|max:500',
            'table_id' => 'nullable|integer|exists:restaurant_tables,id',
            'plats' => 'nullable|array',
            'plats.*.plat_id' => 'required_with:plats|integer|exists:plats,id',
            'plats.*.quantity' => 'required_with:plats|integer|min:1',
            'duration_hours' => 'nullable|integer|min:1|max:5',
        ]);

        $durationHours = $validated['duration_hours'] ?? 1;
        $duration = $durationHours * 60; // Convert to minutes
        $startTime = Carbon::createFromFormat('H:i', $validated['reservation_time']);
        $endTime = $startTime->copy()->addMinutes($duration);

        // Use selected table or find an available one
        if (!empty($validated['table_id'])) {
            $selectedTable = $restaurant->tables()
                ->where('id', $validated['table_id'])
                ->where('is_available', true)
                ->where('capacity', '>=', $validated['guests_count'])
                ->first();

            if (!$selectedTable) {
                return response()->json([
                    'error' => 'Selected table is not available.',
                ], 422);
            }

            // Check if table is available at the selected time
            $hasConflict = TableReservation::where('restaurant_table_id', $selectedTable->id)
                ->where('reservation_date', $validated['reservation_date'])
                ->whereIn('status', ['pending', 'confirmed'])
                ->where(function ($q) use ($startTime, $endTime) {
                    $q->whereRaw('reservation_time < ?', [$endTime->format('H:i:s')])
                      ->whereRaw('ADDTIME(reservation_time, SEC_TO_TIME(duration_minutes * 60)) > ?', [$startTime->format('H:i:s')]);
                })
                ->exists();

            if ($hasConflict) {
                return response()->json([
                    'error' => 'Selected table is already reserved for this time.',
                ], 422);
            }

            $availableTable = $selectedTable;
        } else {
            $availableTable = $restaurant->tables()
                ->where('is_available', true)
                ->where('capacity', '>=', $validated['guests_count'])
                ->whereDoesntHave('reservations', function ($query) use ($validated, $startTime, $endTime) {
                    $query->where('reservation_date', $validated['reservation_date'])
                        ->whereIn('status', ['pending', 'confirmed'])
                        ->where(function ($q) use ($startTime, $endTime) {
                            $q->whereRaw('reservation_time < ?', [$endTime->format('H:i:s')])
                              ->whereRaw('ADDTIME(reservation_time, SEC_TO_TIME(duration_minutes * 60)) > ?', [$startTime->format('H:i:s')]);
                        });
                })
                ->orderBy('capacity', 'asc')
                ->first();
        }

        if (!$availableTable) {
            return response()->json([
                'error' => 'No tables available for the selected time and party size.',
            ], 422);
        }

        // Calculate table price (price per hour × hours)
        $tablePricePerHour = (float) $availableTable->price_per_hour;
        $hours = $durationHours;
        $tableTotal = $tablePricePerHour * $hours;

        // Calculate plats total (optional)
        $platsTotal = 0;
        $platsData = [];
        if (!empty($validated['plats'])) {
            foreach ($validated['plats'] as $platItem) {
                $plat = \App\Models\Plat::find($platItem['plat_id']);
                if ($plat && $plat->restaurant_id === $restaurant->id) {
                    $quantity = $platItem['quantity'];
                    $totalPrice = $plat->price * $quantity;
                    $platsTotal += $totalPrice;
                    $platsData[$plat->id] = [
                        'quantity' => $quantity,
                        'unit_price' => $plat->price,
                        'total_price' => $totalPrice,
                    ];
                }
            }
        }

        // Calculate total amount (table + plats)
        $totalAmount = $tableTotal + $platsTotal;

        // Calculate commission (10% default)
        $commissionRate = 10;
        $commissionAmount = $totalAmount * ($commissionRate / 100);
        $netAmount = $totalAmount - $commissionAmount;

        // Create the reservation (pending status)
        $reservation = TableReservation::create([
            'restaurant_id' => $restaurant->id,
            'restaurant_table_id' => $availableTable->id,
            'reservation_date' => $validated['reservation_date'],
            'reservation_time' => $validated['reservation_time'],
            'duration_minutes' => $duration,
            'table_price_per_hour' => $tablePricePerHour,
            'table_total' => $tableTotal,
            'guests_count' => $validated['guests_count'],
            'guest_name' => $validated['guest_name'],
            'guest_email' => $validated['guest_email'],
            'guest_phone' => $validated['guest_phone'],
            'special_requests' => $validated['special_requests'] ?? null,
            'plats_total' => $platsTotal,
            'total_amount' => $totalAmount,
            'commission_rate' => $commissionRate,
            'commission_amount' => $commissionAmount,
            'net_amount' => $netAmount,
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);

        // Attach plats to reservation
        if (!empty($platsData)) {
            $reservation->plats()->attach($platsData);
        }

        // Create payment record
        $payment = Payment::create([
            'user_id' => null, // Guest booking
            'payable_type' => TableReservation::class,
            'payable_id' => $reservation->id,
            'amount' => $totalAmount,
            'currency' => 'DZD',
            'status' => 'pending',
            'payment_method' => 'chargily',
            'description' => "Restaurant Reservation #{$reservation->id}",
        ]);

        // Update reservation with payment ID
        $reservation->update(['payment_id' => $payment->id]);

        // Initiate Chargily payment
        $paymentItems = [
            [
                'name' => "Table: {$availableTable->name} ({$durationHours}h)",
                'quantity' => 1,
                'price' => $tableTotal,
            ]
        ];

        if ($platsTotal > 0) {
            $paymentItems[] = [
                'name' => 'Plats commandés',
                'quantity' => 1,
                'price' => $platsTotal,
            ];
        }

        $paymentData = [
            'payment_id' => $payment->id,
            'amount' => $totalAmount,
            'guest_name' => $validated['guest_name'],
            'guest_email' => $validated['guest_email'],
            'guest_phone' => $validated['guest_phone'],
            'description' => "Réservation Restaurant - {$restaurant->name}",
            'items' => $paymentItems,
        ];

        $result = $this->chargilyService->initiatePayment($paymentData);

        if (!$result['success']) {
            // If payment initiation fails, delete the reservation and payment
            $reservation->plats()->detach();
            $payment->delete();
            $reservation->delete();

            return response()->json([
                'error' => $result['message'] ?? 'Payment initiation failed',
                'errors' => $result['errors'] ?? [],
            ], 400);
        }

        // Update payment with Chargily checkout ID
        $payment->update([
            'chargily_checkout_id' => $result['checkout_id'],
            'chargily_response' => $result['data'],
        ]);

        return response()->json([
            'message' => 'Reservation created. Please complete payment.',
            'reservation' => $reservation->load('table', 'plats'),
            'table_total' => $tableTotal,
            'plats_total' => $platsTotal,
            'total_price' => $totalAmount,
            'payment_url' => $result['payment_url'],
            'payment' => $payment,
        ], 201);
    }
}
