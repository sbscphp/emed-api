<?php

namespace App\Services\Payment;

use Exception;

/**
 * Thrown when Paystack refuses a call or cannot be reached.
 *
 * Carries the message Paystack gave, which is usually specific enough to act on
 * ("Invalid account number", "Subaccount not found"), and separately the raw
 * payload for the log. Callers decide whether the patient sees the gateway's
 * wording or something gentler.
 */
class PaystackException extends Exception
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(string $message, protected array $payload = [], int $code = 502)
    {
        parent::__construct($message, $code);
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->payload;
    }

    /**
     * Whether Paystack is saying it has never heard of the thing we named.
     *
     * The case that matters is a subaccount created under one integration and
     * addressed with another's keys: the code is well formed and stored, and
     * Paystack simply does not know it. That is recoverable — a new one can be
     * created — where every other rejection is not, so the two are told apart
     * here rather than at each call site.
     */
    public function isNotFound(): bool
    {
        if (($this->payload['code'] ?? null) === 'not_found') {
            return true;
        }

        $message = strtolower($this->payload['message'] ?? $this->getMessage());

        return str_contains($message, 'not found')
            || str_contains($message, 'invalid subaccount');
    }
}
