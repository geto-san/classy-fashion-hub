# Classy Fashion Hub — Ethereal storefront redesign

The storefront redesign is implemented in the working tree. The existing Laravel/Bagisto commerce system and Classy identity remain in place. The storefront source and shipped web assets are prepared for GitHub publication. Live Railway deployment must be verified independently.

## Review

- [Screenshot comparison gallery](../output/ethereal-exact/index.html)
- [Desktop homepage](../output/ethereal-exact/local-home-1440.png)
- [Mobile homepage](../output/ethereal-exact/local-home-390.png)
- [Category listing](../output/ethereal-exact/local-category-1440.png)
- [Product details](../output/ethereal-exact/local-product-1440.png)
- [Mobile checkout](../output/ethereal-exact/checkout-390.png)
- [Browser checks](../output/ethereal-exact/browser-checks.json)

The actual deployment inspected was https://classy-fashion-hub-production-ec16.up.railway.app/. Its before captures are `deployed-1440.png` and `deployed-390.png`. The visual reference was https://demo.bagisto.com/common/ethereal-fashion/ethereal-men. Chromium could inspect the live reference at desktop and mobile sizes after the web tool failed; the owner's Ethereal recording also informed the homepage composition. Reference screenshots are `reference-1440.png` and `reference-390.png`.

## Measured Ethereal reconstruction

The second implementation reconstructs the public reference HTML, Vue templates and computed geometry, rather than treating the hero as the only authoritative section. Evidence is in `output/ethereal-exact/`: live home/category captures at 1440px and 390px, delivered component templates, static styles, product-page source and cart/checkout availability notes. Browser automation provided the runtime evidence; REA was available as a CLI but its browser MCP was not registered, so these captures are not represented as REA Evidence.

| Element | Reference-derived implementation |
| --- | --- |
| Desktop header | 41px announcement; 69px navigation including border; centered Classy logo; text and thin utility icons |
| Main container | 1420px maximum with 30px internal padding; 40px content inset at 1440px |
| Hero frame | 1920:855; real Classy campaign; accessible HTML copy; full outfits preserved |
| Homepage sequence | Hero, truthful black promotion band, category tiles, 12-card arrivals grid, alternating editorial splits, six collection tiles, two-image lookbook, image with product rail, offset rail, wide story, style links, four-column product grid, seven-image mosaic, services, footer |
| Product cards | Portrait ratio 0.81; desktop 3/4 columns, 32px gaps, 24px image-to-info gap, 20px names and 18px prices; mobile two columns, 8px gaps, 14px names |
| Category/search | Full-width composition, 3:1 category banner, 3/4-column controls, right-hand 408px filter drawer |
| Product details | Two 432px gallery columns, 16px gallery gap, 28px main gap, 452px details; compact inline accordions; full-width mobile gallery |
| Mini-cart | 408px desktop drawer, portrait image, stacked details, simple dividers and full-width actions |
| Footer | Pale three-column link structure, generous spacing, lower copyright row; mobile home bottom navigation with accessible footer drawer |

Desktop/mobile browser assertions check the card geometry, header heights and responsive footer state. Final full-page captures also show no horizontal overflow on home, category or search at either width. The source demo's cloud banner, toolbar, chat, branding and unverified promotional claims are excluded.

## Result

- Black announcement strip, centered supplied Classy logo, restrained desktop navigation and existing mobile navigation.
- Full-width campaign photograph with accessible HTML copy, responsive WebP sources, explicit dimensions and high-priority initial loading. Mobile copy sits above the complete photograph.
- Genuine shop collection photos with explicit destinations and descriptions, plus real Bagisto product feeds. Seeded promotional HTML and global photograph replacement are no longer rendered by the homepage.
- Ethereal portrait product cards across homepage, search and categories, including image hover actions and configurable-product selection routes. Category/search filtering uses a desktop drawer and preserves Bagisto's mobile filter/sort controls.
- Shared neutral colors, rectangular controls, typography and focus treatments across product details, cart, mini-cart and checkout.
- A pale footer, working mobile footer newsletter form and factual shopping-service information without invented discounts, free delivery or round-the-clock support claims.
- Keyboard activation for existing custom buttons, dialog semantics, keyboard focus containment, Escape dismissal and focus return for drawers. Reduced-motion preferences are respected.
- Owner-editable campaign image and image description, independent of collection photos. Existing uploads, copy and links remain editable through the homepage editor.

