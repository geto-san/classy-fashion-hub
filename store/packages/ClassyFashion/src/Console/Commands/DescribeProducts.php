<?php

namespace ClassyFashion\Console\Commands;

use ClassyFashion\Support\AiAssistant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Fill missing product descriptions (report 5.1, automated content).
 *
 * Uses the LLM when keys exist, otherwise a catalog-aware template —
 * so it works fully offline. --refresh regenerates everything but
 * requires the LLM (never overwrites curated text with templates).
 *
 * Usage: php artisan classy:describe-products [--refresh] [--limit=20]
 */
class DescribeProducts extends Command
{
    protected $signature = 'classy:describe-products {--refresh : Regenerate all descriptions via LLM} {--limit=50 : Max products}';

    protected $description = 'Generate missing product descriptions (LLM when configured, template fallback).';

    public function handle(): int
    {
        $ids = DB::table('products')->whereNull('parent_id')->limit((int) $this->option('limit'))->pluck('id');

        $filled = 0;

        foreach ($ids as $id) {
            foreach (['short_description', 'description'] as $code) {
                $exists = DB::table('product_attribute_values as av')
                    ->join('attributes as a', 'a.id', '=', 'av.attribute_id')
                    ->where('av.product_id', $id)
                    ->where('a.code', $code)
                    ->whereNotNull('av.text_value')
                    ->where('av.text_value', '!=', '')
                    ->exists();

                if ($exists && ! $this->option('refresh')) {
                    continue;
                }

                $text = $this->describe($id, $code);

                if ($text === null) {
                    continue;
                }

                $attributeId = DB::table('attributes')->where('code', $code)->value('id');

                DB::table('product_attribute_values')->updateOrInsert(
                    ['product_id' => $id, 'attribute_id' => $attributeId, 'locale' => 'en', 'channel' => null],
                    ['text_value' => $text]
                );

                $filled++;
            }
        }

        $this->info("Descriptions written: {$filled}.");

        return self::SUCCESS;
    }

    protected function describe(int $productId, string $code): ?string
    {
        $name = DB::table('product_attribute_values as av')
            ->join('attributes as a', 'a.id', '=', 'av.attribute_id')
            ->where('av.product_id', $productId)
            ->where('a.code', 'name')
            ->value('av.text_value') ?? 'This item';

        if ($this->option('refresh')) {
            if (! AiAssistant::enabled()) {
                $this->warn('Refresh needs AI_LLM_API_KEY; skipping.');

                return null;
            }

            return AiAssistant::chat([
                ['role' => 'system', 'content' => 'Write a 2-sentence product description for a Ugandan online fashion shop. Plain words, mention the fixed UGX price only if given. No hype.'],
                ['role' => 'user', 'content' => "Product: {$name} ({$code})"],
            ], 150);
        }

        $price = DB::table('product_flat')->where('product_id', $productId)->value('price');

        $priceText = $price ? ' Fixed price UGX '.number_format((float) $price).'.' : '';

        if ($code === 'short_description') {
            return "{$name} from Classy Fashion Hub.{$priceText}";
        }

        return "{$name} from Classy Fashion Hub.{$priceText} Delivery across Uganda at UGX 5,000 — add gate or landmark notes at checkout.";
    }
}
