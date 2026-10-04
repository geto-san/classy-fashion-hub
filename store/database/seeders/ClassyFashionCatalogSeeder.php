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
    /**
     * EU shoe sizes sold in Uganda. Added to the same 'size' attribute as
     * S/M/L/XL so footwear is chosen by size and colour like clothing
     * (report 9.2: size on products).
     */
    public const SHOE_SIZES = ['38', '39', '40', '41', '42', '43', '44', '45'];

    public const EXTRA_COLORS = ['Navy', 'Maroon'];

    protected array $catalog = [
        // Shirts / Tops
        ['Classic White Formal Shirt', 'configurable', [2, 6], 35000, 30, ['S', 'M', 'L', 'XL'], ['White', 'Blue']],
        ['Ankara Print Casual Shirt', 'configurable', [2, 6], 55000, 20, ['S', 'M', 'L', 'XL'], ['Red', 'Yellow', 'Green']],
        ['Plain Polo T-Shirt', 'configurable', [2, 6], 25000, 40, ['S', 'M', 'L', 'XL'], ['White', 'Black', 'Blue', 'Grey']],
        ['Kitenge Print Shirt', 'configurable', [2, 5], 48000, 18, ['S', 'M', 'L', 'XL'], ['Blue', 'Red', 'Green']],

        // Jackets / Outerwear
        ['Denim Jacket', 'configurable', [2, 6], 95000, 15, ['M', 'L', 'XL'], ['Blue', 'Black']],
        ['Hooded Sweatshirt', 'configurable', [2, 6, 7], 65000, 22, ['S', 'M', 'L', 'XL'], ['Grey', 'Black', 'Navy', 'Maroon']],
        ['Fleece Hoodie', 'configurable', [2, 6, 7], 72000, 18, ['S', 'M', 'L', 'XL'], ['Black', 'Grey', 'Blue']],

        // Dresses / Women's
        ['Gomesi Traditional Dress', 'configurable', [4], 130000, 12, ['M', 'L', 'XL'], ['Red', 'Purple', 'Pink']],
        ['Kitenge Wrap Dress', 'configurable', [4], 75000, 16, ['S', 'M', 'L'], ['Yellow', 'Green', 'Orange']],
        ['Casual Midi Dress', 'configurable', [4], 60000, 18, ['S', 'M', 'L', 'XL'], ['Blue', 'Black', 'White']],

        // Shoes / Footwear
        ['Canvas Sneakers', 'configurable', [8], 85000, 5, self::SHOE_SIZES, ['White', 'Black', 'Red']],
        ['Leather School Shoes', 'configurable', [8], 120000, 4, self::SHOE_SIZES, ['Black', 'Brown']],
        ['Rubber Sandals', 'configurable', [8], 22000, 8, self::SHOE_SIZES, ['Black', 'Brown', 'Red']],

        // Accessories
        ['Student Backpack', 'simple', [4, 6], 55000, 25, [], []],
        ['Leather Belt', 'simple', [2], 28000, 35, [], []],
        ['Baseball Cap', 'simple', [6], 18000, 50, [], []],
        ['Wool Beanie Hat', 'simple', [6, 7], 15000, 45, [], []],
        ['Canvas Tote Bag', 'simple', [4, 6], 22000, 30, [], []],
        ['Cotton Socks 3-Pack', 'simple', [2], 12000, 60, [], []],
    ];


    public function run(): void
    {
        $this->products = app(ProductRepository::class);

        $this->setupUgxCurrency();

        $this->setupUgandaStore();

        $this->seedCmsContent();

        $this->pruneDemoCategories();

        DB::table('theme_sections')
            ->where('type', 'category_carousel')
            ->update(['status' => 0, 'draft_status' => 0]);

        $this->pruneDeadHeroSlides();

        DB::table('categories')
            ->join('category_translations', function ($join) {
                $join->on('category_translations.category_id', '=', 'categories.id')
                    ->where('category_translations.locale', 'en');
            })
            ->whereIn('category_translations.name', [
                'Kids', 'Girls Clothing', 'Boys Clothing', 'Girls Footwear', 'Boys Footwear',
            ])
            ->update(['categories.status' => 0]);

        $this->ensureShoeSizeOptions();
        $this->ensureExtraColorOptions();

        if (Product::whereNull('parent_id')->where('type', 'configurable')->exists()) {
            $this->command->info('Fashion catalog already present; skipping (redeploy-safe).');

            return;
        }

        $this->wipeDemoProducts();

        foreach ($this->catalog as $item) {
            $this->createProduct(...$item);
        }

        $this->command->info('Classy Fashion Hub catalog seeded: '.count($this->catalog).' products in UGX.');
    }

    /**
     * Uganda-appropriate content for the stock CMS policy pages.
     * Idempotent: keyed by url_key + locale.
     */
    protected function seedCmsContent(): void
    {
        $pages = [
            'about-us' => '<div class="static-container"><div class="mb-5"><h2>About Classy Fashion Hub</h2><p>Classy Fashion Hub is a fashion shop based in Mbarara, Uganda, serving university students, high school students and adults across Uganda. We stock shirts, jackets, shoes, dresses and accessories at fixed prices in Uganda Shillings - no bargaining. Shop in-store in Mbarara or order online and pay with MTN Mobile Money, Airtel Money or cash on delivery. We deliver across Uganda including Kampala, Mbarara, Masaka and Entebbe.</p></div></div>',
            'return-policy' => '<div class="static-container"><div class="mb-5"><h2>Return Policy</h2><p>Unworn items with tags attached can be returned within 7 days of delivery for exchange or refund to your mobile money line. Contact us with your order number to arrange a Mbarara or Kampala collection, or a rider return within our delivery zones.</p></div></div>',
            'refund-policy' => '<div class="static-container"><div class="mb-5"><h2>Refund Policy</h2><p>Approved refunds go back to your MTN or Airtel line within 3 working days, or as cash for cash-on-delivery orders.</p></div></div>',
            'payment-policy' => '<div class="static-container"><div class="mb-5"><h2>Payment Policy</h2><p>We accept MTN Mobile Money and Airtel Money (confirmed before your order is marked Paid) and cash on delivery within our delivery zones. All prices are in Uganda Shillings (UGX).</p></div></div>',
            'shipping-policy' => '<div class="static-container"><div class="mb-5"><h2>Shipping Policy</h2><p>Flat delivery fee of UGX 5,000 anywhere in Uganda. Kampala orders arrive within 24 hours; upcountry orders take 2–4 days. Add gate, landmark or call-on-arrival notes in the delivery instructions at checkout.</p></div></div>',
            'privacy-policy' => '<div class="static-container"><div class="mb-5"><h2>Privacy Policy</h2><p>We keep only what your order needs: name, contact, delivery location and transaction records. Your details are never sold and only staff who handle your order can see them.</p></div></div>',
            'terms-conditions' => '<div class="static-container"><div class="mb-5"><h2>Terms &amp; Conditions</h2><p>Displayed UGX prices are final. Orders are confirmed subject to stock availability; mobile-money orders are fulfilled after payment confirmation.</p></div></div>',
            'terms-of-use' => '<div class="static-container"><div class="mb-5"><h2>Terms of Use</h2><p>Use accurate contact and delivery details so our riders can reach you. Misuse of accounts may lead to suspension.</p></div></div>',
            'customer-service' => '<div class="static-container"><div class="mb-5"><h2>Customer Service</h2><p>Questions about sizes, orders or delivery? Message us with your order number and we shall help — we reply within one working day.</p></div></div>',
            'whats-new' => '<div class="static-container"><div class="mb-5"><h2>What&apos;s New</h2><p>New kitenge and ankara arrivals every month. Follow our catalogue for the latest Kampala fashion.</p></div></div>',
        ];

        foreach ($pages as $urlKey => $html) {
            DB::table('cms_page_translations')
                ->where('url_key', $urlKey)
                ->where('locale', 'en')
                ->update(['html_content' => $html]);
        }

        $this->command->info('CMS policy pages localized for Uganda.');
    }

    /**
     * Drop hero slides pointing at removed demo categories (smart home,
     * phones, laptops). Keeps slides whose link is a live category,
     * product, CMS page or absolute URL. Idempotent.
     */
    protected function pruneDeadHeroSlides(): void
    {
        $rows = DB::table('theme_section_translations')
            ->whereIn('section_id', function ($query) {
                $query->select('id')->from('theme_sections')->where('type', 'image_carousel');
            })
            ->get(['id', 'options', 'draft_options']);

        foreach ($rows as $row) {
            foreach (['options', 'draft_options'] as $column) {
                $data = json_decode((string) $row->{$column}, true);

                if (empty($data['images']) || ! is_array($data['images'])) {
                    continue;
                }

                $kept = array_values(array_filter($data['images'], function ($slide) {
                    $link = (string) ($slide['link'] ?? '');

                    if ($link === '' || str_starts_with($link, 'http')) {
                        return true;
                    }

                    return DB::table('category_translations')->where('slug', $link)->exists()
                        || DB::table('product_attribute_values as av')
                            ->join('attributes as a', 'a.id', '=', 'av.attribute_id')
                            ->where('a.code', 'url_key')
                            ->where('av.text_value', $link)
                            ->exists()
                        || DB::table('cms_page_translations')->where('url_key', $link)->exists();
                }));

                if (count($kept) !== count($data['images'])) {
                    $data['images'] = $kept;

                    DB::table('theme_section_translations')
                        ->where('id', $row->id)
                        ->update([$column => json_encode($data)]);

                    $this->command->info('Pruned dead hero slide(s).');
                }
            }
        }
    }

    /**
     * Uganda store context (currency, payments, shipping, address rules).
     * Idempotent: safe on every run and on fresh installs (Render).
     */
    protected function setupUgandaStore(): void
    {
        $ugxId = DB::table('currencies')->where('code', 'UGX')->value('id');

        DB::table('channel_currencies')->delete();
        DB::table('channel_currencies')->insert(['channel_id' => $this->channelId, 'currency_id' => $ugxId]);

        $config = function (string $code, ?string $value, ?string $channel = 'default', ?string $locale = null) {
            DB::table('core_config')->updateOrInsert(
                ['code' => $code, 'channel_code' => $channel, 'locale_code' => $locale],
                ['value' => $value]
            );
        };

        $config('sales.payment_methods.moneytransfer.active', '0');

        $config('sales.carriers.free.active', '0');
        $config('sales.carriers.flatrate.active', '1');
        $config('sales.carriers.flatrate.title', 'Delivery Across Uganda', 'default', 'en');
        $config('sales.carriers.flatrate.description', 'Flat delivery fee anywhere in Uganda.', 'default', 'en');
        $config('sales.carriers.flatrate.default_rate', '5000');
        $config('sales.carriers.flatrate.type', 'per_order');

        $config('customer.address.requirements.state', '0');
        $config('customer.address.requirements.postcode', '0');

        DB::table('channels')->where('id', $this->channelId)->update(['timezone' => 'Africa/Kampala']);

        $this->command->info('Uganda store context ready (UGX only, local payments, UGX 5,000 flat delivery).');
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
                'symbol'            => 'UGX',
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
     * Remove installer demo categories outside a fashion shop (Wellness,
     * Bookings, Electronics, Household, Books & Stationery) with their
     * subtrees, and repoint homepage section links at fashion categories.
     * Skips any category holding products. Idempotent.
     */
    protected function pruneDemoCategories(): void
    {
        $roots = DB::table('categories')
            ->join('category_translations', function ($join) {
                $join->on('category_translations.category_id', '=', 'categories.id')
                    ->where('category_translations.locale', 'en');
            })
            ->whereIn('category_translations.name', [
                'Wellness', 'Bookings', 'Electronics', 'Household', 'Books & Stationery',
            ])
            ->pluck('categories.id')
            ->all();

        if (empty($roots)) {
            return;
        }

        $ids = $roots;
        $queue = $roots;

        while (! empty($queue)) {
            $children = DB::table('categories')->whereIn('parent_id', $queue)->pluck('id')->all();
            $ids = array_merge($ids, $children);
            $queue = $children;
        }

        $withProducts = DB::table('product_categories')->whereIn('category_id', $ids)->distinct()->pluck('category_id')->all();

        $deleteIds = array_diff($ids, $withProducts);

        \Webkul\Category\Models\Category::whereIn('id', $deleteIds)->get()->each->delete();

        $removed = count($roots) - count(array_intersect($roots, $withProducts));

        foreach (['electronics' => 'mens', 'wellness' => 'womens'] as $old => $new) {
            DB::table('theme_section_translations')
                ->where('options', 'like', '%'.$old.'%')
                ->orWhere('draft_options', 'like', '%'.$old.'%')
                ->get(['id', 'options', 'draft_options'])
                ->each(function ($row) use ($old, $new) {
                    DB::table('theme_section_translations')->where('id', $row->id)->update([
                        'options'       => str_replace('href=\\"'.$old, 'href=\\"'.$new, $row->options),
                        'draft_options' => $row->draft_options ? str_replace('href=\\"'.$old, 'href=\\"'.$new, $row->draft_options) : $row->draft_options,
                    ]);
                });
        }

        $this->command->info("Pruned {$removed} demo category tree(s); homepage links repointed to fashion.");
    }

    /**
     * Make sure the numeric shoe sizes exist as options of the 'size'
     * attribute (the installer only ships S/M/L/XL). Idempotent.
     */
    protected function ensureShoeSizeOptions(): void
    {
        $attributeId = DB::table('attributes')->where('code', 'size')->value('id');

        if (! $attributeId) {
            return;
        }

        $order = (int) DB::table('attribute_options')->where('attribute_id', $attributeId)->max('sort_order');

        foreach (self::SHOE_SIZES as $size) {
            $optionId = DB::table('attribute_options')
                ->where('attribute_id', $attributeId)
                ->where('admin_name', $size)
                ->value('id');

            if (! $optionId) {
                $optionId = DB::table('attribute_options')->insertGetId([
                    'attribute_id' => $attributeId,
                    'admin_name'   => $size,
                    'sort_order'   => ++$order,
                ]);
            }

            DB::table('attribute_option_translations')->updateOrInsert(
                ['attribute_option_id' => $optionId, 'locale' => $this->locale],
                ['label' => $size]
            );
        }
    }

    /**
     * Make sure extra colours used by the catalogue exist as options of
     * the 'color' attribute. Idempotent.
     */
    protected function ensureExtraColorOptions(): void
    {
        $attribute = DB::table('attributes')->where('code', 'color')->first(['id']);

        if (! $attribute) {
            return;
        }

        $order = (int) DB::table('attribute_options')->where('attribute_id', $attribute->id)->max('sort_order');

        foreach (self::EXTRA_COLORS as $color) {
            $optionId = DB::table('attribute_options')
                ->where('attribute_id', $attribute->id)
                ->where('admin_name', $color)
                ->value('id');

            if (! $optionId) {
                $optionId = DB::table('attribute_options')->insertGetId([
                    'attribute_id' => $attribute->id,
                    'admin_name'   => $color,
                    'sort_order'   => ++$order,
                ]);
            }

            DB::table('attribute_option_translations')->updateOrInsert(
                ['attribute_option_id' => $optionId, 'locale' => $this->locale],
                ['label' => $color]
            );
        }
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
            'short_description'   => $name.' — Available at Classy Fashion Hub, Mbarara. Fixed price UGX '.number_format($price).'.',
            'description'         => $name.' available at a fixed price of UGX '.number_format($price).'. No bargaining — what you see is what you pay. Ideal for university students, high school students and anyone looking for quality fashion in Uganda. Pay with MTN Mobile Money, Airtel Money or cash on delivery. We deliver across Uganda.',
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

        $this->attachImages($product, $name);

        Event::dispatch('catalog.product.update.after', $product);

        $this->command->line("  + {$name} ({$type}, UGX ".number_format($price).')');
    }

    /**
     * Link the product's real photos (database/seeders/product-images) or,
     * when none were supplied, a generated placeholder, following the
     * installer seeder storage pattern.
     */
    protected function attachImages(Product $product, string $name): void
    {
        $photos = \ClassyFashion\Support\ProductImage::photos($name);

        if ($photos !== []) {
            foreach ($photos as $position => $photo) {
                $path = Storage::putFile('product/'.$product->id, new File($photo));

                if ($path) {
                    DB::table('product_images')->insert([
                        'type'       => null,
                        'path'       => $path,
                        'product_id' => $product->id,
                        'position'   => $position,
                    ]);
                }
            }

            return;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'classy').'.png';

        \ClassyFashion\Support\ProductImage::placeholder($tmp, $name);

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
}
