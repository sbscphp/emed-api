<?php

namespace App\Services\Payment;

use Illuminate\Support\Facades\Http;

/**
 * Thin client around the Paystack REST API. Only the two calls this feature needs
 * are exposed: initialize (start a card charge) and verify (confirm it).
 */
class PaystackService
{
    private string $baseUrl;
    private ?string $secret;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.paystack.base_url', 'https://api.paystack.co'), '/');
        $this->secret  = config('services.paystack.secret');
    }

    /**
     * Start a transaction. Amount is in naira and converted to kobo for Paystack.
     *
     * @return array{authorization_url:string, access_code:string, reference:string}
     */
    public function initialize(string $email, float $amountNaira, string $reference, ?string $callbackUrl = null, array $metadata = []): array
    {
        $payload = [
            'email'     => $email,
            'amount'    => (int) round($amountNaira * 100), // kobo
            'reference' => $reference,
            'metadata'  => $metadata,
        ];

        if ($callbackUrl) {
            $payload['callback_url'] = $callbackUrl;
        }

        $response = Http::withToken($this->secret)
            ->acceptJson()
            ->post("{$this->baseUrl}/transaction/initialize", $payload);

        if (!$response->successful() || !($response->json('status') === true)) {
            throw new \RuntimeException($response->json('message') ?? 'Unable to initialize payment.');
        }

        return $response->json('data');
    }

    /**
     * Verify a transaction by reference. Returns the Paystack `data` object which
     * includes `status` ('success'), `amount` (kobo), and `reference`.
     */
    public function verify(string $reference): array
    {
        $response = Http::withToken($this->secret)
            ->acceptJson()
            ->get("{$this->baseUrl}/transaction/verify/" . rawurlencode($reference));

        if (!$response->successful() || !($response->json('status') === true)) {
            throw new \RuntimeException($response->json('message') ?? 'Unable to verify payment.');
        }

        return $response->json('data');
    }
}
