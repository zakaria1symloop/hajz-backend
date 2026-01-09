<?php

namespace App\Services;

use Chargily\ChargilyPay\Auth\Credentials;
use Chargily\ChargilyPay\ChargilyPay;
use App\Models\Payment;
use App\Models\Reservation;

class ChargilyPayService
{
    protected ?ChargilyPay $chargilyPay = null;
    protected bool $isConfigured = false;

    public function __construct()
    {
        $publicKey = config('services.chargily.public_key');
        $secretKey = config('services.chargily.secret_key');

        // Only initialize if credentials are properly configured
        if ($publicKey && $secretKey && strlen($publicKey) >= 16 && strlen($secretKey) >= 16) {
            $credentials = new Credentials([
                'mode' => config('services.chargily.mode', 'test'),
                'public' => $publicKey,
                'secret' => $secretKey,
            ]);

            $this->chargilyPay = new ChargilyPay($credentials);
            $this->isConfigured = true;
        }
    }

    public function isConfigured(): bool
    {
        return $this->isConfigured;
    }

    public function createCheckout(Reservation $reservation, string $successUrl, string $failureUrl): array
    {
        if (!$this->isConfigured || !$this->chargilyPay) {
            throw new \Exception('Chargily Pay is not configured. Please set valid API keys.');
        }

        $checkout = $this->chargilyPay->checkouts()->create([
            'amount' => $reservation->total_price,
            'currency' => 'dzd',
            'success_url' => $successUrl,
            'failure_url' => $failureUrl,
            'webhook_endpoint' => config('app.url') . '/api/webhook/chargily',
            'description' => "Reservation #{$reservation->id}",
            'metadata' => [
                'reservation_id' => $reservation->id,
                'user_id' => $reservation->user_id,
            ],
        ]);

        return [
            'checkout_id' => $checkout->getId(),
            'checkout_url' => $checkout->getCheckoutUrl(),
        ];
    }

    public function validateWebhook(string $payload, string $signature): bool
    {
        return $this->chargilyPay->webhook()->validate($payload, $signature);
    }

    public function getWebhookData(string $payload): array
    {
        return json_decode($payload, true);
    }
}
