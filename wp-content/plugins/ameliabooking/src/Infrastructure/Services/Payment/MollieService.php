<?php

/**
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Infrastructure\Services\Payment;

use AmeliaBooking\Domain\Services\Payment\AbstractPaymentService;
use AmeliaBooking\Domain\Services\Payment\PaymentServiceInterface;
use AmeliaBooking\Infrastructure\Services\Mollie\MollieClient;
use AmeliaBooking\Infrastructure\Services\Mollie\MollieResponse;

/**
 * Class MollieService
 *
 * All Mollie communication goes through MollieClient which uses a single
 * direct HTTP call per operation (no Omnipay dependency).
 * The Mollie settings are resolved once per request and cached in
 * $paymentsSettings so getCategorySettings() is called at most once.
 *
 * @package AmeliaBooking\Infrastructure\Services\Payment
 */
class MollieService extends AbstractPaymentService implements PaymentServiceInterface
{
    /** @var array|null Cached `payments` settings category. */
    private $paymentsSettings = null;

    /**
     * Lazy-load and return the full `payments` settings array.
     *
     * @return array
     */
    private function getPaymentsSettings(): array
    {
        if ($this->paymentsSettings === null) {
            $this->paymentsSettings = $this->settingsService->getCategorySettings('payments');
        }

        return $this->paymentsSettings;
    }

    /**
     * Return the active Mollie API key (test or live) from cached settings.
     *
     * @return string
     */
    private function getApiKey(): string
    {
        $mollie = $this->getPaymentsSettings()['mollie'] ?? [];

        if (empty($mollie['testApiKey']) && empty($mollie['liveApiKey'])) {
            throw new \RuntimeException('Mollie API key not configured');
        }

        return !empty($mollie['testMode']) ? $mollie['testApiKey'] : $mollie['liveApiKey'];
    }

    /**
     * Return a configured MollieClient for the current API key.
     *
     * @return MollieClient
     */
    private function getClient(): MollieClient
    {
        return new MollieClient($this->getApiKey());
    }

    /**
     * Create a new Mollie payment and return a response object.
     *
     * On success, $response->isRedirect() is true and
     * $response->getRedirectUrl() returns the checkout URL.
     *
     * @param array $data      Payment parameters (returnUrl, notifyUrl, amount, ...).
     * @param array $transfers Unused for Mollie; kept for interface compatibility.
     *
     * @return MollieResponse
     * @throws \RuntimeException
     */
    public function execute($data, &$transfers): MollieResponse
    {
        $payload = [
            'amount'      => [
                'value'    => number_format((float)$data['amount'], 2, '.', ''),
                'currency' => $this->getPaymentsSettings()['currency'],
            ],
            'redirectUrl' => $data['returnUrl'],
            'webhookUrl'  => $data['notifyUrl'],
        ];

        if (!empty($data['description'])) {
            $payload['description'] = $data['description'];
        }

        if (!empty($data['metaData'])) {
            $payload['metadata'] = $data['metaData'];
        }

        if (!empty($data['method'])) {
            $payload['method'] = $data['method'];
        }

        return new MollieResponse($this->getClient()->createPayment($payload));
    }

    /**
     * Fetch an existing Mollie payment and wrap it in a MollieResponse.
     *
     * Callers use $response->getStatus() to read the payment status.
     *
     * @param array $data Must contain `id` (Mollie transaction reference).
     *
     * @return MollieResponse
     * @throws \RuntimeException
     */
    public function fetchPayment(array $data): MollieResponse
    {
        return new MollieResponse($this->getClient()->getPayment($data['id']));
    }

    /**
     * Create a Mollie payment (not a payment-link) and return a normalised result array.
     *
     * We intentionally use POST /v2/payments here rather than /v2/payment-links
     * because Mollie test-mode payment-links require Mollie dashboard authentication
     * to view their checkout page, while regular test payments at
     * checkout.mollie.com/pay/test/xxx are publicly accessible.
     * The webhook and redirect behaviour is identical between the two resource types.
     *
     * On success: ['link' => 'https://checkout.mollie.com/pay/...', 'status' => 200]
     * On failure: ['message' => '...', 'status' => <http_code>]
     *
     * @param array $data Payment body (amount, description, redirectUrl, webhookUrl).
     *
     * @return array
     */
    public function getPaymentLink($data): array
    {
        $response = $this->getClient()->createPayment([
            'amount'      => $data['amount'],
            'description' => $data['description'] ?? '',
            'redirectUrl' => $data['redirectUrl'],
            'webhookUrl'  => $data['webhookUrl'],
        ]);

        // Regular Mollie payments expose the checkout URL at _links.checkout.href
        if (!empty($response['_links']['checkout']['href'])) {
            return [
                'link'   => $response['_links']['checkout']['href'],
                'status' => 200,
            ];
        }

        return [
            'message' => $response['detail'] ?? $response['title'] ?? $response['message'] ?? 'Unknown error',
            'status'  => (int)($response['_http_code'] ?? 500),
        ];
    }

    /**
     * Retrieve the details of a Mollie payment link by ID.
     *
     * @param string $id Payment-link ID (e.g. pl_ZtVHNuxWLs).
     *
     * @return array|null Decoded response or null on empty result.
     */
    public function fetchPaymentLink($id): ?array
    {
        $response = $this->getClient()->getPaymentLink($id);

        return !empty($response) ? $response : null;
    }

    /**
     * Issue a refund for a Mollie payment.
     *
     * Returns ['error' => false] on success or ['error' => '<message>'] on
     * failure, matching the interface expected by RefundPaymentCommandHandler.
     *
     * @param array $data Must contain `id`; optionally `amount`.
     *
     * @return array
     * @throws \RuntimeException
     */
    public function refund($data): array
    {
        $amount = !empty($data['amount'])
            ? $data['amount']
            : $this->getTransactionAmount($data['id'], null);

        if ($amount === null) {
            return ['error' => 'Unable to determine refund amount'];
        }

        $payload = [
            'amount' => [
                'value'    => number_format((float)$amount, 2, '.', ''),
                'currency' => $this->getPaymentsSettings()['currency'],
            ],
        ];

        $response = $this->getClient()->createRefund($data['id'], $payload);

        $httpCode = $response['_http_code'] ?? 0;

        return [
            'error' => ($httpCode >= 200 && $httpCode < 300)
                ? false
                : ($response['detail'] ?? $response['title'] ?? $response['message'] ?? 'Refund failed'),
        ];
    }

    /**
     * Return the charged amount value for a Mollie payment.
     *
     * @param string     $id        Mollie payment ID.
     * @param array|null $transfers Unused; kept for interface compatibility.
     *
     * @return string|null Amount string (e.g. "10.00") or null on error.
     * @throws \RuntimeException
     */
    public function getTransactionAmount($id, $transfers): ?string
    {
        $response = $this->getClient()->getPayment($id);

        return !empty($response['amount']['value']) ? $response['amount']['value'] : null;
    }

    /**
     * Validates a Mollie API key by checking its format and making a test API call.
     */
    public function validateKey(string $apiKey, bool $testMode): array
    {
        $expectedPrefix = $testMode ? 'test_' : 'live_';

        if (strpos($apiKey, $expectedPrefix) !== 0) {
            return ['valid' => false, 'message' => 'Invalid Mollie API key.'];
        }

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL            => 'https://api.mollie.com/v2/methods',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $apiKey],
        ]);

        curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($httpCode === 200) {
            return ['valid' => true, 'message' => 'Mollie API key is valid'];
        }

        return ['valid' => false, 'message' => 'Invalid Mollie API key.'];
    }
}
