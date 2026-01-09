<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Payment;
use App\Models\User;

class ChargilyService
{
    protected $baseUrl;
    protected $publicKey;
    protected $secretKey;
    protected $mode;

    public function __construct()
    {
        $this->mode = config('chargily.mode', 'test');
        $this->publicKey = config('chargily.public_key');
        $this->secretKey = config('chargily.secret_key');
        $this->baseUrl = $this->mode === 'live'
            ? 'https://pay.chargily.net/api/v2'
            : 'https://pay.chargily.net/test/api/v2';
    }

    /**
     * Check if Chargily is properly configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->secretKey) && !empty($this->publicKey);
    }

    /**
     * Make a request to Chargily API
     */
    protected function request(string $method, string $endpoint, array $data = []): array
    {
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($endpoint, '/');

        Log::info('Chargily API Request', [
            'method' => $method,
            'url' => $url,
            'data' => $data,
        ]);

        try {
            $http = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ]);

            $response = match (strtoupper($method)) {
                'GET' => $http->get($url, $data),
                'POST' => $http->post($url, $data),
                'PUT' => $http->put($url, $data),
                'DELETE' => $http->delete($url, $data),
                default => throw new \Exception("Unsupported HTTP method: {$method}"),
            };

            $result = $response->json();

            Log::info('Chargily API Response', [
                'status' => $response->status(),
                'response' => $result,
            ]);

