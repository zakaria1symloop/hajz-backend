<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\Hotel;
use App\Models\Flight;
use App\Models\Restaurant;
use App\Models\Room;
use App\Models\Payment;
use App\Services\ChargilyService;
use App\Services\RoomAvailabilityService;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ReservationController extends Controller
{
    public function __construct(
        protected ChargilyService $chargilyService,
        protected RoomAvailabilityService $roomAvailabilityService,
        protected WalletService $walletService
    ) {}

    public function index(Request $request)
    {
        $reservations = $request->user()
            ->reservations()
            ->with(['reservable', 'payment', 'room.images'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json($reservations);
    }

    public function store(Request $request)
    {
        $request->validate([
            'type' => 'required|in:hotel,flight,restaurant',
            'item_id' => 'required|integer',
            'room_id' => 'nullable|integer|exists:rooms,id',
            'check_in' => 'nullable|date|after_or_equal:today',
            'check_out' => 'nullable|date|after:check_in',
            'reservation_date' => 'nullable|date',
            'guests' => 'required|integer|min:1',
            'special_requests' => 'nullable|string',
        ]);

        $type = $request->type;
        $item = $this->getReservableItem($type, $request->item_id);

        if (!$item) {
            return response()->json(['error' => 'Item not found'], 404);
        }

        $room = null;
        $totalPrice = 0;

        // Handle hotel room reservations
        if ($type === 'hotel') {
            if (!$request->room_id) {
                return response()->json(['error' => 'Room ID is required for hotel bookings'], 422);
            }
            if (!$request->check_in || !$request->check_out) {
                return response()->json(['error' => 'Check-in and check-out dates are required'], 422);
            }

            $room = Room::where('id', $request->room_id)
                ->where('hotel_id', $item->id)
                ->where('is_active', true)
                ->first();

            if (!$room) {
                return response()->json(['error' => 'Room not found or not available'], 404);
            }

            // Check room capacity
            if ($room->capacity < $request->guests) {
                return response()->json(['error' => 'Room capacity exceeded. Maximum guests: ' . $room->capacity], 422);
            }

            // Check availability
            $checkIn = Carbon::parse($request->check_in);
            $checkOut = Carbon::parse($request->check_out);

            if (!$this->roomAvailabilityService->isRoomAvailable($room, $checkIn, $checkOut)) {
                return response()->json(['error' => 'Room is not available for the selected dates'], 422);
            }

            $totalPrice = $this->roomAvailabilityService->calculatePrice($room, $checkIn, $checkOut);
        } else {
            $totalPrice = $this->calculateTotalPrice($type, $item, $request);
        }

        $reservation = Reservation::create([
            'user_id' => $request->user()->id,
            'reservable_type' => get_class($item),
            'reservable_id' => $item->id,
            'room_id' => $room?->id,
            'check_in' => $request->check_in,
            'check_out' => $request->check_out,
            'reservation_date' => $request->reservation_date,
            'guests' => $request->guests,
            'total_price' => $totalPrice,
            'status' => 'pending',
            'special_requests' => $request->special_requests,
        ]);

        // Create payment record
        $payment = Payment::create([
            'reservation_id' => $reservation->id,
            'user_id' => $request->user()->id,
            'payable_type' => Reservation::class,
            'payable_id' => $reservation->id,
            'amount' => $totalPrice,
            'currency' => 'DZD',
            'status' => 'pending',
            'payment_method' => 'chargily',
            'description' => "Hotel Reservation #{$reservation->id}",
        ]);

        // Initiate Chargily payment
        $paymentData = [
            'payment_id' => $payment->id,
            'amount' => $totalPrice,
            'user' => $request->user(),
            'description' => "Réservation Hôtel - {$item->name}" . ($room ? " - Chambre: {$room->name}" : ""),
        ];

        $result = $this->chargilyService->initiatePayment($paymentData);

        if (!$result['success']) {
            // If payment initiation fails, delete the reservation and payment
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
            'reservation' => $reservation->load(['reservable', 'payment', 'room.images']),
            'payment_url' => $result['payment_url'],
            'payment' => $payment,
        ], 201);
    }

    public function show(Request $request, Reservation $reservation)
    {
        if ($reservation->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json($reservation->load(['reservable', 'payment', 'room.images']));
    }

    public function cancel(Request $request, Reservation $reservation)
    {
        if ($reservation->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if (!in_array($reservation->status, ['pending', 'confirmed'])) {
            return response()->json(['error' => 'Cannot cancel this reservation'], 400);
        }

        // Release room dates if it was a confirmed hotel reservation
        if ($reservation->status === 'confirmed' && $reservation->room_id) {
            $this->roomAvailabilityService->releaseDatesForReservation($reservation);
        }

        $reservation->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => $request->input('reason', 'Cancelled by user'),
        ]);

        return response()->json(['message' => 'Reservation cancelled successfully']);
    }

    public function guestStore(Request $request)
    {
        $request->validate([
            'type' => 'required|in:hotel,flight,restaurant',
            'item_id' => 'required|integer',
            'room_id' => 'nullable|integer|exists:rooms,id',
            'check_in' => 'nullable|date|after_or_equal:today',
            'check_out' => 'nullable|date|after:check_in',
            'reservation_date' => 'nullable|date',
            'guests' => 'required|integer|min:1',
            'special_requests' => 'nullable|string',
            'guest_name' => 'required|string|max:255',
            'guest_email' => 'required|email|max:255',
            'guest_phone' => 'required|string|max:20',
        ]);

        $type = $request->type;
        $item = $this->getReservableItem($type, $request->item_id);

        if (!$item) {
            return response()->json(['error' => 'Item not found'], 404);
        }

        $room = null;
        $totalPrice = 0;

        // Handle hotel room reservations
        if ($type === 'hotel') {
            if (!$request->room_id) {
                return response()->json(['error' => 'Room ID is required for hotel bookings'], 422);
            }
            if (!$request->check_in || !$request->check_out) {
                return response()->json(['error' => 'Check-in and check-out dates are required'], 422);
            }

            $room = Room::where('id', $request->room_id)
                ->where('hotel_id', $item->id)
                ->where('is_active', true)
                ->first();

            if (!$room) {
                return response()->json(['error' => 'Room not found or not available'], 404);
            }

            // Check room capacity
            if ($room->capacity < $request->guests) {
                return response()->json(['error' => 'Room capacity exceeded. Maximum guests: ' . $room->capacity], 422);
            }

            // Check availability
            $checkIn = Carbon::parse($request->check_in);
            $checkOut = Carbon::parse($request->check_out);

            if (!$this->roomAvailabilityService->isRoomAvailable($room, $checkIn, $checkOut)) {
                return response()->json(['error' => 'Room is not available for the selected dates'], 422);
            }

            $totalPrice = $this->roomAvailabilityService->calculatePrice($room, $checkIn, $checkOut);
        } else {
            $totalPrice = $this->calculateTotalPrice($type, $item, $request);
        }

        $reservation = Reservation::create([
            'user_id' => null, // Guest booking - no user
            'reservable_type' => get_class($item),
            'reservable_id' => $item->id,
            'room_id' => $room?->id,
            'check_in' => $request->check_in,
            'check_out' => $request->check_out,
            'reservation_date' => $request->reservation_date,
            'guests' => $request->guests,
            'total_price' => $totalPrice,
            'status' => 'pending',
            'special_requests' => $request->special_requests,
            'guest_name' => $request->guest_name,
            'guest_email' => $request->guest_email,
            'guest_phone' => $request->guest_phone,
        ]);

        // Create payment record
        $payment = Payment::create([
            'reservation_id' => $reservation->id,
            'user_id' => null, // Guest booking
            'payable_type' => Reservation::class,
            'payable_id' => $reservation->id,
            'amount' => $totalPrice,
            'currency' => 'DZD',
            'status' => 'pending',
            'payment_method' => 'chargily',
            'description' => "Hotel Reservation #{$reservation->id}",
        ]);

        // Initiate Chargily payment for guest
        $paymentData = [
            'payment_id' => $payment->id,
            'amount' => $totalPrice,
            'guest_name' => $request->guest_name,
            'guest_email' => $request->guest_email,
            'guest_phone' => $request->guest_phone,
            'description' => "Réservation Hôtel - {$item->name}" . ($room ? " - Chambre: {$room->name}" : ""),
        ];

        $result = $this->chargilyService->initiatePayment($paymentData);

        if (!$result['success']) {
            // If payment initiation fails, delete the reservation and payment
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
            'reservation' => $reservation->load(['reservable', 'payment', 'room.images']),
            'payment_url' => $result['payment_url'],
            'payment' => $payment,
        ], 201);
    }

    protected function getReservableItem(string $type, int $id)
    {
        return match ($type) {
            'hotel' => Hotel::find($id),
            'flight' => Flight::find($id),
            'restaurant' => Restaurant::find($id),
            default => null,
        };
    }

    protected function calculateTotalPrice(string $type, $item, Request $request): float
    {
        return match ($type) {
            'hotel' => $this->calculateHotelPrice($item, $request),
            'flight' => $item->price * $request->guests,
            'restaurant' => $item->price_range * $request->guests,
            default => 0,
        };
    }

    protected function calculateHotelPrice($hotel, Request $request): float
    {
        $checkIn = Carbon::parse($request->check_in);
        $checkOut = Carbon::parse($request->check_out);
        $nights = $checkIn->diffInDays($checkOut);

        return $hotel->price_per_night * $nights;
    }
}
