<?php

namespace Database\Seeders;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Http\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Webkul\Attribute\Models\AttributeOption;
use Webkul\Product\Models\Product;
use Webkul\Product\Repositories\ProductRepository;

/**
 * Classy Fashion Hub catalog seeder (Gap #2, #3, #7).
 *
 * - Adds UGX as the store currency and makes it the channel base currency.
 * - Removes installer demo products (only when no orders exist yet).
 * - Seeds 16 fashion products (configurable size/colour variants + simples)
 *   with fixed UGX prices, stock quantities and generated placeholder images.
 *
 * Run: php artisan db:seed --class='Database\Seeders\ClassyFashionCatalogSeeder'
 */
class ClassyFashionCatalogSeeder extends Seeder
{
    protected ProductRepository $products;

    protected int $inventorySourceId = 1;

    protected int $channelId = 1;

    protected string $locale = 'en';

    /**
     * [name, type, category_ids, price, qty_per_variant, sizes, colors]
     * sizes/colors are admin names of the size/color attribute options.
     */
    protected array $catalog = [
        ['Classic White Cotton Shirt', 'configurable', [2, 6], 45000, 25, ['S', 'M', 'L', 'XL'], ['White', 'Blue']],
        ['Ankara Print Shirt', 'configurable', [2, 6], 65000, 15, ['M', 'L', 'XL'], ['Red', 'Yellow', 'Green']],
        ['Denim Jacket', 'configurable', [2, 6], 120000, 12, ['M', 'L', 'XL'], ['Blue', 'Black']],
        ['Leather Jacket', 'configurable', [2], 250000, 8, ['M', 'L'], ['Black', 'Brown']],
        ['Gomesi Traditional Dress', 'configurable', [4], 150000, 10, ['M', 'L', 'XL'], ['Red', 'Purple', 'Pink']],
        ['Kitenge Wrap Dress', 'configurable', [4], 85000, 14, ['S', 'M', 'L'], ['Yellow', 'Green', 'Orange']],
        ["Men's Formal Shirt", 'configurable', [2, 5], 55000, 20, ['S', 'M', 'L', 'XL'], ['White', 'Blue']],
        ['Hooded Sweatshirt', 'configurable', [2, 6, 7], 75000, 18, ['M', 'L', 'XL'], ['Grey', 'Black', 'Blue']],
        ['Running Sneakers', 'configurable', [8], 135000, 16, [], ['White', 'Black', 'Red']],
        ['Leather Loafers', 'configurable', [8], 160000, 10, [], ['Black', 'Brown']],
        ['Leather Belt', 'simple', [2], 35000, 30, [], []],
        ['Silk Scarf', 'simple', [4], 25000, 40, [], []],
        ['Canvas Tote Bag', 'simple', [4, 6], 30000, 35, [], []],
        ['Baseball Cap', 'simple', [6], 20000, 50, [], []],
        ['Wool Beanie', 'simple', [6, 7], 18000, 45, [], []],
        ['Cotton Socks 3-Pack', 'simple', [2], 15000, 60, [], []],
    ];

    public function run(): void
    {
        $this->products = app(ProductRepository::class);

        $this->setupUgxCurrency();

        $this->wipeDemoProducts();

        foreach ($this->catalog as $item) {
            $this->createProduct(...$item);
        }

        $this->command->info('Classy Fashion Hub catalog seeded: '.count($this->catalog).' products in UGX.');
    }

    /**
     * Add UGX and make it the default channel base currency.
     */
    protected function setupUgxCurrency(): void
    {
        DB::table('currencies')->updateOrInsert(
            ['code' => 'UGX'],
            [
                'name'              => 'Ugandan Shilling',
                'symbol'            => 'USh',
                'decimal'           => 0,
                'currency_position' => 'left_with_space',
            ]
        );

        $ugxId = DB::table('currencies')->where('code', 'UGX')->value('id');

        DB::table('channels')->where('id', $this->channelId)->update(['base_currency_id' => $ugxId]);

        DB::table('core_config')->updateOrInsert(
            ['code' => 'catalog.inventory.stock_options.out_of_stock_threshold', 'channel_code' => null, 'locale_code' => null],
            ['value' => '5']
        );

        $this->command->info('Currency UGX ready (channel base currency). Low-stock threshold set to 5.');
    }

