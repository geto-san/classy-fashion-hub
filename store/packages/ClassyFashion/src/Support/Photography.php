<?php

namespace ClassyFashion\Support;

/**
 * Keep reviewed imported photos attached to their intended subjects.
 */
class Photography
{
    /**
     * Owner uploads remain editable; imported media requires an explicit review.
     */
    public static function allows(string $path, string $placement = 'product'): bool
    {
        static $review;

        $review ??= json_decode(file_get_contents(__DIR__.'/../Resources/photography-review.json'), true, 512, JSON_THROW_ON_ERROR)['approved'];
        $path = ltrim($path, '/');

        if (preg_match('~^storefront/campaigns/20261103/(hero|closing)\.webp$~', $path, $match)) {
            $path = 'images/campaigns/'.$match[1].'.webp';
        }

        $approved = $review[$path] ?? $review['/'.$path] ?? null;

        if ($approved !== null) {
            if ($approved === true || $approved === '*') {
                return true;
            }

            if (is_array($approved)) {
                return in_array($placement, $approved, true)
                    || in_array('*', $approved, true)
                    || (str_starts_with($placement, 'category:') && in_array('category', $approved, true));
            }

            if ($approved === $placement) {
                return true;
            }

            if (str_starts_with($placement, 'category:') && $approved === 'category') {
                return true;
            }

            return false;
        }

        return ! preg_match('~^(products/(catalogue-20261104|african-20261108|demo)|storefront/(editorial/20261104|campaigns/20261103)|images/(campaigns|storefront))/~', $path);
    }
}
