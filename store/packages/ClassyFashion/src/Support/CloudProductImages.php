<?php

namespace ClassyFashion\Support;

use ClassyFashion\Filesystem\CloudinaryAdapter;
use Illuminate\Support\Facades\Storage;
use Webkul\Product\ProductImage;

/**
 * Deliver hosted originals through their CDN without processing each card in PHP.
 */
class CloudProductImages extends ProductImage
{
    /**
     * Use the same reviewed gallery for cards, cart thumbnails and product details.
     */
    public function getProductBaseImage($product, ?array $galleryImages = null)
    {
        if (! $product) {
            return parent::getProductBaseImage($product, $galleryImages);
        }

        $images = $this->getGalleryImages($product);

        return $images[0] ?? parent::getProductBaseImage((clone $product)->setRelation('images', collect()));
    }

    /**
     * Filter primary and alternate photos before either local or CDN delivery.
     */
    public function getGalleryImages($product)
    {
        if (! $product) {
            return parent::getGalleryImages($product);
        }

        $approved = $product->images->filter(fn ($image) => Photography::allows($image->path))->values();

        if (! $this->cloud()) {
            return parent::getGalleryImages((clone $product)->setRelation('images', $approved));
        }

        $images = $approved->map(fn ($image) => $this->urls($image->path, (string) ($image->alt ?: $product->name ?: $product->sku)))->all();

        if ($images) {
            return $images;
        }

        return $product->parent_id ? $this->getGalleryImages($product->parent) : parent::getGalleryImages((clone $product)->setRelation('images', collect()));
    }

    /**
     * Detect the actual adapter so local fixtures and storage fakes retain their semantics.
     */
    protected function cloud(): bool
    {
        return Storage::disk('public')->getAdapter() instanceof CloudinaryAdapter;
    }

    /**
     * A browser reuses one responsive original for cards, thumbnails and the gallery.
     */
    protected function urls(string $path, string $alt): array
    {
        $url = Storage::disk('public')->url($path);
        static $fits;

        if ($fits === null) {
            $manifest = __DIR__.'/../Resources/coursework-catalogue/manifest.json';
            $fits = is_file($manifest) ? array_column(json_decode(file_get_contents($manifest), true, 512, JSON_THROW_ON_ERROR)['products'], 'image_fit', 'image_path') : [];
        }

        return array_fill_keys(['small_image_url', 'medium_image_url', 'large_image_url', 'product_card_image_url', 'original_image_url'], $url) + ['alt' => $alt, 'fit' => $fits[$path] ?? 'cover'];
    }
}
