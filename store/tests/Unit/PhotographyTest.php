<?php

namespace Tests\Unit;

use ClassyFashion\Support\CloudProductImages;
use ClassyFashion\Support\Photography;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PHPUnit\Framework\TestCase;
use Webkul\ImageCache\ImageUrlBuilder;
use Webkul\Product\Repositories\ProductRepository;

class PhotographyTest extends TestCase
{
    /**
     * Release mocked storage and image services between tests.
     */
    protected function tearDown(): void
    {
        Mockery::close();
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        Container::setInstance(null);
        parent::tearDown();
    }

    /**
     * Withdrawn stock photographs stay hidden across products and editorial placements.
     */
    public function test_imported_photos_require_review_and_the_matching_placement(): void
    {
        $this->assertFalse(Photography::allows('products/catalogue-20261104/blue-t-shirt.jpg'));
        $this->assertFalse(Photography::allows('products/catalogue-20261104/white-tshirt-template.jpg'));
        $this->assertFalse(Photography::allows('storefront/editorial/20261104/smiling-man-in-blue-jpg.jpg', 'women_editorial_image'));
        $this->assertFalse(Photography::allows('/images/campaigns/everyday.webp', 'category:women'));
        $this->assertFalse(Photography::allows('storefront/campaigns/20261103/hero.webp', 'hero_image'));
        $this->assertFalse(Photography::allows('storefront/campaigns/20261103/hero.webp', 'men_editorial_image'));
        $this->assertTrue(Photography::allows('storefront/owner-campaign.webp', 'hero_image'));
        $this->assertFalse(Photography::allows('products/catalogue-20261104/reflective-sunglasses.jpg', 'collection_caps_image'));
        $this->assertFalse(Photography::allows('products/catalogue-20261104/a-model-in-khaki-trousers-and-a-striped-t-shirt.jpg', 'men_lookbook_image'));
    }

    /**
     * Filter the primary photo and alternate views together without changing records.
     */
    public function test_cloud_and_local_cards_galleries_and_variants_exclude_unreviewed_media(): void
    {
        require_once __DIR__.'/../../packages/Webkul/ImageCache/src/Http/helpers.php';

        $app = new Container;
        Container::setInstance($app);
        Facade::setFacadeApplication($app);
        $builder = Mockery::mock(ImageUrlBuilder::class);
        $builder->shouldReceive('templateNames')->andReturn(['small', 'medium', 'large', 'product_card', 'original']);
        $builder->shouldReceive('urls')->andReturnUsing(fn ($path) => ['large_image_url' => '/media/'.$path]);
        $app->instance(ImageUrlBuilder::class, $builder);
        $storage = Mockery::mock();
        $storage->shouldReceive('disk')->with('public')->andReturnSelf();
        $storage->shouldReceive('url')->andReturnUsing(fn ($path) => '/media/'.$path);
        $storage->shouldReceive('has')->andReturnTrue();
        Storage::swap($storage);

        $helper = new class(Mockery::mock(ProductRepository::class)) extends CloudProductImages
        {
            public bool $useCloud = true;

            /**
             * Exercise CDN delivery without an external account.
             */
            protected function cloud(): bool
            {
                return $this->useCloud;
            }

            /**
             * Use a deterministic image-free fallback for this isolated test.
             */
            public function getPlaceholderUrl(string $template): string
            {
                return '/placeholder.webp';
            }
        };
        $product = new class extends Model {};
        $product->setRawAttributes(['name' => 'Blue T-shirt', 'sku' => 'CFH-T-SHIRTS-002']);
        $blocked = (object) ['path' => 'products/catalogue-20261104/white-tshirt-template.jpg', 'alt' => 'Unreviewed', 'alt_text' => 'Unreviewed'];
        $approved = (object) ['path' => 'products/owner-reviewed-kitenge.jpg', 'alt' => 'Kitenge fabric', 'alt_text' => 'Kitenge fabric'];
        $product->setRelation('images', collect([$blocked, $approved]));
        $product->setRelation('videos', collect());

        $gallery = $helper->getGalleryImages($product);
        $this->assertCount(1, $gallery);
        $this->assertSame('/media/'.$approved->path, $helper->getProductBaseImage($product)['large_image_url']);
        $this->assertCount(2, $product->images);

        $variant = clone $product;
        $variant->setAttribute('parent_id', 1);
        $variant->setRelation('parent', $product);
        $variant->setRelation('images', collect([$blocked]));
        $this->assertSame($gallery, $helper->getGalleryImages($variant));

        $product->setRelation('images', collect([$blocked]));
        $this->assertSame('/placeholder.webp', $helper->getProductBaseImage($product)['large_image_url']);

        $helper->useCloud = false;
        $this->assertSame('/placeholder.webp', $helper->getProductBaseImage($product)['large_image_url']);
        $product->setRelation('images', collect([$blocked, $approved]));
        $this->assertCount(1, $helper->getGalleryImages($product));
        $this->assertSame('/media/'.$approved->path, $helper->getProductBaseImage($product)['large_image_url']);
        $this->assertSame('/media/'.$approved->path, $helper->getProductBaseImage($variant)['large_image_url']);
    }
}
