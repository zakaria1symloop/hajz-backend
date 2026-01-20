<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Reservation;
use App\Services\ChargilyPayService;
use App\Services\WalletService;
use App\Services\RoomAvailabilityService;
use App\Notifications\HotelBookingConfirmation;
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
                $payment->reservation->update([
                    'status' => 'confirmed',
                    'confirmed_at' => now(),
                ]);

                // Process hotel wallet credit (if this is a hotel reservation with a room)
                $reservation = $payment->reservation;
                if ($reservation->reservable_type === 'App\Models\Hotel' && $reservation->room_id) {
                    // Credit hotel wallet with payment (minus commission)
                    $this->walletService->processPayment($payment);

                    // Block room dates for the reservation
                    $room = $reservation->room;
                    if ($room) {
                        $this->roomAvailabilityService->blockDatesForReservation($room, $reservation);
                    }

                    // Send confirmation email to guest
                    $this->sendBookingConfirmationEmail($reservation);
                }
                break;

            case 'checkout.failed':
                $payment->update([
                    'status' => 'failed',
                    'chargily_response' => $checkoutData,
                ]);
                $payment->reservation->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                ]);
                break;
        }

        return response()->json(['success' => true]);
    }

    protected function sendBookingConfirmationEmail($reservation)
    {
        try {
            // Load necessary relationships
            $reservation->load(['room.hotel']);

            // Get guest email
            $email = $reservation->guest_email ?? $reservation->user->email ?? null;

            if (!$email) {
                Log::warning('No email found for reservation', ['reservation_id' => $reservation->id]);
                return;
            }

            // Send notification
            Notification::route('mail', $email)
                ->notify(new HotelBookingConfirmation($reservation));

            Log::info('Booking confirmation email sent', [
                'reservation_id' => $reservation->id,
                'email' => $email
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send booking confirmation email', [
                'reservation_id' => $reservation->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}
