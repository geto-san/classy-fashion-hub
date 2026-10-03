<?php

namespace ClassyFashion\Http\Controllers\Shop;

use ClassyFashion\Support\ShopAssistant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Webkul\Shop\Http\Controllers\Controller;

/**
 * AI shop assistant (report 5.1): chat page + JSON API.
 *
 * Guarded by route throttling plus a daily cap so free-tier LLM quotas
 * (and the server) survive traffic spikes. The knowledge base answers
 * without any key; the LLM only polishes open questions.
 */
class AssistantController extends Controller
{
    public function index()
    {
        return view('classy-fashion::shop.assistant.index');
    }

    public function chat(Request $request)
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:500'],
        ]);

        $key = 'classy-assistant:'.($request->user('customer')?->id ?? $request->ip());

        $limit = (int) (env('AI_ASSISTANT_DAILY_LIMIT', 200));

        if (RateLimiter::tooManyAttempts($key, $limit)) {
            return response()->json([
                'reply'    => 'You have reached today’s assistant limit. Please try again tomorrow — or browse the catalogue.',
                'products' => [],
            ], 429);
        }

        RateLimiter::hit($key, 86400);

        $answer = ShopAssistant::answer(
            $validated['message'],
            $request->user('customer')
        );

        return response()->json($answer);
    }

    public static function routes(): void
    {
        Route::middleware('web')->prefix('assistant')->name('classy.assistant.')->group(function () {
            Route::get('', [self::class, 'index'])->name('index');

            Route::post('chat', [self::class, 'chat'])
                ->middleware('throttle:30,1')
                ->name('chat');
        });
    }
}
