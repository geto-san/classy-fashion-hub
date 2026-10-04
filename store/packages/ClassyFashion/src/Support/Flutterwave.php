<?php

namespace ClassyFashion\Support;

use Illuminate\Support\Facades\Http;

/**
 * Minimal Flutterwave v3 client.
 *
 * Settings come from config('classy.flutterwave.*') (see Config/classy.php),
 * never read straight from the environment, so they keep working when the
 * config is cached.
 * Sandbox vs live is decided by FLUTTERWAVE_SANDBOX and enforced against the
 * key prefix: test keys with sandbox=false, or live keys with sandbox=true,
 * leave the gateway switched off rather than silently using the wrong mode.
 * Never logs secrets.
 */
class Flutterwave
{
    protected const BASE = 'https://api.flutterwave.com/v3';

    protected const TEST_KEY_PREFIX = 'FLWSECK_TEST';

    public static function secretKey(): ?string
    {
        return config('classy.flutterwave.secret_key') ?: null;
    }

    public static function publicKey(): ?string
    {
        return config('classy.flutterwave.public_key') ?: null;
    }

    public static function secretHash(): ?string
    {
        return config('classy.flutterwave.secret_hash') ?: null;
    }

    public static function sandbox(): bool
    {
        return (bool) config('classy.flutterwave.sandbox', true);
    }

    /**
     * Do the configured keys belong to the configured mode?
     */
    public static function keysMatchMode(): bool
    {
        $key = (string) self::secretKey();

        if ($key === '') {
            return false;
        }

        $isTestKey = str_starts_with($key, self::TEST_KEY_PREFIX);

        return self::sandbox() ? $isTestKey : ! $isTestKey;
    }

    /**
     * Ready to take payments: both keys present and matching the mode.
     */
    public static function configured(): bool
    {
        return filled(self::secretKey())
            && filled(self::publicKey())
            && self::keysMatchMode();
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
        if (! self::configured()) {
            return [
                'ok'   => false,
                'data' => ['message' => 'Gateway keys are missing or do not match FLUTTERWAVE_SANDBOX.'],
            ];
        }

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
