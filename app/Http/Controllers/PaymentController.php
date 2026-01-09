<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Reservation;
use App\Models\TableReservation;
use App\Models\CarBooking;
use App\Services\SlickPayService;
use App\Services\WalletService;
use App\Services\RoomAvailabilityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    protected $slickPayService;
    protected $walletService;
    protected $roomAvailabilityService;

    public function __construct(
        SlickPayService $slickPayService,
        WalletService $walletService,
        RoomAvailabilityService $roomAvailabilityService
    ) {
        $this->slickPayService = $slickPayService;
        $this->walletService = $walletService;
        $this->roomAvailabilityService = $roomAvailabilityService;
    }

    /**
     * Initiate payment for a hotel reservation
     */
    public function initiateHotelPayment(Request $request)
    {
        $request->validate([
            'reservation_id' => 'required|exists:reservations,id',
        ]);

        $reservation = Reservation::with(['reservable', 'room', 'user'])->findOrFail($request->reservation_id);

        // Check if already paid
        if ($reservation->payment && $reservation->payment->isSuccessful()) {
            return response()->json([
                'message' => 'Reservation already paid',
            ], 400);
        }

        // Create or get payment record
        $payment = Payment::updateOrCreate(
            ['reservation_id' => $reservation->id],
            [
                'user_id' => $reservation->user_id,
                'payable_type' => Reservation::class,
                'payable_id' => $reservation->id,
                'amount' => $reservation->total_price,
                'currency' => 'DZD',
                'status' => 'pending',
                'payment_method' => 'slickpay',
                'description' => "Hotel Reservation #{$reservation->id}",
            ]
        );

        // Prepare payment data
        $paymentData = [
            'payment_id' => $payment->id,
            'amount' => $reservation->total_price,
            'description' => "Réservation Hôtel - {$reservation->reservable->name}",
            'items' => [
                [
                    'name' => "Chambre: {$reservation->room->name}",
                    'quantity' => 1,
                    'price' => $reservation->total_price,
                ]
            ],
        ];

        // Add user or guest info
        if ($reservation->user) {
            $paymentData['user'] = $reservation->user;
        } else {
            $paymentData['guest_name'] = $reservation->guest_name;
            $paymentData['guest_email'] = $reservation->guest_email;
            $paymentData['guest_phone'] = $reservation->guest_phone;
        }

        // Initiate payment with SlickPay
        $result = $this->slickPayService->initiatePayment($paymentData);

        if (!$result['success']) {
            return response()->json([
                'message' => $result['message'] ?? 'Payment initiation failed',
                'errors' => $result['errors'] ?? [],
            ], 400);
        }

        // Update payment with SlickPay invoice ID
        $payment->update([
            'slickpay_invoice_id' => $result['invoice_id'],
            'slickpay_response' => $result['data'],
        ]);

        return response()->json([
            'message' => 'Payment initiated successfully',
            'payment_url' => $result['payment_url'],
            'payment' => $payment,
        ]);
    }

    /**
     * Initiate payment for a restaurant reservation
     */
    public function initiateRestaurantPayment(Request $request)
    {
        $request->validate([
            'table_reservation_id' => 'required|exists:table_reservations,id',
        ]);

        $reservation = TableReservation::with(['restaurant', 'table', 'user'])->findOrFail($request->table_reservation_id);

        // Check if already paid
        if ($reservation->payment_status === 'paid') {
            return response()->json([
                'message' => 'Reservation already paid',
            ], 400);
        }

        // Create payment record
        $payment = Payment::create([
            'user_id' => $reservation->user_id,
            'payable_type' => TableReservation::class,
            'payable_id' => $reservation->id,
            'amount' => $reservation->total_amount,
            'currency' => 'DZD',
            'status' => 'pending',
            'payment_method' => 'slickpay',
            'description' => "Restaurant Reservation #{$reservation->id}",
        ]);

        // Update reservation with payment ID
        $reservation->update(['payment_id' => $payment->id]);

        // Prepare payment data
        $paymentData = [
            'payment_id' => $payment->id,
            'amount' => $reservation->total_amount,
            'description' => "Réservation Restaurant - {$reservation->restaurant->name}",
            'items' => [
                [
                    'name' => "Table: {$reservation->table->name}",
                    'quantity' => 1,
                    'price' => $reservation->table_total,
                ]
            ],
        ];

        // Add plats to items if any
        if ($reservation->plats_total > 0) {
            $paymentData['items'][] = [
                'name' => 'Plats commandés',
                'quantity' => 1,
                'price' => $reservation->plats_total,
            ];
        }

        // Add user or guest info
        if ($reservation->user) {
            $paymentData['user'] = $reservation->user;
        } else {
            $paymentData['guest_name'] = $reservation->guest_name;
            $paymentData['guest_email'] = $reservation->guest_email;
            $paymentData['guest_phone'] = $reservation->guest_phone;
        }

        // Initiate payment with SlickPay
        $result = $this->slickPayService->initiatePayment($paymentData);

        if (!$result['success']) {
            return response()->json([
                'message' => $result['message'] ?? 'Payment initiation failed',
                'errors' => $result['errors'] ?? [],
            ], 400);
        }

        // Update payment with SlickPay invoice ID
        $payment->update([
            'slickpay_invoice_id' => $result['invoice_id'],
            'slickpay_response' => $result['data'],
        ]);

        return response()->json([
            'message' => 'Payment initiated successfully',
            'payment_url' => $result['payment_url'],
            'payment' => $payment,
        ]);
    }

    /**
     * Initiate payment for a car rental booking
     */
    public function initiateCarPayment(Request $request)
    {
        $request->validate([
            'car_booking_id' => 'required|exists:car_bookings,id',
        ]);

        $booking = CarBooking::with(['car', 'carRentalCompany', 'user'])->findOrFail($request->car_booking_id);

        // Check if already paid
        if ($booking->payment_status === 'paid') {
            return response()->json([
                'message' => 'Booking already paid',
            ], 400);
        }

        // Create payment record
        $payment = Payment::create([
            'user_id' => $booking->user_id,
            'payable_type' => CarBooking::class,
            'payable_id' => $booking->id,
            'amount' => $booking->total_amount,
            'currency' => 'DZD',
            'status' => 'pending',
            'payment_method' => 'slickpay',
            'description' => "Car Rental Booking #{$booking->id}",
        ]);

        // Update booking with payment ID
        $booking->update(['payment_id' => $payment->id]);

        // Prepare payment data
        $paymentData = [
            'payment_id' => $payment->id,
            'amount' => $booking->total_amount,
            'description' => "Location Voiture - {$booking->car->brand} {$booking->car->model}",
            'items' => [
                [
                    'name' => "{$booking->car->brand} {$booking->car->model} - {$booking->rental_days} jours",
                    'quantity' => $booking->rental_days,
                    'price' => $booking->price_per_day,
                ]
            ],
        ];

        // Add user or guest info
        if ($booking->user) {
            $paymentData['user'] = $booking->user;
        } else {
            $paymentData['guest_name'] = $booking->customer_name;
            $paymentData['guest_email'] = $booking->customer_email;
            $paymentData['guest_phone'] = $booking->customer_phone;
        }

        // Initiate payment with SlickPay
        $result = $this->slickPayService->initiatePayment($paymentData);

        if (!$result['success']) {
            return response()->json([
                'message' => $result['message'] ?? 'Payment initiation failed',
                'errors' => $result['errors'] ?? [],
            ], 400);
        }

        // Update payment with SlickPay invoice ID
        $payment->update([
            'slickpay_invoice_id' => $result['invoice_id'],
            'slickpay_response' => $result['data'],
        ]);

        return response()->json([
            'message' => 'Payment initiated successfully',
            'payment_url' => $result['payment_url'],
            'payment' => $payment,
        ]);
    }

    /**
     * SlickPay callback handler
     */
    public function callback(Request $request)
    {
        Log::info('SlickPay Callback Received', [
            'query' => $request->query(),
            'body' => $request->all(),
        ]);

        $paymentId = $request->query('payment_id');

        if (!$paymentId) {
            Log::error('SlickPay Callback: No payment_id provided');
            return response()->json(['message' => 'Payment ID required'], 400);
        }

        $payment = Payment::find($paymentId);

        if (!$payment) {
            Log::error('SlickPay Callback: Payment not found', ['payment_id' => $paymentId]);
            return response()->json(['message' => 'Payment not found'], 404);
        }

        // Verify payment status with SlickPay
        $verificationResult = $this->slickPayService->verifyPayment($payment);

        Log::info('SlickPay Payment Verification Result', [
            'payment_id' => $paymentId,
            'result' => $verificationResult,
        ]);

        if ($verificationResult['completed']) {
            // Process successful payment
            $this->processSuccessfulPayment($payment);

            // Redirect to success page
            $frontendUrl = config('app.frontend_url', 'http://localhost:4000');
            $successUrl = $this->getSuccessUrl($payment, $frontendUrl);

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Payment successful',
                    'payment' => $payment->fresh(),
                    'redirect_url' => $successUrl,
                ]);
            }

            return redirect($successUrl);
        }

        if ($verificationResult['failed'] ?? false) {
            // Process failed payment
            $this->processFailedPayment($payment);

            $frontendUrl = config('app.frontend_url', 'http://localhost:4000');
            $failureUrl = $this->getFailureUrl($payment, $frontendUrl);

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Payment failed',
                    'payment' => $payment->fresh(),
                    'redirect_url' => $failureUrl,
                ]);
            }

            return redirect($failureUrl);
        }

        // Payment still pending
        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Payment pending',
                'payment' => $payment->fresh(),
            ]);
        }

        $frontendUrl = config('app.frontend_url', 'http://localhost:4000');
        return redirect("{$frontendUrl}/payment/pending?payment_id={$payment->id}");
    }

    /**
     * Check payment status
     */
    public function checkStatus(Request $request, $paymentId)
    {
        $payment = Payment::findOrFail($paymentId);

        // Verify with SlickPay if still pending
        if ($payment->isPending() && $payment->slickpay_invoice_id) {
            $verificationResult = $this->slickPayService->verifyPayment($payment);

            if ($verificationResult['completed']) {
                $this->processSuccessfulPayment($payment);
            } elseif ($verificationResult['failed'] ?? false) {
                $this->processFailedPayment($payment);
            }
        }

        return response()->json([
            'payment' => $payment->fresh(),
            'is_completed' => $payment->isSuccessful(),
            'is_pending' => $payment->isPending(),
            'is_failed' => $payment->isFailed(),
        ]);
    }

    /**
     * Process successful payment
     */
    protected function processSuccessfulPayment(Payment $payment)
    {
        DB::transaction(function () use ($payment) {
            // Mark payment as completed
            $payment->markAsCompleted();

            // Handle based on payable type
            if ($payment->payable_type === Reservation::class || $payment->reservation_id) {
                $this->processHotelPayment($payment);
            } elseif ($payment->payable_type === TableReservation::class) {
                $this->processRestaurantPayment($payment);
            } elseif ($payment->payable_type === CarBooking::class) {
                $this->processCarPayment($payment);
            }
        });
    }

    /**
     * Process hotel reservation payment
     */
    protected function processHotelPayment(Payment $payment)
    {
        $reservation = $payment->reservation ?? Reservation::find($payment->payable_id);

        if (!$reservation) {
            Log::error('Hotel reservation not found for payment', ['payment_id' => $payment->id]);
            return;
        }

        // Update reservation status
        $reservation->update([
            'status' => 'confirmed',
            'confirmed_at' => now(),
        ]);

        // Block room dates
        if ($reservation->room_id && $reservation->room) {
            $this->roomAvailabilityService->blockDatesForReservation($reservation->room, $reservation);
        }

        // Credit hotel wallet
        $this->walletService->processPayment($payment);

        Log::info('Hotel payment processed successfully', [
            'payment_id' => $payment->id,
            'reservation_id' => $reservation->id,
        ]);
    }

    /**
     * Process restaurant reservation payment
     */
    protected function processRestaurantPayment(Payment $payment)
    {
        $reservation = TableReservation::find($payment->payable_id);

        if (!$reservation) {
            Log::error('Restaurant reservation not found for payment', ['payment_id' => $payment->id]);
            return;
        }

        // Update reservation status
        $reservation->update([
            'status' => 'confirmed',
            'payment_status' => 'paid',
        ]);

        // Credit restaurant wallet
        $this->walletService->processTableReservationPayment($payment, $reservation);

        Log::info('Restaurant payment processed successfully', [
            'payment_id' => $payment->id,
            'reservation_id' => $reservation->id,
        ]);
    }

    /**
     * Process car rental payment
     */
    protected function processCarPayment(Payment $payment)
    {
        $booking = CarBooking::find($payment->payable_id);

        if (!$booking) {
            Log::error('Car booking not found for payment', ['payment_id' => $payment->id]);
            return;
        }

        // Update booking status
        $booking->update([
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'confirmed_at' => now(),
        ]);

        // Credit company wallet
        $this->walletService->processCarBookingPayment($payment, $booking);

        Log::info('Car rental payment processed successfully', [
            'payment_id' => $payment->id,
            'booking_id' => $booking->id,
        ]);
    }

    /**
     * Process failed payment
     */
    protected function processFailedPayment(Payment $payment)
    {
        $payment->markAsFailed();

        // Update associated booking status
        if ($payment->payable_type === Reservation::class || $payment->reservation_id) {
            $reservation = $payment->reservation ?? Reservation::find($payment->payable_id);
            if ($reservation) {
                $reservation->update(['status' => 'cancelled']);
            }
        } elseif ($payment->payable_type === TableReservation::class) {
            $reservation = TableReservation::find($payment->payable_id);
            if ($reservation) {
                $reservation->update([
                    'status' => 'cancelled',
                    'payment_status' => 'failed',
                ]);
            }
        } elseif ($payment->payable_type === CarBooking::class) {
            $booking = CarBooking::find($payment->payable_id);
            if ($booking) {
                $booking->update([
                    'status' => 'cancelled',
                    'payment_status' => 'failed',
                ]);
            }
        }

        Log::info('Payment marked as failed', ['payment_id' => $payment->id]);
    }

    /**
     * Get success URL based on payment type
     */
    protected function getSuccessUrl(Payment $payment, string $frontendUrl): string
    {
        if ($payment->payable_type === Reservation::class || $payment->reservation_id) {
            $reservationId = $payment->reservation_id ?? $payment->payable_id;
            return "{$frontendUrl}/reservations/{$reservationId}/success";
        } elseif ($payment->payable_type === TableReservation::class) {
            return "{$frontendUrl}/table-reservations/{$payment->payable_id}/success";
        } elseif ($payment->payable_type === CarBooking::class) {
            return "{$frontendUrl}/car-bookings/{$payment->payable_id}/success";
        }

        return "{$frontendUrl}/payment/success?payment_id={$payment->id}";
    }

    /**
     * Get failure URL based on payment type
     */
    protected function getFailureUrl(Payment $payment, string $frontendUrl): string
    {
        if ($payment->payable_type === Reservation::class || $payment->reservation_id) {
            $reservationId = $payment->reservation_id ?? $payment->payable_id;
            return "{$frontendUrl}/reservations/{$reservationId}/failure";
        } elseif ($payment->payable_type === TableReservation::class) {
            return "{$frontendUrl}/table-reservations/{$payment->payable_id}/failure";
        } elseif ($payment->payable_type === CarBooking::class) {
            return "{$frontendUrl}/car-bookings/{$payment->payable_id}/failure";
        }

        return "{$frontendUrl}/payment/failed?payment_id={$payment->id}";
    }
}