            return [
                'success' => $response->successful(),
                'data' => $result,
                'message' => $result['message'] ?? null,
                'errors' => $result['errors'] ?? [],
                'status' => $response->status(),
            ];
        } catch (\Exception $e) {
            Log::error('Chargily API Error', [
                'error' => $e->getMessage(),
                'url' => $url,
            ]);

            return [
                'success' => false,
                'data' => null,
                'message' => $e->getMessage(),
                'errors' => ['exception' => $e->getMessage()],
                'status' => 500,
            ];
        }
    }

    /**
     * Create a customer in Chargily
     */
    public function createCustomer(array $params): array
    {
        $data = [
            'name' => $params['name'],
            'email' => $params['email'],
            'phone' => $params['phone'] ?? null,
            'address' => $params['address'] ?? null,
        ];

        // Remove null values
        $data = array_filter($data, fn($v) => $v !== null);

        return $this->request('POST', 'customers', $data);
    }

    /**
     * Get or create customer for a user
     */
    public function getOrCreateCustomer(User $user): ?string
    {
        if ($user->chargily_customer_id) {
            return $user->chargily_customer_id;
        }

        $response = $this->createCustomer([
            'name' => $user->name ?? 'Guest User',
            'email' => $user->email,
            'phone' => $user->phone ?? null,
        ]);

        if ($response['success'] && isset($response['data']['id'])) {
            $user->update(['chargily_customer_id' => $response['data']['id']]);
            return $response['data']['id'];
        }

        return null;
    }

    /**
     * Create a checkout session
     */
    public function createCheckout(array $params): array
    {
        $data = [
            'amount' => $params['amount'],
            'currency' => 'dzd',
            'success_url' => $params['success_url'],
            'failure_url' => $params['failure_url'],
            'webhook_endpoint' => $params['webhook_url'] ?? config('chargily.webhook_url'),
            'description' => $params['description'] ?? 'Hajz Booking Payment',
            'locale' => $params['locale'] ?? 'fr',
            'metadata' => $params['metadata'] ?? [],
        ];

        // Add customer if provided
        if (!empty($params['customer_id'])) {
            $data['customer_id'] = $params['customer_id'];
        }

        return $this->request('POST', 'checkouts', $data);
    }

    /**
     * Get checkout details
     */
    public function getCheckout(string $checkoutId): array
    {
        return $this->request('GET', "checkouts/{$checkoutId}");
    }

    /**
     * Verify payment status
     */
    public function verifyPayment(Payment $payment): array
    {
        if (!$payment->chargily_checkout_id) {
            return [
                'success' => false,
                'completed' => false,
                'message' => 'No Chargily checkout ID found',
            ];
        }

        $response = $this->getCheckout($payment->chargily_checkout_id);

        if (!$response['success']) {
            return [
                'success' => false,
                'completed' => false,
                'message' => $response['message'] ?? 'Failed to retrieve checkout',
            ];
        }

        $data = $response['data'];
        $status = $data['status'] ?? null;

        if ($status === 'paid') {
            $payment->update([
                'status' => 'paid',
                'chargily_response' => $data,
                'paid_at' => now(),
            ]);

            return [
                'success' => true,
                'completed' => true,
                'message' => 'Payment completed successfully',
                'data' => $data,
            ];
        }

        if (in_array($status, ['failed', 'canceled', 'expired'])) {
            $payment->update([
                'status' => 'failed',
                'chargily_response' => $data,
            ]);

            return [
                'success' => true,
                'completed' => false,
                'failed' => true,
                'message' => 'Payment failed',
                'data' => $data,
            ];
        }

        return [
            'success' => true,
            'completed' => false,
            'message' => 'Payment pending',
            'data' => $data,
        ];
    }

    /**
     * Initiate payment for a reservation
     */
    public function initiatePayment(array $params): array
    {
        $customerId = $params['customer_id'] ?? null;

        // Get or create customer for registered users
        if (!$customerId && isset($params['user'])) {
            $customerId = $this->getOrCreateCustomer($params['user']);
        }

        // For guest bookings, create a customer
        if (!$customerId && isset($params['guest_name'], $params['guest_email'])) {
            $customerResponse = $this->createCustomer([
                'name' => $params['guest_name'],
                'email' => $params['guest_email'],
                'phone' => $params['guest_phone'] ?? null,
            ]);

            if ($customerResponse['success']) {
                $customerId = $customerResponse['data']['id'] ?? null;
            }
        }

        // Build URLs
        $frontendUrl = config('app.frontend_url', 'http://localhost:4000');
        $backendUrl = config('app.url');

        $successUrl = $params['success_url'] ?? "{$frontendUrl}/booking/success?payment_id=" . ($params['payment_id'] ?? '');
        $failureUrl = $params['failure_url'] ?? "{$frontendUrl}/booking/failure?payment_id=" . ($params['payment_id'] ?? '');
        $webhookUrl = $params['webhook_url'] ?? "{$backendUrl}/api/payments/chargily-webhook";

        // Create checkout
        $checkoutResponse = $this->createCheckout([
            'amount' => $params['amount'],
            'success_url' => $successUrl,
            'failure_url' => $failureUrl,
            'webhook_url' => $webhookUrl,
            'description' => $params['description'] ?? 'Hajz Booking',
            'customer_id' => $customerId,
            'metadata' => [
                'payment_id' => $params['payment_id'] ?? null,
            ],
        ]);

        if (!$checkoutResponse['success']) {
            return [
                'success' => false,
                'message' => $checkoutResponse['message'] ?? 'Failed to create checkout',
                'errors' => $checkoutResponse['errors'] ?? [],
            ];
        }

        $checkoutData = $checkoutResponse['data'];

        return [
            'success' => true,
            'checkout_id' => $checkoutData['id'] ?? null,
            'payment_url' => $checkoutData['checkout_url'] ?? null,
            'data' => $checkoutData,
        ];
    }

    /**
     * Validate webhook signature
     */
    public function validateWebhookSignature(string $payload, string $signature): bool
    {
        $computedSignature = hash_hmac('sha256', $payload, $this->secretKey);
        return hash_equals($computedSignature, $signature);
    }

    /**
     * Process webhook event
     */
    public function processWebhook(array $payload): array
    {
        $event = $payload['type'] ?? null;
        $data = $payload['data'] ?? [];

        Log::info('Chargily Webhook Event', [
            'event' => $event,
            'data' => $data,
        ]);

        return [
            'event' => $event,
            'data' => $data,
            'checkout_id' => $data['id'] ?? null,
            'status' => $data['status'] ?? null,
            'metadata' => $data['metadata'] ?? [],
        ];
    }
}
