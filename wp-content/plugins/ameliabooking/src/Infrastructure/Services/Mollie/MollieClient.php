<?php

/**
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Infrastructure\Services\Mollie;

/**
 * Class MollieClient
 *
 * @package AmeliaBooking\Infrastructure\Services\Mollie
 */
class MollieClient
{
    private const BASE_URL = 'https://api.mollie.com/v2';

    /**
     * @var string
     */
    private string $apiKey;

    /**
     * MollieClient constructor.
     */
    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
    }

    /**
     * Create a new payment.
     *
     * @throws \RuntimeException On cURL or JSON error.
     */
    public function createPayment(array $data): array
    {
        return $this->request('POST', '/payments', $data);
    }

    /**
     * Retrieve an existing payment.
     *
     * @throws \RuntimeException On cURL or JSON error.
     */
    public function getPayment(string $id): array
    {
        return $this->request('GET', '/payments/' . urlencode($id));
    }

    /**
     * Create a payment link.
     *
     * @throws \RuntimeException On cURL or JSON error.
     */
    public function createPaymentLink(array $data): array
    {
        return $this->request('POST', '/payment-links', $data);
    }

    /**
     * Retrieve an existing payment link.
     *
     * @throws \RuntimeException On cURL or JSON error.
     */
    public function getPaymentLink(string $id): array
    {
        return $this->request('GET', '/payment-links/' . urlencode($id));
    }

    /**
     * Create a refund for a payment.
     *
     * @throws \RuntimeException On cURL or JSON error.
     */
    public function createRefund(string $paymentId, array $data): array
    {
        return $this->request('POST', '/payments/' . urlencode($paymentId) . '/refunds', $data);
    }

    /**
     * Convenience helper: fetch the `status` string of a payment.
     */
    public function getPaymentStatus(string $id): ?string
    {
        $response = $this->getPayment($id);

        return $response['status'] ?? null;
    }

    /**
     * Send an HTTP request to the Mollie API.
     *
     * @throws \RuntimeException On cURL failure or invalid JSON.
     */
    private function request(string $method, string $endpoint, array $body = []): array
    {
        $ch = curl_init();

        $options = [
            CURLOPT_URL            => self::BASE_URL . $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
        ];

        if ($method !== 'GET' && !empty($body)) {
            $options[CURLOPT_POSTFIELDS] = json_encode($body);
        }

        curl_setopt_array($ch, $options);

        $raw      = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);

        if ($curlError) {
            throw new \RuntimeException('Mollie API cURL error: ' . $curlError);
        }

        $decoded = json_decode($raw, true);

        if (!is_array($decoded)) {
            throw new \RuntimeException(
                'Invalid Mollie API response (HTTP ' . $httpCode . '): ' . substr((string)$raw, 0, 200)
            );
        }

        $decoded['_http_code'] = $httpCode;

        return $decoded;
    }
}