    /**
     * Remove installer demo products. Refuses when real orders exist.
     */
    protected function wipeDemoProducts(): void
    {
        if (DB::table('orders')->count() > 0) {
            $this->command->warn('Orders exist; demo products left untouched.');

            return;
        }

        $tables = [
            'product_attribute_values',
            'product_images',
            'product_inventories',
            'product_ordered_inventories',
            'product_inventory_indices',
            'product_price_indices',
            'product_flat',
            'product_super_attributes',
            'product_categories',
            'product_channels',
            'product_customer_group_prices',
            'product_up_sells',
            'product_cross_sells',
            'product_relations',
            'product_grouped_products',
            'product_reviews',
            'products',
        ];

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        foreach ($tables as $table) {
            DB::table($table)->truncate();
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->command->info('Demo products wiped.');
    }

    /**
     * Run repository calls under mass-assignment guarding.
     *
     * The db:seed command unguards all models, but the product repository
     * relies on guarded fills (as on the admin HTTP path) to drop
     * non-column keys such as super_attributes and variants.
     */
    protected function guarded(callable $callback): mixed
    {
        Model::reguard();

        try {
            return $callback();
        } finally {
            Model::unguard();
        }
    }

    protected function createProduct(
        string $name,
        string $type,
        array $categoryIds,
        int $price,
        int $qty,
        array $sizes,
        array $colors
    ): void {
        $sku = strtoupper(Str::slug($name, '')).'-'.strtoupper(Str::random(4));

        $optionId = fn (string $code, string $adminName): int => AttributeOption::query()
            ->whereHas('attribute', fn ($q) => $q->where('code', $code))
            ->where('admin_name', $adminName)
            ->value('id');

        $data = [
            'type'               => $type,
            'attribute_family_id' => 1,
            'sku'                => $sku,
        ];

        if ($type === 'configurable') {
            $superAttributes = [];

            if (! empty($colors)) {
                $superAttributes['color'] = array_map(fn ($c) => $optionId('color', $c), $colors);
            }

            if (! empty($sizes)) {
                $superAttributes['size'] = array_map(fn ($s) => $optionId('size', $s), $sizes);
            }

            $data['super_attributes'] = $superAttributes;
        }

        /** @var Product $product */
        $product = $this->guarded(fn () => $this->products->create($data));

        $payload = [
            'sku'                 => $sku,
            'name'                => $name,
            'url_key'             => Str::slug($name.' '.strtolower(Str::random(4))),
            'short_description'   => $name.' - Classy Fashion Hub.',
            'description'         => $name.' sold at a fixed price of USh '.number_format($price).'.',
            'price'               => $price,
            'cost'                => (int) round($price * 0.6),
            'weight'              => 1,
            'status'              => 1,
            'visible_individually' => 1,
            'categories'          => $categoryIds,
            'channels'            => [$this->channelId],
            'locale'              => $this->locale,
            'channel'             => 'default',
        ];

        if ($type === 'configurable') {
            $product->load('variants');

            $variants = [];

            foreach ($product->variants as $variant) {
                $variant->loadMissing('attribute_values');

                $row = [
                    'sku'         => $variant->sku,
                    'name'        => $name,
                    'price'       => $price,
                    'weight'      => 1,
                    'status'      => 1,
                    'inventories' => [$this->inventorySourceId => $qty],
                ];

                foreach (['color', 'size'] as $code) {
                    $av = $variant->attribute_values->firstWhere('attribute.code', $code);

                    if ($av) {
                        $row[$code] = $av->integer_value;
                    }
                }

                $variants[$variant->id] = $row;
            }

            $payload['variants'] = $variants;
        } else {
            $payload['inventories'] = [$this->inventorySourceId => $qty];
        }

        $product = $this->guarded(fn () => $this->products->update($payload, $product->id));

        $this->attachPlaceholderImage($product, $name);

        Event::dispatch('catalog.product.update.after', $product);

        $this->command->line("  + {$name} ({$type}, UGX ".number_format($price).')');
    }

    /**
     * Generate a branded placeholder PNG and link it to the product,
     * following the installer seeder storage pattern.
     */
    protected function attachPlaceholderImage(Product $product, string $name): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'classy').'.png';

        $this->makePlaceholder($tmp, $name);

        $path = Storage::putFile('product/'.$product->id, new File($tmp));

        @unlink($tmp);

        if ($path) {
            DB::table('product_images')->insert([
                'type'       => null,
                'path'       => $path,
                'product_id' => $product->id,
                'position'   => 0,
            ]);
        }
    }

    protected function makePlaceholder(string $path, string $text): void
    {
        $w = 800;
        $h = 1000;

        $img = imagecreatetruecolor($w, $h);

        imagefill($img, 0, 0, imagecolorallocate($img, 0x4A, 0x19, 0x42));

        imagefilledrectangle($img, 0, $h - 160, $w, $h, imagecolorallocate($img, 0xE8, 0xB8, 0x4B));

        $white = imagecolorallocate($img, 255, 255, 255);
        $gold = imagecolorallocate($img, 0x4A, 0x19, 0x42);

        $font = '/usr/share/fonts/TTF/DejaVuSansMNerdFontPropo-Bold.ttf';

        $label = 'Classy Fashion Hub';
        $words = explode(' ', $text);

        if (is_file($font)) {
            $size = 34;

            $tb = imagettfbbox($size, 0, $font, $text);
            $tw = $tb[2] - $tb[0];

            if ($tw > $w - 80) {
                $size = (int) ($size * ($w - 80) / $tw);
            }

            $y = 430;

            foreach (array_chunk($words, 2) as $line) {
                $line = implode(' ', $line);
                $tb = imagettfbbox($size, 0, $font, $line);
                imagettftext($img, $size, 0, (int) (($w - ($tb[2] - $tb[0])) / 2), $y, $white, $font, $line);
                $y += $size + 24;
            }

            $tb = imagettfbbox(24, 0, $font, $label);
            imagettftext($img, 24, 0, (int) (($w - ($tb[2] - $tb[0])) / 2), $h - 70, $gold, $font, $label);
        } else {
            imagestring($img, 5, 60, 450, substr($text, 0, 40), $white);
            imagestring($img, 5, 60, 500, $label, $white);
        }

        imagepng($img, $path);

        imagedestroy($img);
    }
}
