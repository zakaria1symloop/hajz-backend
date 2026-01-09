<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Payment;
use App\Models\User;

class SlickPayService
{
    protected $baseUrl;
    protected $publicKey;
    protected $sandbox;
    protected $callbackUrl;

    public function __construct()
    {
        $this->sandbox = config('slickpay.sandbox', true);
        $this->publicKey = config('slickpay.public_key');
        $this->baseUrl = $this->sandbox
            ? config('slickpay.sandbox_url')
            : config('slickpay.production_url');
        $this->callbackUrl = config('slickpay.callback_url');
    }

    /**
     * Check if SlickPay is properly configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->publicKey);
    }

    /**
     * Make a request to SlickPay API
     */
    protected function request(string $method, string $endpoint, array $data = []): array
    {
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($endpoint, '/');

        Log::info('SlickPay API Request', [
            'method' => $method,
            'url' => $url,
            'data' => $data,
        ]);

        try {
            $http = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->publicKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ]);

            // Disable SSL verification in sandbox mode
            if ($this->sandbox) {
                $http = $http->withoutVerifying();
            }

            $response = match (strtoupper($method)) {
                'GET' => $http->get($url, $data),
                'POST' => $http->post($url, $data),
                'PUT' => $http->put($url, $data),
                'DELETE' => $http->delete($url, $data),
                default => throw new \Exception("Unsupported HTTP method: {$method}"),
            };

            $result = $response->json();

            Log::info('SlickPay API Response', [
                'status' => $response->status(),
                'response' => $result,
            ]);

