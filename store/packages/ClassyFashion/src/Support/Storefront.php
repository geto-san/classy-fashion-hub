<?php

namespace ClassyFashion\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Client-editable storefront content with optional owner-supplied photography.
 */
class Storefront
{
    /**
     * The fields available to the shop owner.
     */
    public static function fields(): array
    {
        return [
            'hero_title' => 'text',
            'hero_text' => 'textarea',
            'hero_button' => 'text',
            'hero_link' => 'link',
            'hero_image' => 'image',
            'hero_alt' => 'text',
            'closing_image' => 'image',
            'closing_alt' => 'text',
            'collection_title' => 'text',
            'collection_text' => 'textarea',
            'dresses_title' => 'text',
            'dresses_link' => 'link',
            'dresses_image' => 'image',
            'dresses_alt' => 'text',
            'caps_title' => 'text',
            'caps_link' => 'link',
            'caps_image' => 'image',
            'caps_alt' => 'text',
            'everyday_title' => 'text',
            'everyday_link' => 'link',
            'everyday_image' => 'image',
            'everyday_alt' => 'text',
            'assistant_enabled' => 'boolean',
        ];
    }

    /**
     * Read saved content without coupling the hero to seeded carousel slides.
     */
    public static function get(string $field): mixed
    {
        $value = core()->getConfigData('general.content.storefront.'.$field);

        if ($value !== null && str_ends_with($field, '_link') && ! self::validLink((string) $value)) {
            return '/search';
        }

        if ($value !== null && ! (str_ends_with($field, '_image') && $value === '')) {
            return $field === 'assistant_enabled'
                ? filter_var($value, FILTER_VALIDATE_BOOLEAN)
                : $value;
        }

        return match ($field) {
            'assistant_enabled' => true,
            'hero_link' => '/search',
            'dresses_link' => '/search?query=dress',
            'caps_link' => '/search?query=cap',
            'everyday_link' => '/search',
            'hero_image' => '/images/campaigns/hero.webp',
            'dresses_image' => '/images/campaigns/dresses.webp',
            'caps_image' => '/images/campaigns/caps.webp',
            'everyday_image' => '/images/campaigns/everyday.webp',
            'closing_image' => '/images/campaigns/closing.webp',
            default => trans('classy-fashion::storefront.content.'.$field),
        };
    }

    /**
     * Uploaded media uses the storage disk; bundled media survives ephemeral deployments.
     */
    public static function image(string $field): string
    {
        $path = (string) self::get($field);

        if ($path === '') {
            return '';
        }

        return str_starts_with($path, '/') ? $path : Storage::disk('public')->url($path);
    }

    /**
     * Explicit collection content keeps uploaded photographs attached to their subjects.
     */
    public static function collections(): array
    {
        $images = self::homepageImages();

        return array_map(fn ($key) => [
            'src' => $images[$key.'_image'],
            'title' => self::get($key.'_title'),
            'alt' => self::get($key.'_alt'),
            'link' => self::get($key.'_link'),
        ], ['dresses', 'caps', 'everyday']);
    }

    /**
     * Reserve each photo for one placement, prioritizing the opening and closing campaigns.
     */
    public static function homepageImages(): array
    {
        $images = [];
        $used = [];

        foreach (['hero_image', 'closing_image', 'dresses_image', 'caps_image', 'everyday_image'] as $field) {
            $image = self::image($field);
            $images[$field] = in_array($image, $used, true) ? '' : $image;

            if ($image !== '') {
                $used[] = $image;
            }
        }

        return $images;
    }

    /**
     * Collection destinations use merchandise visible in the owner's shop photographs.
     */
    public static function editorialCollections(): array
    {
        return array_merge(self::collections(), array_map(fn ($key) => [
            'src' => self::image('everyday_image'),
            'title' => trans('classy-fashion::storefront.editorial.'.$key),
            'alt' => self::get('everyday_alt'),
            'link' => '/search?query='.$key,
        ], ['bags', 'shoes', 'shirts']));
    }

    /**
     * Only same-site paths can be used for collection and hero links.
     */
    public static function validLink(string $value): bool
    {
        return str_starts_with($value, '/')
            && ! str_starts_with($value, '//')
            && ! preg_match('/[\\\\\x00-\x20]/', $value);
    }
}
