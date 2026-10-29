# Storefront design system (v2)

Editorial, hairline-based layout inspired by leading Japanese fashion retailers
(generous whitespace, uppercase tracked labels, 3:4 product tiles, text-link
navigation), with Ugandan content: UGX prices, MTN MoMo / Airtel Money / cash on
delivery, flat UGX 5,000 delivery, Luganda greetings (Karibu, Webale nnyo) and a
thin Ugandan-flag accent on the announcement bar.

## Where things live

| Concern | File |
|---|---|
| Tokens, components, page styles | `store/public/css/classy-theme.css` (shop only) |
| Fonts + stylesheet injection | `ClassyFashion/src/Listeners/BrandHeadAssets.php` (`shopTheme()`) |
| Announcement bar | `Shop/.../components/layouts/header/index.blade.php` |
| Footer, promise strip | `Shop/.../components/layouts/footer/index.blade.php`, `services.blade.php` |
| Homepage | `Shop/.../home/index.blade.php` |
| Product card, rails, category tiles | `Shop/.../components/products/card.blade.php`, `products/carousel.blade.php`, `categories/carousel.blade.php` |

## Tokens

Colour: noir `#0A0A0A`, ivory `#F7F3EC`, champagne `#E8E0D4`, stone `#6B635B`, gold `#8A6A3B`.
Type: Cormorant Garamond (display), Belleza (uppercase labels, buttons), Jost (body).
Shape: 2px radius, 1px hairlines in `--cfh-line`.

Shared Bagisto controls (buttons, inputs, labels, sale tags, breadcrumbs) are
restyled in the "consistency layer" at the end of the stylesheet so cart,
checkout and account pages match the new pages without per-view edits.

## Homepage behaviour

Admin-managed image-carousel and product-carousel sections still take
precedence when they exist; otherwise the editorial hero and the default
"New arrivals" / "Staff picks" rails render. Bagisto's demo static-content
blocks and services block are no longer rendered.

## Rebuilding

CSS and Blade only: no Vite rebuild needed. Clear caches after deploy:
`php artisan optimize:clear && php artisan responsecache:clear`.

## Listing filters and product recommendations

- `categories/filters.blade.php` (core override): every filter is a collapsed
  dropdown box; size-like attributes render as selectable chips; the option
  search box only appears for long option lists (more than 8).
- "You may also like" (`ProductRecommendations`) uses semantic `cfh-pick*`
  classes styled in `classy-theme.css` (3:4 tiles, four across, two on mobile)
  instead of Tailwind utilities, which are not compiled for this package.
