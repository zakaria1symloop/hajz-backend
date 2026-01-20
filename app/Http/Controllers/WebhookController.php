<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Reservation;
use App\Models\TableReservation;
use App\Models\CarBooking;
use App\Services\ChargilyPayService;
use App\Services\WalletService;
use App\Services\RoomAvailabilityService;
use App\Notifications\HotelBookingConfirmation;
use App\Notifications\RestaurantBookingConfirmation;
use App\Notifications\CarRentalBookingConfirmation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function __construct(
        protected ChargilyPayService $chargilyPayService,
        protected WalletService $walletService,
        protected RoomAvailabilityService $roomAvailabilityService
    ) {}

    public function handleChargily(Request $request)
    {
        $payload = $request->getContent();
        $signature = $request->header('signature');

        if (!$this->chargilyPayService->validateWebhook($payload, $signature)) {
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        $data = $this->chargilyPayService->getWebhookData($payload);

        if (!isset($data['type']) || !isset($data['data'])) {
            return response()->json(['error' => 'Invalid payload'], 400);
        }

        $checkoutData = $data['data'];
        $checkoutId = $checkoutData['id'] ?? null;

        if (!$checkoutId) {
            return response()->json(['error' => 'Checkout ID not found'], 400);
        }

        $payment = Payment::where('checkout_id', $checkoutId)->first();

        if (!$payment) {
            return response()->json(['error' => 'Payment not found'], 404);
        }

        switch ($data['type']) {
            case 'checkout.paid':
                $payment->update([
                    'status' => 'paid',
                    'payment_method' => $checkoutData['payment_method'] ?? null,
                    'chargily_response' => $checkoutData,
                ]);

                // Handle different booking types
                $this->processSuccessfulPayment($payment);
                break;

            case 'checkout.failed':
                $payment->update([
                    'status' => 'failed',
                    'chargily_response' => $checkoutData,
                ]);
                $this->processCancelledPayment($payment);
                break;
        }

        return response()->json(['success' => true]);
    }

    protected function processSuccessfulPayment($payment)
    {
        $payableType = $payment->payable_type;

        // Handle Hotel Reservations (Reservation model)
        if ($payableType === 'App\\Models\\Reservation' && $payment->payable) {
            $reservation = $payment->payable;
            $reservation->update([
                'status' => 'confirmed',
                'confirmed_at' => now(),
            ]);

            if ($reservation->reservable_type === 'App\\Models\\Hotel' && $reservation->room_id) {
                // Credit hotel wallet
                $this->walletService->processPayment($payment);

                // Block room dates
                $room = $reservation->room;
                if ($room) {
                    $this->roomAvailabilityService->blockDatesForReservation($room, $reservation);
                }

                // Send email
                $this->sendHotelConfirmationEmail($reservation);
            }
        }

        // Handle Restaurant Reservations (TableReservation model)
        if ($payableType === 'App\\Models\\TableReservation' && $payment->payable) {
            $reservation = $payment->payable;
            $reservation->update([
                'status' => 'confirmed',
                'payment_status' => 'paid',
                'confirmed_at' => now(),
            ]);

            // Send email
            $this->sendRestaurantConfirmationEmail($reservation);
        }

        // Handle Car Rental Bookings (CarBooking model)
        if ($payableType === 'App\\Models\\CarBooking' && $payment->payable) {
            $booking = $payment->payable;
            $booking->update([
                'status' => 'confirmed',
                'payment_status' => 'paid',
            ]);

            // Send email
            $this->sendCarRentalConfirmationEmail($booking);
        }
    }

    protected function processCancelledPayment($payment)
    {
        $payableType = $payment->payable_type;

        if ($payableType === 'App\\Models\\Reservation' && $payment->payable) {
            $payment->payable->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);
        }

        if ($payableType === 'App\\Models\\TableReservation' && $payment->payable) {
            $payment->payable->update([
                'status' => 'cancelled',
                'payment_status' => 'failed',
                'cancelled_at' => now(),
            ]);
        }

        if ($payableType === 'App\\Models\\CarBooking' && $payment->payable) {
            $payment->payable->update([
                'status' => 'cancelled',
                'payment_status' => 'failed',
            ]);
        }
    }

    protected function sendHotelConfirmationEmail($reservation)
    {
        try {
            $reservation->load(['room.hotel', 'user']);
            $email = $reservation->guest_email ?? $reservation->user?->email;

            if (!$email) {
                Log::warning('No email for hotel reservation', ['id' => $reservation->id]);
                return;
            }

            Notification::route('mail', $email)
                ->notify(new HotelBookingConfirmation($reservation));

            Log::info('Hotel confirmation email sent', [
                'reservation_id' => $reservation->id,
                'email' => $email
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send hotel email', [
                'reservation_id' => $reservation->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    protected function sendRestaurantConfirmationEmail($reservation)
    {
        try {
            $reservation->load(['restaurant', 'table', 'user']);
            $email = $reservation->guest_email ?? $reservation->user?->email;

            if (!$email) {
                Log::warning('No email for restaurant reservation', ['id' => $reservation->id]);
                return;
            }

            Notification::route('mail', $email)
                ->notify(new RestaurantBookingConfirmation($reservation));

            Log::info('Restaurant confirmation email sent', [
                'reservation_id' => $reservation->id,
                'email' => $email
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send restaurant email', [
                'reservation_id' => $reservation->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    protected function sendCarRentalConfirmationEmail($booking)
    {
        try {
            $booking->load(['car.company', 'user']);
            $email = $booking->customer_email ?? $booking->user?->email;

            if (!$email) {
                Log::warning('No email for car booking', ['id' => $booking->id]);
                return;
            }

            Notification::route('mail', $email)
                ->notify(new CarRentalBookingConfirmation($booking));

            Log::info('Car rental confirmation email sent', [
                'booking_id' => $booking->id,
                'email' => $email
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send car rental email', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}
