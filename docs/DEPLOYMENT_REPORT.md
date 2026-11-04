# Classy Fashion Hub deployment and handover

Status checked: 5 November 2026. Website: https://classy-fashion-hub.onrender.com.
Administrator sign-in: https://classy-fashion-hub.onrender.com/admin/login.

The project is configured for student assessment with the original Ethereal
Fashion UI, a normal retail catalogue, and practice payment controls. The two
Railway application/database services were deleted previously; retained Railway
storage is documented below. This report separates the current academic setup
from the historical migration and any future commercial launch.

## Coursework refresh — 5 November 2026

- **122 published styles**, 24 collections under Women, Men, Outerwear and Accessories.
  Names and descriptions use ordinary retail wording; previous `[DEMO]` labels are removed.
- **147 product photographs**, including 25 alternate gallery views, plus **21 new homepage photos**.
  Originals have a minimum short edge of 919 pixels; old 320-pixel catalogue photos are replaced.
- The supplied hero and opposite-facing embracing-women closing campaign remain.
  All 23 homepage editorial/campaign photographs are distinct; four product feeds allocate
  distinct products. Four centered category tiles fit desktop and mobile.
- Original Ethereal typography, header, card proportions, grid/rail layouts and editorial
  sections remain. Direct CDN image delivery and native card loading avoid stalled shimmers.
- Prices, costs and stock (20 units per listing at installation) are classroom scenarios.
  Descriptions are original. Photos come from Burst by Shopify, with individual source,
  photographer, licence and checksum records in the
  [manifest](../store/packages/ClassyFashion/src/Resources/coursework-catalogue/manifest.json).
  Marketplace seller images and descriptions were not copied.
- `CLASSY_COURSEWORK_MODE=true` selects local Approve/Decline mobile-money controls,
  blocks external gateway confirmation, captures email previews privately without sending,
  and uses the local assistant even if a paid AI key exists. Checkout, stock, orders,
  accounts, authorization, audits and reports still persist and function normally.
- Eleven information pages now provide project-appropriate contact, shipping, payment,
  returns, privacy and terms content. These are academic explanations, not approved
  policies for a real merchant.
- Tied-price catalogue pagination now uses a product-ID tie breaker.

A fresh independent PostgreSQL archive was verified and restored twice. The refresh
was prepared through Bagisto repositories in one isolated restore and reconciled
against the second before live application. Existing product IDs are retained for
the first 100 listings; customer, order and administrator records are outside the
transfer. The importer is explicit and repeat-safe; startup never reseeds the shop.
Private backup, upload and browser evidence: `backups/coursework-refresh-20261104/`.

Verification before publication: 36 focused tests / 583 assertions pass, including
checkout, reports, worker restrictions and notification capture. Pint passes on
65 changed PHP files; the Shop production build and all 336 translation checks pass.
The earlier eight payment/callback regression tests also pass.

### Presenting without a budget

1. Use the hosted site for client/lecturer review; allow for free-host cold starts.
   Keep a local installation as the presentation backup.
2. Follow the README once, including the public media downloader. No paid payment,
   email or AI accounts are required in coursework mode.
3. Add an item to the cart, enter an example checkout phone/address, select mobile
   money, and choose **Approve** or **Decline** on the practice-payment screen.
   An approval creates a real project order once; a decline keeps the cart.
4. Show order processing, stock changes, staff restrictions and reports in administration.
   Use made-up customer details, since saved orders and logs are genuine project records.
5. Give the reviewer an administrator created through Settings → Users, and let them
   set their own password. Keep project credentials and private logs out of Git/screenshots.

For a future real business, replace authored inventory/prices with the shop's actual
stock, review policy/contact content, disable coursework mode, verify email and
merchant payments, and perform a fresh backup and full checkout acceptance test.
Those paid-service decisions are not prerequisites for this course demonstration.

## What is deployed

| Component | Deployment and purpose |
|---|---|
| Website and administration | Render Free, Oregon; Docker image containing Bagisto 2.5.0, Laravel 13, PHP 8.4, and the built Tailwind 4 storefront/admin themes |
| Business database | Neon PostgreSQL 17, project `round-fire-56963406`; pooled connections for requests, direct connections for controlled migrations, TLS required |
| Uploaded files | Cloudinary; public originals retain their paths and extensions, private storage uses authenticated delivery |
| Email integration | Brevo transport is retained; coursework mode captures previews and cancels delivery |
| Source | Branch `deploy/render-neon-20261102`; live application commit `ff75074` |
| Live release | Render deployment `dep-dbkvh0mi0phs73bdlfk0`, status `live` |