The reference declares **Work Sans** for body and headings. Its variable WOFF2 font is served locally from `store/public/fonts/work-sans/`, with the SIL Open Font License retained in `OFL.txt`. Existing Poppins/system fonts remain fallbacks. The supplied logo is used unchanged, including its own lettering.

## Main files

| File or directory | Purpose |
| --- | --- |
| `store/packages/ClassyFashion/src/Resources/views/overrides/shop/home/index.blade.php` | Campaign, collections and product feeds |
| `.../overrides/shop/components/layouts/header/` | Announcement strip and centered navigation |
| `.../overrides/shop/components/layouts/footer/index.blade.php` | Classy footer, links and subscription |
| `.../overrides/shop/components/layouts/services.blade.php` | Factual service row |
| `.../overrides/shop/components/products/` | Measured portrait cards, reusable grid/rail feed and carousel presentation |
| `.../overrides/shop/categories/` and `.../overrides/shop/search/` | Full-width listings and filter drawer |
| `.../overrides/shop/components/drawer/index.blade.php` | Keyboard-accessible drawer behavior |
| `.../overrides/shop/products/view/` | Ethereal gallery, details and inline accordions with original purchase controls |
| `.../overrides/shop/checkout/` | Mini-cart, cart and checkout visual consistency |
| `store/public/css/classy-ethereal.css` | Measured storefront layout and responsive visual system |
| `store/public/css/classy-ethereal-product.css` | Product gallery and detail styling |
| `store/public/css/classy-ethereal-footer.css` | Footer, cart and checkout styling |
| `.../Listeners/ProductRecommendations.php` and `.../views/products/recommendations.blade.php` | Existing recommendation ranking presented with the shared product feed |
| `store/packages/ClassyFashion/src/Resources/views/ethereal-head.blade.php` | Storefront stylesheet/font loading and keyboard support |
| `store/packages/ClassyFashion/src/Listeners/BrandHeadAssets.php` | Loads storefront assets without restyling the admin |
| `store/packages/ClassyFashion/src/Support/Storefront.php` | Separate hero media fields and explicit collection mapping |
| `store/packages/ClassyFashion/src/Resources/views/admin/storefront/index.blade.php` | Owner campaign upload controls |
| `store/packages/ClassyFashion/src/Resources/lang/*/storefront.php` | Matching new keys in all 22 locale files |
| `store/packages/Webkul/Shop/tailwind.config.js` | Corrects the custom package source paths scanned by Tailwind |
| `.dockerignore`, `.gitignore`, `store/public/.gitignore` | Includes required sources, stylesheet, fonts and web images |
| `store/tests/Feature/StorefrontTest.php` | Campaign rendering, independent uploads, safe links and permissions |
| `scripts/verify-ethereal.cjs` | Repeatable local desktop/mobile commerce checks and screenshots |

Earlier unrelated working-tree changes were preserved. No new Composer or npm dependency was introduced. The source paths above remain Bagisto overrides; generated Vite output was rebuilt rather than hand-edited.

## Verification

