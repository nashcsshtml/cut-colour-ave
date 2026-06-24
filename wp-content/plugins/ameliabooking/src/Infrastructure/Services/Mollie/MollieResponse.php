<?php

/**
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Infrastructure\Services\Mollie;

/**
 * Class MollieResponse
 *
 * @package AmeliaBooking\Infrastructure\Services\Mollie
 */
class MollieResponse
{
    /**
     * HTTP status codes that indicate a successful API call.
     */
    private const SUCCESS_CODES = [200, 201, 204];

    /**
     * @var array
     */
    private array $data;

    /**
     * @var int
     */
    private int $httpCode;

    /**
     * MollieResponse constructor.
     */
    public function __construct(array $data)
    {
        $this->data     = $data;
        $this->httpCode = isset($data['_http_code']) ? (int)$data['_http_code'] : 0;
    }

    /**
     * Whether the API call was successful.
     */
    public function isSuccessful(): bool
    {
        return in_array($this->httpCode, self::SUCCESS_CODES, true);
    }

    /**
     * Return the HTTP status code.
     */
    public function getCode(): int
    {
        return $this->httpCode;
    }

    /**
     * True when the payment has a hosted checkout URL to redirect to.
     */
    public function isRedirect(): bool
    {
        return !empty($this->data['_links']['checkout']['href']);
    }

    /**
     * The Mollie hosted checkout URL.
     */
    public function getRedirectUrl(): string
    {
        return $this->data['_links']['checkout']['href'] ?? '';
    }

    /**
     * Payment status string returned by Mollie (e.g. "open", "paid", "failed").
     */
    public function getStatus(): string
    {
        $status = $this->data['status'] ?? '';

        return is_string($status) ? $status : (string)$status;
    }

    /**
     * Return a human-readable error message from the response, if any.
     */
    public function getMessage(): string
    {
        if (!empty($this->data['detail'])) {
            return $this->data['detail'];
        }

        return $this->data['title'] ?? $this->data['message'] ?? '';
    }

    /**
     * Return the raw Mollie API response array (includes `_http_code`).
     */
    public function getData(): array
    {
        return $this->data;
    }
}
