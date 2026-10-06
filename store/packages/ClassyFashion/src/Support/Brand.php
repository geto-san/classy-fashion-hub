<?php

namespace ClassyFashion\Support;

/**
 * Single brand identity for Classy Fashion Hub (Brand Kit v1.0).
 *
 * Every storefront, admin, e-mail and PDF surface renders this name, logo
 * and copyright. No Bagisto or Webkul trademark may remain user-visible.
 */
class Brand
{
    public const NAME = 'Classy Fashion Hub';

    public const SHORT_NAME = 'Classy';

    public const LOGO_PATH = '/images/brand-logo.png';

    public const FAVICON_PATH = '/favicon.ico';

    /**
     * Canonical shop footer and admin powered-by replacement.
     */
    public static function copyright(): string
    {
        return '© Copyright 2010 - '.date('Y').', '.self::NAME.'. All rights reserved.';
    }

    /**
     * Rewrite rendered HTML so the single Classy brand applies on every
     * page and for every user, overriding Bagisto fallbacks.
     */
    public static function apply(string $html): string
    {
        $copyright = e(self::copyright());

        $replacements = [
            '/<meta\s+name="generator"\s+content="Bagisto"\s*\/?>/i' => '<meta name="generator" content="'.self::NAME.'">',
            '/<!--\s*Built With Bagisto\s*-->/' => '<!-- Built With '.self::NAME.' -->',
            '/cache\/logo\/bagisto\.png/' => ltrim(self::LOGO_PATH, '/'),
            '/aria-label="Bagisto"/' => 'aria-label="'.self::NAME.'"',
        ];

        foreach ($replacements as $pattern => $replacement) {
            $html = (string) preg_replace($pattern, $replacement, $html);
        }

        $html = (string) preg_replace(
            '/Powered by\s*<a[^>]*>Bagisto<\/a>,?\s*A Community Project by\s*<a[^>]*>Webkul<\/a>/i',
            $copyright,
            $html
        );

        $html = (string) preg_replace(
            '/Propulsé par\s*<a[^>]*>Bagisto<\/a>,?\s*un projet[^<]*par\s*<a[^>]*>Webkul<\/a>/i',
            $copyright,
            $html
        );

        $html = (string) preg_replace(
            '/<a[^>]*>Bagisto<\/a>,?\s*(?:A Community Project|un projet[^<]*|ein Community[^<]*|un proyecto[^<]*)?\s*(?:by|par|von|por)\s*<a[^>]*>Webkul<\/a>/i',
            $copyright,
            $html
        );

        $html = (string) preg_replace(
            '/<a[^>]*href="https:\/\/bagisto\.com[^"]*"[^>]*>Bagisto<\/a>/i',
            self::NAME,
            $html
        );

        $html = (string) preg_replace(
            '/<a[^>]*href="https:\/\/webkul\.com[^"]*"[^>]*>Webkul<\/a>/i',
            self::NAME,
            $html
        );

        $html = (string) preg_replace(
            '/Powered by\s*:bagisto[^<]*/i',
            $copyright,
            $html
        );

        $html = (string) preg_replace(
            '/©[^<]*Webkul Software[^<]*All rights reserved\./i',
            self::copyright(),
            $html
        );

        $html = (string) preg_replace(
            '/©[^<]*Webkul Software[^<]*Tous droits réservés\./i',
            self::copyright(),
            $html
        );

        $html = (string) preg_replace(
            '/Webkul Software \([^)]*\)/i',
            self::NAME,
            $html
        );

        $html = str_replace(
            ['>Bagisto<', '>bagisto<', '>Webkul<'],
            ['>'.self::NAME.'<', '>'.self::NAME.'<', '>'.self::NAME.'<'],
            $html
        );

        $html = (string) preg_replace(
            '/https:\/\/(www\.)?bagisto\.com[^\s"\']*/i',
            '/',
            $html
        );

        $html = (string) preg_replace(
            '/https:\/\/(www\.)?webkul\.com[^\s"\']*/i',
            '/',
            $html
        );

        $html = (string) preg_replace(
            '/\/themes\/(shop|admin|installer)\/[^"\']*build\/assets\/(dark-logo|logo|bagisto-logo)[^"\']*\.svg/i',
            self::LOGO_PATH,
            $html
        );

        $html = (string) preg_replace(
            '/\/themes\/(shop|admin|installer)\/[^"\']*build\/assets\/favicon[^"\']*\.ico/i',
            self::FAVICON_PATH,
            $html
        );

        return $html;
    }
}
