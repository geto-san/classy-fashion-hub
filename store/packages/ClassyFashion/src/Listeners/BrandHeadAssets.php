<?php

namespace ClassyFashion\Listeners;

use Webkul\Theme\ViewRenderEventManager;

/**
 * Brand Guidelines v1.0 head assets: favicons, share card, fonts and
 * brand stylesheets on every storefront page.
 */
class BrandHeadAssets
{
    public function addAssets(ViewRenderEventManager $eventManager): void
    {
        $ogImage = url('og-image.png');

        $html = <<<HTML
        <meta name="description" content="Classy Fashion Hub — shirts, jackets, shoes and more. Clear prices, easy ordering.">
        <meta name="theme-color" content="#0A0A0A">
        <link rel="icon" href="/favicon.ico" sizes="48x48">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
        <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
        <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
        <link rel="mask-icon" href="/safari-pinned-tab.svg" color="#0A0A0A">
        <link rel="manifest" href="/site.webmanifest">
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="Classy Fashion Hub">
        <meta property="og:title" content="Classy Fashion Hub">
        <meta property="og:description" content="Shirts, jackets, shoes and more. Clear prices, easy ordering.">
        <meta property="og:image" content="{$ogImage}">
        <meta name="twitter:card" content="summary_large_image">
        <link rel="stylesheet" href="/brand.css">
        <link rel="stylesheet" href="/css/classy-brand.css">
        HTML;

        $eventManager->addTemplate($html);
    }
}
