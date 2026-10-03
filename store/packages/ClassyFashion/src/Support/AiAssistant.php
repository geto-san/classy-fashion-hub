<?php

namespace ClassyFashion\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Minimal OpenAI-compatible chat client for free-tier LLM providers.
 *
 * Defaults to Groq's free tier (llama model); any OpenAI-compatible
 * endpoint works via AI_LLM_* env keys (OpenRouter free models, etc).
 * All failures degrade gracefully: callers must always have a
 * rule-based fallback so the shop works with no key at all.
 * Never logs keys or customer data.
 */
class AiAssistant
{
    public static function enabled(): bool
    {
        return filled(env('AI_LLM_API_KEY'));
    }

    public static function baseUrl(): string
    {
        return rtrim((string) (env('AI_LLM_BASE_URL') ?: 'https://api.groq.com/openai/v1'), '/');
    }

    public static function model(): string
    {
        return (string) (env('AI_LLM_MODEL') ?: 'llama-3.3-70b-versatile');
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
            $response = Http::baseUrl(self::baseUrl())
                ->withToken((string) env('AI_LLM_API_KEY'))
                ->acceptJson()
                ->timeout((int) (env('AI_LLM_TIMEOUT', 20)))
                ->post('/chat/completions', [
                    'model'       => self::model(),
                    'messages'    => $messages,
                    'max_tokens'  => $maxTokens,
                    'temperature' => 0.3,
                ]);

            if (! $response->successful()) {
                Log::warning('classy.ai.llm_failed', ['status' => $response->status()]);

                return null;
            }

            $text = trim((string) ($response->json('choices.0.message.content') ?? ''));

            return $text === '' ? null : $text;
        } catch (\Throwable $e) {
            Log::warning('classy.ai.llm_error', ['error' => get_class($e)]);

            return null;
        }
    }
}
