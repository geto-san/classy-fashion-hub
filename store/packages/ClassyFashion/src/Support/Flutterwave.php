<?php

namespace ClassyFashion\Support;

use Illuminate\Support\Facades\Http;

/**
 * Minimal Flutterwave v3 client (sandbox-capable).
 *
 * Test mode is selected by the keys themselves; no separate base URL.
 * Never logs secrets.
 */
class Flutterwave
{
    protected const BASE = 'https://api.flutterwave.com/v3';

    public static function secretKey(): ?string
    {
        return env('FLUTTERWAVE_SECRET_KEY') ?: null;
    }

    public static function secretHash(): ?string
    {
        return env('FLUTTERWAVE_SECRET_HASH') ?: null;
    }

    protected static function client()
    {
        return Http::baseUrl(self::BASE)
            ->withToken((string) self::secretKey())
            ->acceptJson()
            ->timeout(30);
    }

    /**
     * Initiate a Ugandan mobile-money charge (MTN / AIRTEL).
     */
    public static function chargeUgandaMobileMoney(array $payload): array
    {
        $response = self::client()->post('/charges?type=mobile_money_uganda', $payload);

        return [
            'ok'   => $response->successful(),
            'data' => $response->json(),
        ];
    }

    /**
     * Server-side verification of a transaction id. The ONLY proof of payment.
     */
    public static function verifyTransaction(string $transactionId): array
    {
        $response = self::client()->get("/transactions/{$transactionId}/verify");

        return [
            'ok'   => $response->successful(),
            'data' => $response->json(),
        ];
    }

    /**
     * Webhook authenticity: verif-hash header must equal our secret hash.
     */
    public static function webhookSignatureValid(?string $header): bool
    {
        $expected = (string) self::secretHash();

        return $header !== null
            && $expected !== ''
            && hash_equals($expected, $header);
    }
}
