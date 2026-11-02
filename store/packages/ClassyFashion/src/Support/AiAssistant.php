<?php

namespace ClassyFashion\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Server-side AI client with a safe null fallback and no customer-data logging.
 */
class AiAssistant
{
    public static function enabled(): bool
    {
        return filled(config('classy.ai.api_key'));
    }

    public static function baseUrl(): string
    {
        return rtrim((string) (config('classy.ai.base_url') ?: 'https://api.groq.com/openai/v1'), '/');
    }

    public static function model(): string
    {
        return (string) (config('classy.ai.model') ?: 'llama-3.3-70b-versatile');
    }

    /**
     * Chat completion. Returns the assistant text or null on any failure.
     */
    public static function chat(array $messages, int $maxTokens = 400): ?string
    {
        if (! self::enabled()) {
            return null;
        }

        try {
            $openai = self::baseUrl() === 'https://api.openai.com/v1';

            $payload = $openai ? [
                'model' => self::model(),
                'input' => $messages,
                'max_output_tokens' => $maxTokens,
                'store' => false,
            ] : [
                'model' => self::model(),
                'messages' => $messages,
                'max_tokens' => $maxTokens,
                'temperature' => 0.3,
            ];

            $response = Http::baseUrl(self::baseUrl())
                ->withToken((string) config('classy.ai.api_key'))
                ->acceptJson()
                ->timeout((int) config('classy.ai.timeout', 20))
                ->post($openai ? '/responses' : '/chat/completions', $payload);

            if (! $response->successful()) {
                Log::warning('classy.ai.llm_failed', ['status' => $response->status()]);

                return null;
            }

            $text = $openai
                ? collect($response->json('output', []))
                    ->where('type', 'message')
                    ->flatMap(fn ($item) => $item['content'] ?? [])
                    ->where('type', 'output_text')
                    ->pluck('text')
                    ->implode("\n")
                : (string) ($response->json('choices.0.message.content') ?? '');

            $text = trim($text);

            return $text === '' ? null : $text;
        } catch (\Throwable $e) {
            Log::warning('classy.ai.llm_error', ['error' => get_class($e)]);

            return null;
        }
    }
}
