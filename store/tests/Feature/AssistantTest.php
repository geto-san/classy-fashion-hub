<?php

use ClassyFashion\Support\Recommender;
use Illuminate\Support\Facades\Http;
use Webkul\Customer\Models\Customer;
use Webkul\Product\Models\Product;

use function Pest\Laravel\get;
use function Pest\Laravel\postJson;

it('opens the assistant page', function () {
    get(route('classy.assistant.index'))
        ->assertOk()
        ->assertSee('Shop Assistant', false);
});

it('answers delivery and payment questions without any key', function () {
    config(['classy.ai.api_key' => '']);

    postJson(route('classy.assistant.chat'), ['message' => 'How much is delivery to Kampala?'])
        ->assertOk()
        ->assertSee('flat UGX 5,000', false);

    postJson(route('classy.assistant.chat'), ['message' => 'Can I pay with Airtel money?'])
        ->assertOk()
        ->assertSee('Airtel', false);
});

it('finds products by colour, category and UGX ceiling', function () {
    config(['classy.ai.api_key' => '']);

    $response = postJson(route('classy.assistant.chat'), ['message' => 'green dresses under 100000'])
        ->assertOk();

    $items = $response->json('products');

    expect($items)->not->toBeEmpty();

    foreach ($items as $item) {
        expect($item['price'])->toContain('UGX')
            ->and($item['url'])->toStartWith('http');
    }
});

it('tracks orders for customers and guides guests', function () {
    $customer = Customer::where('email', 'customer@classy.local')->firstOrFail();

    $this->actingAs($customer);

    postJson(route('classy.assistant.chat'), ['message' => 'where is my order'])
        ->assertOk()
        ->assertSee('Your latest order', false);
});

it('uses the LLM for open questions when keys exist', function () {
    config(['classy.ai.api_key' => 'test-key-123']);

    Http::fake([
        'api.groq.com/openai/v1/chat/completions*' => Http::response([
            'choices' => [['message' => ['content' => 'Yes — we gift wrap for UGX 2,000.']]],
        ]),
    ]);

    postJson(route('classy.assistant.chat'), ['message' => 'do you gift wrap'])
        ->assertOk()
        ->assertSee('gift wrap', false);

    config(['classy.ai.api_key' => '']);
});

it('falls back gracefully when the LLM fails', function () {
    config(['classy.ai.api_key' => 'test-key-123']);

    Http::fake([
        'api.groq.com/openai/v1/chat/completions*' => Http::response([], 500),
    ]);

    postJson(route('classy.assistant.chat'), ['message' => 'do you gift wrap'])
        ->assertOk()
        ->assertSee('I can help with products', false);

    config(['classy.ai.api_key' => '']);
});

it('rate-limits the chat per day', function () {
    config(['classy.ai.daily_limit' => 2]);

    postJson(route('classy.assistant.chat'), ['message' => 'hi'])->assertOk();
    postJson(route('classy.assistant.chat'), ['message' => 'hi'])->assertOk();
    postJson(route('classy.assistant.chat'), ['message' => 'hi'])->assertStatus(429);

    config(['classy.ai.daily_limit' => 200]);
});

it('recommends sellable picks excluding the product itself', function () {
    $product = Product::where('type', 'configurable')->firstOrFail();

    $picks = Recommender::picksFor($product, 4);

    expect($picks)->not->toBeEmpty()
        ->and($picks->pluck('id'))->not->toContain($product->id);

    foreach ($picks as $pick) {
        expect((float) $pick->price)->toBeGreaterThan(0);
    }
});

it('shows recommendations on the product page', function () {
    $product = Product::where('type', 'configurable')->firstOrFail();

    $urlKey = DB::table('product_attribute_values as av')
        ->join('attributes as a', 'a.id', '=', 'av.attribute_id')
        ->where('av.product_id', $product->id)
        ->where('a.code', 'url_key')
        ->value('av.text_value');

    get('/'.$urlKey)
        ->assertOk()
        ->assertSee('You may also like', false);
});

it('fills missing descriptions without a key', function () {
    $product = Product::factory()->create(['sku' => 'AI-TEST-'.fake()->unique()->numberBetween(1000, 9999)]);

    $this->artisan('classy:describe-products', ['--limit' => 200])
        ->assertSuccessful();

    $text = DB::table('product_attribute_values as av')
        ->join('attributes as a', 'a.id', '=', 'av.attribute_id')
        ->where('av.product_id', $product->id)
        ->where('a.code', 'description')
        ->value('av.text_value');

    expect($text)->toContain('Classy Fashion Hub');
});