- PHP 8.4 isolated Docker environment: **89 feature tests passed, 394 assertions**. This includes order placement, variants, payment mocks, stock/roles, and the storefront tests. See `output/ethereal-exact/feature-tests.txt`.
- Affected suite: **20 tests passed, 90 assertions**. Final feature suite uses `php artisan test tests/Feature`; the unqualified upstream suite references an absent PayGlocal test directory and cannot run as configured.
- Pint: all four checked PHP files pass (Storefront, ProductRecommendations, StorefrontTest, AssistantTest). Blade views compile successfully.
- Shop Vite production build passes. A final smoke image builds using the previously installed dependencies, final allowlisted source and rebuilt Shop bundle. All 41 checked views/CSS/fonts/images are included byte-for-byte (`deployment-allowlist.json`). The clean Railway Dockerfile rebuild failed: GitHub codeload timed out downloading the pinned Bagisto archive (curl error 28 after 300 seconds). This is a blocked production-build gate, not a passing clean build; the earlier iteration built successfully.
- **16 passing** desktop and mobile browser checks at **1440px and 390px** cover homepage layout, real product feeds, mini-cart, search, variants, persisted cart, quantity updates, customer sign-in and checkout through address, shipping and COD selection. Desktop filtering and keyboard dismissal/focus restoration also pass. No JavaScript page errors were recorded; homepage captures have no horizontal document overflow.
- A sample image written through Laravel's public storage disk returned HTTP 200 before and after replacement of the smoke-test container using the same named volume, with identical SHA-256 bytes. The built image's homepage, stylesheet and campaign image also return HTTP 200. Evidence is in `image-smoke.json`.
- All **22 ClassyFashion storefront locale files have the same 101 separately formatted keys (plus matching nested inline key groups)**. New labels are supplied as English defaults in those files. This is key parity, not a claim of reviewed translations.
- The global `bagisto:translations:check` gate still **fails**: 168 of 190 checked locales pass; the existing root `vendor` directory is treated as a locale and 21 Installer locale structures differ from English. The command scans `packages/Webkul` and does not discover the ClassyFashion package, so its key parity was checked separately. See `output/ethereal-exact/translations.txt` for the full output.

The old audit container initially contained stale tests and disposable array sessions. The current workspace tests were synced, HTTP sessions were made persistent inside that disposable preview, and its test customer's browser-created active carts were cleared before the final feature run. Those preview-only settings and fixture corrections were not applied to the repository or deployment configuration.

The browser runner uses the documented local test customer and can leave an active cart on that fixture account. Use an isolated seeded database, and clear that fixture cart before a subsequent checkout feature-suite run. It does not place a real order or contact a live payment provider.

## Remaining differences and deployment

The homepage uses the business's own campaign and collection photography. The local catalogue still contains its existing purple/gold seeded image placeholders. They are intentionally retained: the generated red suit, ombre dress and striped shirt have not been mapped to unrelated seeded SKUs. Verified product photographs must be uploaded to the corresponding real catalogue items for the same photographic quality as Ethereal's product grid.

The Classy logo, real categories, truthful service information and existing commerce flows replace the reference's branding, sample inventory and demo overlays. Product details now follow the recovered Ethereal gallery/details/accordion layout. Live reference product hydration stalled, but its delivered templates were recovered. Live cart and checkout states timed out, so exact pixel fidelity of those states cannot be certified; their original commerce behavior remains under the shared styling. The reference category banner geometry is reproduced using genuine business imagery or owner-uploaded category media. The homepage campaign is native 1672 × 940; larger displays do not gain additional photographic detail from CSS scaling.

Deploy the reviewed working tree through the existing Railway workflow. Keep the configured persistent storage volume. The local container-replacement test verifies the storage approach; actual Railway volume persistence and the new storefront must still be checked after a real authorized deployment. The live website has not been changed by this task.

Before deployment, ensure all new override files, CSS, fonts and campaign derivatives are included in the reviewed commit. After release, check homepage, search, product variants, cart, checkout, owner uploads and asset URLs at the actual Railway address, then compare with the saved screenshots.


The final verification rerun initially encountered root-owned fixture image files in the disposable container; ownership was corrected only there. No production storage permissions or fixtures were changed. Final captures and test results are in `output/ethereal-exact/`; previous deployed and earlier implementation captures remain in `output/ethereal-verification/` for comparison.

The approval-review usage limit temporarily prevented the deployment-context check; after resuming, that exact check executed and passed. No approval restriction was bypassed.

Final smoke image HTTP checks: homepage, all three storefront stylesheets, Work Sans font and campaign WebP returned **200**. See `output/ethereal-exact/image-smoke.json`. This smoke image reuses installed dependencies; it does not replace the blocked clean-production-build gate.

Publication scope excludes the unrelated pending OpenAI client and assistant-answer changes. The committed storefront suite excludes their two new API tests; the original 89-test result above describes the complete working tree before publication separation.

Final publication snapshot: **87 feature tests passed, 390 assertions** after excluding the two unrelated OpenAI tests and client changes. Pint passes all five publication PHP targets. Staged whitespace checks pass. Original assistant code and its matching tests remain unchanged in the published snapshot.
