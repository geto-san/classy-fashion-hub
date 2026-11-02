# Classy Fashion Hub deployment and handover

Status checked: 2 November 2026. Website: https://classy-fashion-hub.onrender.com.
Administrator sign-in: https://classy-fashion-hub.onrender.com/admin/login.

The replacement website is live and the two Railway services have been deleted.
It is ready for the shop to start adding its own catalogue and photographs.
Customer launch still requires verified email delivery, approved Privacy and
Terms pages, configured products and live-payment decisions. Railway storage
cleanup also requires additional approval, as detailed below.

## What is deployed

| Component | Deployment and purpose |
|---|---|
| Website and administration | Render Free, Oregon; Docker image containing Bagisto 2.5.0, Laravel 13, PHP 8.4, and the built Tailwind 4 storefront/admin themes |
| Business database | Neon PostgreSQL 17, project `round-fire-56963406`; pooled connections for requests, direct connections for controlled migrations, TLS required |
| Uploaded files | Cloudinary; public originals retain their paths and extensions, private storage uses authenticated delivery |
| Email integration | Brevo HTTPS transport is configured; verified sender and successful delivery remain outstanding |
| Source | Branch `deploy/render-neon-20261102`; live application commit `95612f9` |
| Live release | Render deployment `dep-dbk6vess728c73c9r9ig`, status `live` |

Render serves the application, Neon stores business records and sessions, and
Cloudinary stores uploaded originals independently of Render instances. Routine
startup validates database access and installed-store readiness. It does not run
seeders or migrations, replace accounts, or regenerate uploaded files. Automatic
deployment is disabled in the blueprint; deployments are controlled manually.
Jobs currently execute synchronously, without a separate queue worker.

The selected Railway data was preserved rather than replaced with a demo shop:
**0 products, 1 customer, 1 order, and 26 uploaded files**. Current Neon queries
confirm those business counts and two CMS pages. Password hashes, identities,
order values, and the application encryption key were retained during migration.
The hosting origin and stored footer URLs were changed to Render.

## Photo cleanup and catalogue setup

At the owner's request, all fashion photos were cleared, including homepage,
category, legacy theme and product-search images. The 23 corresponding Cloudinary
originals were deleted after fresh independent media/database backups. Six bundled
fashion photos were removed from the deployed source. Cloudinary now retains only
the logo, favicon and locale icon; all three database media references pass checks.
The earlier 26-file migration and restore records below describe the state before
this intentional cleanup, and their backups remain available.

The storefront now handles absent photos without broken images. Photo-dependent
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
| Shopping | Customers can browse categories, search/filter products, choose size/colour variants, and use the cart and checkout. Products must first be added to the currently empty live catalogue. |
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
