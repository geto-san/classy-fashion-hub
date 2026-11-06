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
        $fields = [
            'hero_title' => 'text',
            'hero_text' => 'textarea',
            'hero_button' => 'text',
            'hero_link' => 'link',
            'hero_image' => 'image',
            'hero_alt' => 'text',
            'closing_image' => 'image',
            'closing_title' => 'text',
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

        foreach (['title' => 'text', 'link' => 'link', 'image' => 'image', 'alt' => 'text'] as $suffix => $type) {
            $fields['outerwear_'.$suffix] = $type;
        }

        foreach (self::editorialSlots() as $slot) {
            $fields[$slot.'_image'] = 'image';
            $fields[$slot.'_alt'] = 'text';
        }

        return $fields;
    }

    /**
     * Give every photographic placement its own editable original.
     */
    public static function editorialSlots(): array
    {
        return ['women_editorial', 'men_editorial', 'collection_dresses', 'collection_shirts', 'collection_caps', 'collection_bags', 'collection_shoes', 'collection_tops', 'women_lookbook', 'men_lookbook', 'featured_story', 'journal_one', 'journal_two', 'journal_three', 'journal_four', 'journal_five', 'journal_six'];
    }

    /**
     * Read immutable asset provenance once per request, beneath any saved owner values.
     */
    public static function presentation(): array
    {
        static $presentation;

        return $presentation ??= is_file(__DIR__.'/../Resources/coursework-catalogue/presentation.json')
            ? json_decode(file_get_contents(__DIR__.'/../Resources/coursework-catalogue/presentation.json'), true, 512, JSON_THROW_ON_ERROR)
            : [];
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

        $slot = preg_replace('/_(image|alt|title|link)$/', '', $field);

        if (isset(self::presentation()['settings'][$field])) {
            return self::presentation()['settings'][$field];
        }

        if (in_array($slot, self::editorialSlots(), true) || $slot === 'outerwear') {
            $photo = self::presentation()['images'][$slot] ?? [];

            return match (true) {
                str_ends_with($field, '_image') => $photo['path'] ?? '',
                str_ends_with($field, '_alt') => $photo['alt'] ?? trans('classy-fashion::coursework.photography'),
                $field === 'outerwear_title' => trans('classy-fashion::coursework.outerwear'),
                $field === 'outerwear_link' => '/outerwear',
                default => '',
            };
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
            'closing_title' => trans('classy-fashion::coursework.closing_title'),
            default => trans('classy-fashion::storefront.content.'.$field),
        };
    }

    /**
     * Uploaded media uses the storage disk; bundled media survives ephemeral deployments.
     */
    public static function image(string $field): string
    {
        $path = (string) self::get($field);

        if ($path === '' || ! Photography::allows($path, $field)) {
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

        $keys = ['dresses', 'everyday', 'outerwear', 'caps'];

        return array_map(fn ($key) => [
            'src' => $images[$key.'_image'],
            'title' => self::get($key.'_title'),
            'alt' => self::get($key.'_alt'),
            'link' => self::get($key.'_link'),
        ], $keys);
    }

    /**
     * Reserve each photo for one placement, prioritizing the opening and closing campaigns.
     */
    public static function homepageImages(): array
    {
        $images = [];
        $used = [];

        $fields = array_keys(array_filter(self::fields(), fn ($type) => $type === 'image'));

        foreach (array_unique(array_merge(['hero_image', 'closing_image'], $fields)) as $field) {
            $image = self::image($field);
            $images[$field] = in_array($image, $used, true) ? '' : $image;

            if ($image !== '') {
                $used[] = $image;
            }
        }

        return $images;
    }

    /**
     * Each collection tile has a separate photograph and matching destination.
     */
    public static function editorialCollections(): array
    {
        $images = self::homepageImages();

        return array_map(fn ($key) => [
            'src' => $images['collection_'.$key.'_image'],
            'title' => trans('classy-fashion::coursework.collections.'.$key),
            'alt' => self::get('collection_'.$key.'_alt'),
            'link' => '/'.$key,
        ], ['dresses', 'shirts', 'caps', 'bags', 'shoes', 'tops']);
    }

    /**
     * Keep the owner editor aligned with the actual homepage placements.
     */
    public static function editorGroups(): array
    {
        return [
            'hero' => ['hero_title', 'hero_text', 'hero_button', 'hero_link', 'hero_image', 'hero_alt'],
            'collections' => ['collection_title', 'collection_text', 'dresses_title', 'dresses_link', 'dresses_image', 'dresses_alt', 'caps_title', 'caps_link', 'caps_image', 'caps_alt', 'everyday_title', 'everyday_link', 'everyday_image', 'everyday_alt', 'outerwear_title', 'outerwear_link', 'outerwear_image', 'outerwear_alt'],
            'editorial' => array_merge(...array_map(fn ($slot) => [$slot.'_image', $slot.'_alt'], self::editorialSlots())),
            'closing' => ['closing_title', 'closing_image', 'closing_alt'],
            'options' => ['assistant_enabled'],
        ];
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
