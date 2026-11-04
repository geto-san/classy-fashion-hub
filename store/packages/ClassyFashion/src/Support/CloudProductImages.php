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
     * Retain the ordinary Bagisto behaviour for local product storage.
     */
    public function getProductBaseImage($product, ?array $galleryImages = null)
    {
        if ($product && $this->cloud() && ($image = $product->images->first())) {
            return $galleryImages ? $galleryImages[0] : $this->urls($image->path, (string) ($image->alt ?: $product->name ?: $product->sku));
        }

        return parent::getProductBaseImage($product, $galleryImages);
    }

    /**
     * Existing references are already validated during upload and import.
     */
    public function getGalleryImages($product)
    {
        if (! $product || ! $this->cloud()) {
            return parent::getGalleryImages($product);
        }

        $images = $product->images->map(fn ($image) => $this->urls($image->path, (string) ($image->alt ?: $product->name ?: $product->sku)))->all();

        if ($images) {
            return $images;
        }

        return $product->parent_id ? $this->getGalleryImages($product->parent) : parent::getGalleryImages($product);
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
