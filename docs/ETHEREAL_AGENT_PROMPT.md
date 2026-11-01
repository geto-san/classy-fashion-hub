# Classy Fashion Hub — Ethereal implementation prompt

Copy the prompt below to your implementation agent. Supply the actual Classy Fashion Hub deployment URL if the agent cannot discover it from the deployment configuration; the Bagisto URL is a reference demo, not the Classy deployment.

---

Implement an Ethereal-inspired storefront for **Classy Fashion Hub**, using this reference as the visual authority:
https://demo.bagisto.com/common/ethereal-fashion/ethereal-men

Do the implementation and verify it in a browser. Preserve the existing Laravel/Bagisto commerce system and Classy Fashion Hub business identity. This is a visual redesign of the storefront, including homepage, category/search listings, product details, cart and checkout consistency.

## Inspect first

- Read repository instructions and applicable Bagisto skills. Inspect the existing working tree and preserve unrelated ongoing changes.
- Inspect the live reference at desktop and mobile sizes. If unavailable, inspect `documents/ethereal.bagisto.com.webm`; extracted frames are in `output/premium-assets/reference/`. The recording shows the demo state captured by the owner; verify current live behavior when possible. Use the Ethereal recording rather than `documents/commerce.bagisto.com.webm` as the visual target.
- Inspect the actual Classy deployment and compare it with local code. Discover its URL from available deployment information; ask for it only if unavailable. Never describe the reference demo as our deployed website.
- Read `documents/Blair_Fashion_Hub report.docx`, the brand kit, and existing shop overrides. The audience includes Ugandan students and adults; fixed prices, stock visibility and a simple buying journey matter.
- Current customizations live in `store/packages/ClassyFashion/`, particularly `src/Resources/views/overrides/shop/`, `src/Support/Storefront.php`, the provider and admin storefront configuration. Work through this package and Bagisto extension points wherever possible.

## Match the observed Ethereal composition

1. Use a narrow black announcement strip with restrained white text. Below it, use a spacious white desktop navigation row: product categories on the left, the Classy wordmark visually centered, and search/account/cart utilities on the right. Use existing categories and working routes. Add compare only if already supported and useful. Match the reference proportions and icon weight.
2. Build a full-width photographic hero directly under navigation. Use a quiet warm neutral image with generous negative space on the left and models on the right, a large regular-weight sans-serif headline, short supporting line and compact rectangular black CTA. Render all copy as accessible HTML. Use the supplied `classy-collection-campaign` asset; preserve complete outfits when cropping. On mobile, place copy above or below the photograph if needed so faces and clothing remain visible.
3. Follow the reference's large editorial rhythm: generous whitespace, crisp alignment, collection imagery and uncluttered product grids. Review the whole recording to establish section order. Use real inventory for sections such as New Arrivals; do not invent product counts or promotional urgency.
4. Product cards should lead with consistent portrait images, followed by the product name and UGX price. Use minimal framing, fine typography and restrained hover/focus actions, including Add to Cart where the product supports it. Configurable products must still lead to size/colour selection. Avoid dashboard-style cards, heavy shadows, repeated rounded boxes and competing decorative effects.
5. Use a simple service-information row and a pale neutral multi-column footer with the Classy identity, actual support links, policies and verified contact information. Include only delivery/payment/support claims the business can fulfill. Do not copy the demo's free shipping, instalments, 24/7 support, Black Friday countdown or discount claims without verified business data.
6. Carry the same type scale, neutral palette, rectangular controls, image ratios and spacing into listings, product galleries, size/colour selectors, mini-cart, cart and checkout. Keep the purchase button prominent and readable.

Match layout, spacing, proportions, image treatment and hierarchy closely. Use the Classy logo and real products. Do not copy Bagisto demo photography, Ethereal branding, cloud-hosting banner, demo toolbar or chat overlays. Black, white and warm neutrals should dominate; retain existing Classy brand accents sparingly where useful. Identify the reference font if obtainable from authorized assets; otherwise choose an appropriately licensed close sans-serif and document the substitution.

## Use the supplied visual assets correctly

- Review `output/premium-assets/README.md` and `output/premium-assets/manifest.json`. PNG masters and optimized web derivatives are provided separately. Exact generation prompts and source references are in `output/premium-assets/generation-prompts.json`.
- These are AI reconstructions from boutique photos. Compare each with its source before assigning it to an actual SKU. Keep uncertain trim, fabric, reverse-side details and accessories out of factual product claims. Never assign a reconstruction to an unrelated seeded product merely because the colors match.
- Prefer the campaign asset for the homepage. Use individual reconstructions in product listings only after the merchandise match is verified. Keep the genuine shop photographs available for comparison.
- Install web derivatives into an appropriate shipped public asset directory or persistent Bagisto media storage; do not reference `output/` as a public URL. Respect the deployment's explicit `.dockerignore` allowlist and verify inclusion in the built image. Keep PNG masters out of the initial page payload.
- Use responsive `srcset`/`sizes`, explicit width/height, descriptive alt text and appropriate object positioning. Load the first hero eagerly with high fetch priority and lazy-load content below the fold. Avoid stretching smaller files or loading hidden carousel slides eagerly. Honour reduced motion and provide keyboard-accessible carousel controls if retaining a carousel.
- Preserve owner-editable storefront content. Inspect the current hero/image fields first; extend the content model if the campaign needs a separate hero image rather than overloading category images. Avoid brittle global image-replacement regexes or mapping three unrelated shop photos into every section.

## Preserve commerce behavior

Retain UGX pricing, real inventory, size/colour variants, customer registration/login, search, cart, checkout, COD and the existing configured mobile-money flow. Keep admin/worker permissions, stock controls, order statuses, sales reporting, audit history and notifications intact. Do not alter payment logic or inventory rules to simplify the redesign. Use existing localization conventions and maintain all supported locale keys. Do not introduce a new framework or dependency without required approval.

## Verify and finish

- Build the affected frontend package and clear applicable Laravel/view/response caches according to repository instructions.
- Follow the repository's change-verification requirements; report checks actually run and distinguish blocked checks from passing checks.
- Capture comparable reference and local screenshots at approximately 1440px desktop and 390px mobile widths, including the hero, product section and footer. Inspect navigation, spacing, image crops, typography, mobile overflow and footer hierarchy. State any reference details that could not be inspected.
- Exercise search, category filtering, product variants, add-to-cart, mini-cart, cart updates and checkout in the approved test environment. Verify keyboard navigation, visible focus, labels, contrast and touch targets. Confirm asset URLs return 200 in the deployment build and owner image uploads survive redeployment.
- Deliver the completed code, before/after screenshots, file summary, checks and any remaining visual differences. Prepare the result for deployment; publish only within the deployment authority already granted by the owner. Do not claim live changes without checking the actual deployment URL.

Start by inspecting the supplied recording, assets and existing implementation, then carry the redesign through completion.