            return [
                'success' => $response->successful() && ($result['success'] ?? false),
                'data' => $result['data'] ?? $result,
                'message' => $result['message'] ?? null,
                'errors' => $result['errors'] ?? [],
                'status' => $response->status(),
            ];
        } catch (\Exception $e) {
            Log::error('SlickPay API Error', [
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
     * Create a contact in SlickPay
     */
    public function createContact(User $user): array
    {
        $nameParts = explode(' ', $user->name ?? 'Guest User', 2);
        $firstName = $nameParts[0] ?? 'Guest';
        $lastName = $nameParts[1] ?? 'User';

        // Generate a random RIB (20 digits) for the contact
        $rib = '007' . str_pad(mt_rand(1, 99999999999999999), 17, '0', STR_PAD_LEFT);

        $data = [
            'firstname' => $firstName,
            'lastname' => $lastName,
            'email' => $user->email ?? 'guest_' . time() . '@hajz.dz',
            'phone' => $user->phone ?? '',
            'address' => 'Algeria',
            'rib' => $rib,
            'account' => $rib,
            'title' => 'M.',
        ];

        $response = $this->request('POST', 'users/contacts', $data);

        if ($response['success'] && isset($response['data']['uuid'])) {
            $user->update(['slickpay_contact_uuid' => $response['data']['uuid']]);
        }

        return $response;
    }

    /**
     * Get or create contact for a user
     */
    public function getOrCreateContact(User $user): ?string
    {
        if ($user->slickpay_contact_uuid) {
            return $user->slickpay_contact_uuid;
        }

        $response = $this->createContact($user);

        if ($response['success'] && isset($response['data']['uuid'])) {
            return $response['data']['uuid'];
        }

        return null;
    }

    /**
     * Create a contact for guest bookings
     */
    public function createGuestContact(string $name, string $email, string $phone = ''): array
    {
        $nameParts = explode(' ', $name, 2);
        $firstName = $nameParts[0] ?? 'Guest';
        $lastName = $nameParts[1] ?? 'User';

        $rib = '007' . str_pad(mt_rand(1, 99999999999999999), 17, '0', STR_PAD_LEFT);

        $data = [
            'firstname' => $firstName,
            'lastname' => $lastName,
            'email' => $email,
            'phone' => $phone,
            'address' => 'Algeria',
            'rib' => $rib,
            'account' => $rib,
            'title' => 'M.',
        ];

        return $this->request('POST', 'users/contacts', $data);
    }

    /**
     * Create an invoice for payment
     */
    public function createInvoice(array $params): array
    {
        $data = [
            'amount' => $params['amount'],
            'contact' => $params['contact_uuid'],
            'url' => $params['callback_url'],
            'items' => $params['items'] ?? [
                [
                    'name' => $params['description'] ?? 'Hajz Booking',
                    'quantity' => 1,
                    'price' => $params['amount'],
                ]
            ],
        ];

        // Add account/RIB if configured (otherwise SlickPay uses account default)
        $account = $params['account'] ?? config('slickpay.merchant_account');
        if (!empty($account)) {
            $data['account'] = $account;
        }

        return $this->request('POST', 'users/invoices', $data);
    }

    /**
     * Get invoice details
     */
    public function getInvoice(string $invoiceId): array
    {
        return $this->request('GET', "users/invoices/{$invoiceId}");
    }

    /**
     * Verify payment status
     */
    public function verifyPayment(Payment $payment): array
    {
        if (!$payment->slickpay_invoice_id) {
            return [
                'success' => false,
                'completed' => false,
                'message' => 'No SlickPay invoice ID found',
            ];
        }

        $response = $this->getInvoice($payment->slickpay_invoice_id);

        if (!$response['success']) {
            return [
                'success' => false,
                'completed' => false,
                'message' => $response['message'] ?? 'Failed to retrieve invoice',
            ];
        }

        $data = $response['data'];

        // Check various status fields
        $isCompleted = $this->checkPaymentStatus($data);
        $isFailed = $this->checkPaymentFailed($data);

        if ($isCompleted) {
            $payment->update([
                'status' => 'paid',
                'slickpay_response' => $data,
                'paid_at' => now(),
            ]);

            return [
                'success' => true,
                'completed' => true,
                'message' => 'Payment completed successfully',
                'data' => $data,
            ];
        }

        if ($isFailed) {
            $payment->update([
                'status' => 'failed',
                'slickpay_response' => $data,
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
     * Check if payment is completed based on SlickPay response
     */
    protected function checkPaymentStatus(array $data): bool
    {
        $completedStatuses = ['paid', 'completed', 'payé', '1', 'true', 1, true];

        // Check direct fields
        foreach (['status', 'completed', 'pay_status'] as $field) {
            if (isset($data[$field]) && in_array($data[$field], $completedStatuses, false)) {
                return true;
            }
        }

        // Check nested in invoice
        if (isset($data['invoice'])) {
            foreach (['status', 'completed', 'pay_status'] as $field) {
                if (isset($data['invoice'][$field]) && in_array($data['invoice'][$field], $completedStatuses, false)) {
                    return true;
                }
            }
        }

        // Check nested in user_invoice
        if (isset($data['user_invoice'])) {
            foreach (['status', 'completed', 'pay_status'] as $field) {
                if (isset($data['user_invoice'][$field]) && in_array($data['user_invoice'][$field], $completedStatuses, false)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if payment has failed
     */
    protected function checkPaymentFailed(array $data): bool
    {
        $failedStatuses = ['failed', 'cancelled', 'expired', 'annulé', 'expiré', 'rejected'];

        foreach (['status', 'pay_status'] as $field) {
            if (isset($data[$field]) && in_array(strtolower($data[$field]), $failedStatuses, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Initiate payment for a reservation
     */
    public function initiatePayment(array $params): array
    {
        $contactUuid = $params['contact_uuid'] ?? null;

        // Get or create contact
        if (!$contactUuid && isset($params['user'])) {
            $contactUuid = $this->getOrCreateContact($params['user']);
        }

        // For guest bookings, create a temporary contact
        if (!$contactUuid && isset($params['guest_name'], $params['guest_email'])) {
            $contactResponse = $this->createGuestContact(
                $params['guest_name'],
                $params['guest_email'],
                $params['guest_phone'] ?? ''
            );

            if ($contactResponse['success']) {
                $contactUuid = $contactResponse['data']['uuid'] ?? null;
            }
        }

        if (!$contactUuid) {
            return [
                'success' => false,
                'message' => 'Failed to create payment contact',
            ];
        }

        // Build callback URL with payment reference
        $callbackUrl = url($this->callbackUrl) . '?payment_id=' . ($params['payment_id'] ?? '');

        // Create invoice
        $invoiceResponse = $this->createInvoice([
            'amount' => $params['amount'],
            'contact_uuid' => $contactUuid,
            'callback_url' => $callbackUrl,
            'description' => $params['description'] ?? 'Hajz Booking',
            'items' => $params['items'] ?? null,
        ]);

        if (!$invoiceResponse['success']) {
            return [
                'success' => false,
                'message' => $invoiceResponse['message'] ?? 'Failed to create invoice',
                'errors' => $invoiceResponse['errors'] ?? [],
            ];
        }

        $invoiceData = $invoiceResponse['data'];

        return [
            'success' => true,
            'invoice_id' => $invoiceData['uuid'] ?? $invoiceData['id'] ?? null,
            'payment_url' => $invoiceData['url'] ?? null,
            'data' => $invoiceData,
        ];
    }

    /**
     * Get payment iframe URL
     */
    public function getPaymentIframeUrl(string $invoiceUuid): string
    {
        $baseUrl = $this->sandbox
            ? 'https://devapi.slick-pay.com'
            : 'https://prodapi.slick-pay.com';

        return "{$baseUrl}/api/v2/iframe?uuid={$invoiceUuid}";
    }

    /**
     * Calculate commission for an amount
     */
    public function calculateCommission(float $amount): array
    {
        // SlickPay commission calculation (approximate - check actual rates)
        $commissionRate = 0.02; // 2%
        $commission = round($amount * $commissionRate, 2);

        return [
            'amount' => $amount,
            'commission' => $commission,
            'total' => $amount + $commission,
        ];
    }
}