Render serves the application, Neon stores business records and sessions, and
Cloudinary stores uploaded originals independently of Render instances. Routine
startup validates database access and installed-store readiness. It does not run
seeders or migrations, replace accounts, or regenerate uploaded files. Automatic
deployment is disabled in the blueprint; deployments are controlled manually.
Jobs currently execute synchronously, without a separate queue worker.

The migration preserved **0 products, 1 customer, 1 order, and 26 uploaded files**.
The later photo cleanup and demo import are recorded separately below. Password hashes, identities,
order values, and the application encryption key were retained during migration.
The hosting origin and stored footer URLs were changed to Render.

## Demo catalogue — 3 November 2026 (historical; superseded)

The owner's requested client-testing catalogue contains **100 visible demo products**:
20 dresses/tops, 20 shirts, 20 T-shirts, 20 trousers and 20 footwear listings.
Every name starts with `[DEMO]`; descriptions explain that the item is not for sale.
Each listing has a distinct CC0 photo from the
[clothing dataset](https://github.com/alexeygrigorev/clothing-dataset-small).
The [manifest](../store/packages/ClassyFashion/src/Resources/demo-catalogue/manifest.json)
records the pinned image source, licence, SHA-256 and illustrative UGX price benchmark.
Descriptions are original; Jumia category prices are examples, not matching-photo
supplier quotations. Marketplace seller photos and descriptions were not copied.

All 100 originals were uploaded to Cloudinary and downloaded again for checksum
verification. Stock is zero and backorders were intentionally disabled, preventing
demo purchases. Existing customer, order, account, channel and homepage records
were preserved. Demo categories sit under the existing shop categories.

A fresh database archive restored successfully before the import. The catalogue
was prepared using Bagisto repositories against that isolated restore, rehearsed
against a second restore, then transferred into empty product tables in one
guarded transaction. Category snapshots must match before transfer; existing
products are never replaced. Private backup and verification evidence is under
`backups/catalogue-sourcing/`.

Verification: 6 catalogue tests (322 assertions), the 12 homepage tests
(73 assertions), Pint and all 336 translation checks pass. Desktop/mobile and
product-detail checks confirm demo labels, distinct photos and no purchasable demos.
The newest-first API traversal returns all 100 distinct products across five pages.

Before customer launch, remove products whose SKU starts `CFH-DEMO-20261103-`
and their empty demo categories, then add the shop's actual products, rights-cleared
photos, sizes, prices and stock. Do not treat these examples as available inventory.
One issue found during that rehearsal, now fixed in the coursework refresh, was
price sorting repeating items between pages when prices tie. Add a product-ID tie breaker to catalogue sorting
before launch; newest-first and name sorting can be used during demo review.

## Homepage campaign refresh — 3 November 2026

At the owner's request, the original two-woman campaign is restored as the hero.
A distinct generated photograph of the women embracing in opposite directions
closes the homepage. Three different shop photographs appear once each in the
centered Categories section. Repeated editorial/photo sections and duplicate
homepage product feeds were removed; catalogue and search remain available.

Five originals were uploaded to `storefront/campaigns/20261103/` on Cloudinary and
verified by SHA-256. Bundled copies are included as fallbacks. Homepage settings
now include separate closing-image and description fields; repeated saved photo
paths are suppressed. Both live desktop and mobile checks confirm five unique
photos, centered categories, the closing banner last, and no JavaScript or broken
visible-image errors. The Shop build, translation gate and 12 storefront tests
(73 assertions) pass. Business counts remain 0 products, 1 customer and 1 order.
The release is commit `2abd572`, deployment `dep-dbkuk3m7bikc73fu3m8g`.
Private upload/check records are under `backups/homepage-refresh/`.

## Photo cleanup and catalogue setup — 2 November 2026 (historical)

At the owner's request, all fashion photos were cleared, including homepage,
category, legacy theme and product-search images. The 23 corresponding Cloudinary
originals were deleted after fresh independent media/database backups. Six bundled
fashion photos were removed from the deployed source. At that point Cloudinary retained only
the logo, favicon and locale icon; all three database media references passed checks.
The earlier 26-file migration and restore records below describe the state before
this intentional cleanup, and their backups remain available.

That release handled absent photos without broken images. Photo-dependent
sections stay hidden until the owner uploads photos; category links and product
feeds remain available. The catalogue has zero products and zero gallery images.
Existing customer/order records were preserved. Startup initialization and demo
seeding remain disabled so redeployments do not repopulate the catalogue.

The shop can add products under **Catalog → Products → Create Product**, then
configure names, prices, variants, stock, categories, visibility and gallery photos.
Homepage photos and copy are managed separately under the **Storefront** settings
page. Uploading a single collection photo is supported without requiring all of
the other photo slots to be filled.

Verification for this release: 11 storefront tests and 63 assertions pass; Pint,
the Shop build and translations pass. Real Cloudinary upload/read/delete checks
pass and remove their temporary test image. The live desktop/mobile homepage has
no fashion photos or broken visible images; administrator login, products,
storefront settings, user management, My Account and the Create Product dialog
pass browser checks. Render was restarted after the data cleanup to discard stale
page caches. Fresh backups and evidence are private under
`backups/catalogue-handover/`.

## How the website functions

| Area | Implemented behavior and practical limits |
|---|---|
| Shopping | Customers can browse categories, search/filter products, choose size/colour variants, and use the cart and checkout. The current academic catalogue has 122 stocked styles; use actual merchant inventory before commercial sales. |
| Customer accounts | Registration, sign-in, profiles, order history and order tracking are implemented. Password-reset delivery depends on resolving email setup. |
| Orders and delivery | Staff manage Pending, Confirmed, Paid, Processing, Dispatched and Delivered states under payment rules. Cash on Delivery can proceed to processing after confirmation and records cash at delivery. Delivery addresses, phone numbers, instructions and a free-text rider/partner are recorded. |
| Payments | Cash on Delivery and mobile-money integrations are implemented. MTN automatic confirmation verifies payment server-side; a configured manual Till flow lets staff reconcile a submitted transaction reference. Neither a harmless unknown-callback check nor sandbox tests prove live merchant readiness. Paid mobile-money cancellations require manual refund reconciliation. |
| Worker access | The standard Worker role supports operational work with a quantity-only stock screen and restricted settings, account management and profit access. Review custom roles before assigning real staff. |
| Administrator access | Preserved credentials access orders, payments, stock, users/roles, storefront settings, sales/profit reports and the audit log. No credentials are included in this report. |
| Reporting | Reports and CSV exports cover sales, stock, payments and gross product profit. Costs are captured per sale; enter actual supplier costs. Profit excludes business overheads, and period reporting uses the original order date. |
| Assistant | Shop assistance and rule-based product recommendations work through the normal session/CSRF flow. An optional configured LLM can support open questions. Learned recommendations require more purchase history. |
| Content and notifications | About Us and Payment Policy were present in the source. Order-status and low-stock email features exist, but delivery is not yet proven. Privacy and Terms are absent. |

## Giving the client their own administrator account

The original administrator was provisioned during store installation and preserved
during migration. Administrator registration is restricted to existing authorized
administrators; public customer registration does not grant staff access.

1. Sign in at `/admin/login` using the existing administrator credentials.
2. Open **Settings → Users → Create User**. Enter the client's name and email,
   set and confirm a temporary password, assign the full-access administrator role,
   enable the account, and save it.
3. The client signs in, opens **My Account** (`/admin/account`), and changes the
   password using the current password, new password and confirmation. They can
   also update their account name and email there.
4. Verify access to **Catalog → Products**, then review whether the previous
   administrator account should remain active. Verify the client account first.

This is a temporary-password handover, not an email invitation flow. Password-reset
email should only be relied on after Brevo sender verification and delivery testing.

## Verification and preserved backups

Checks repeated during this retirement:

- Render reports its latest deployment as live and the service as not suspended.
- Health (`/up`), homepage, customer login, administrator login and About Us return
  HTTP 200. Privacy Policy and Terms and Conditions return HTTP 404.
- The first public health request timed out; a subsequent check succeeded. Keep
  cold-start latency in mind when assessing the Free service.
- Neon contains 0 products, 1 customer, 1 order and 2 CMS pages.
- The original Railway SQL/media/config snapshot and independent Neon backup
  pass their recorded SHA-256 checksums.

Earlier migration acceptance, recorded in `DEPLOY.md`, also verified desktop and
mobile rendering, administrator login and operational pages, assistant responses,
safe handling of an unknown payment callback, and rejection of installer AJAX
requests. The final redeployment passed 16 public browser checks without broken
images or JavaScript errors. All 26 files survived redeployment; all 15 distinct
database media references resolved. These broader browser checks were not rerun
in full during service deletion.

The independent restore drill matched **144 tables, 1,424 rows, 118 sequences and
26 media files**, including table checksums, sequence states and original file
bytes. The migration record reports 143 custom tests and 476 assertions passing
on each of MySQL and PostgreSQL, plus storage, backup, formatting and translation
checks. Those suites were not rerun for this documentation/infrastructure task.

Private backups remain in the Git-ignored `backups/railway-live/` and
`backups/neon-independent/20261102T091100Z/` directories. Retirement API evidence,
business counts and HTTP results are in `backups/railway-retirement/`. Backups
remain local; verified off-machine storage and recurring backup scheduling are
still required.

## Railway retirement

At approximately 10:05 UTC on 2 November 2026, Railway accepted deletion of:

| Service | ID | Result |
|---|---|---|
| `classy-fashion-hub` | `c0710838-dafd-468a-877e-eb678fcf3652` | Deleted |
| `MySQL` | `6d53b249-0f99-4033-991a-b49960d757aa` | Deleted |

A subsequent query confirms **zero services** across both accessible projects.
The temporary SSH key used for the retirement attempt was revoked.

Before these deletions, neither service had an active deployment; Railway marked
all recorded deployments `REMOVED`, and SSH reported no active application.
Consequently no fresh source export or final source-to-Neon comparison was
possible. The preserved migration snapshot is the latest verified source in this
handover; changes made after that snapshot cannot be ruled out. Restoring an old
deployment would require the matching original database/media backup and code.

Remaining resources:

| Project | Retained volumes |
|---|---|
| `humble-magic` | `mysql-volume`, `classy-fashion-hub-volume` |
| `powerful-essence` | `classy-fashion-hub-volume`, `mysql-volume-F-Rl`, `mysql-volume-15jP` |

Automatic approval review rejected permanent deletion of both projects and all
five volumes: three older volumes have no verified content backup, and the review
treated project/volume destruction as broader than the authorized service removal.
Deleting these volumes permanently loses their remaining contents. They have
therefore been retained pending explicit approval. **Complete Railway teardown
and zero future Railway charges are not confirmed.** Retained storage may incur
charges, and Railway account/subscription cancellation was not performed.

## Work required

| Priority | Action | Completion evidence |
|---|---|---|
| Before selling | Populate the catalogue with actual products, prices, variants, stock, supplier costs and approved photos. | Place an end-to-end order for a real configured item and verify stock and reporting. |
| Before customer launch | Verify `MAIL_FROM_ADDRESS` in Brevo and authorize Render outbound ranges `74.220.48.0/24` and `74.220.56.0/24`. | Password-reset and order-status emails arrive at controlled recipient accounts. |
| Before customer launch | Publish owner-approved Privacy and Terms content; review all policy/footer links. | Required policy URLs return HTTP 200 and contain approved shop practices. |
| Before accepting mobile money | Confirm the enabled provider/mode, live merchant credentials, currency and callback registration. | A controlled payment, callback/status confirmation, order creation and refund reconciliation complete successfully. |
| Before customer launch | Decide the public hostname and update DNS, app/channel URLs, links and payment-provider callback configuration where applicable. | All customer entry points and provider callbacks use the selected host; no dependency on the deleted Railway app remains. |
| Resolve migration uncertainty | Decide whether retained source volumes need recovery and comparison before approving their deletion. | Any recoverable post-snapshot changes are reconciled, or the owner explicitly accepts the snapshot and loss of unverified volume contents. |
| Finish Railway cleanup | Approve deletion of the two remaining projects and five volumes after the recovery decision; review/cancel any paid Railway plan. | Empty resource inventory and billing/subscription confirmation. |
| Operational readiness | Schedule independent database/media exports, copy them off-machine and repeat restore drills. | A recent scheduled backup and successful restore are documented. |
| Operational readiness | Decide whether Render Free availability is sufficient; evaluate always-on hosting if needed. | Agreed uptime/cold-start expectations and monitoring. |
| Quality and maintenance | Test with real phones, weak networks and representative users; measure load/accessibility and review dependencies. Update older guides/audits whose historical deployment statements are superseded by this report. | Recorded findings and resolved material defects. |

Future enhancements include Luganda, learned recommendations, a rider directory,
automatic refund integration, virtual fitting and marketplace functionality.
They are not required to complete the current migration handover.
