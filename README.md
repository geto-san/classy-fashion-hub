# Classy Fashion Hub — Online Fashion Management & E-Commerce System

University project adapting **Bagisto v2.4.12** (Laravel 12) for a small
Ugandan fashion shop. Requirements: [`docs/Blair_Fashion_Hub_report.docx`](docs/Blair_Fashion_Hub_report.docx).
Build plan & status: [`docs/Classy_Fashion_Hub_Bagisto_Plan.xlsx`](docs/Classy_Fashion_Hub_Bagisto_Plan.xlsx).
Requirement-by-requirement status: [`docs/COMPLIANCE_AUDIT.md`](docs/COMPLIANCE_AUDIT.md).

## Prerequisites

- PHP **8.3 or 8.4** with extensions: calendar, curl, intl, mbstring, openssl,
  pdo, pdo_mysql, tokenizer, ctype, dom, fileinfo, filter, gd, hash, json,
  pcre, session, xml, bcmath, iconv, zip (+ soap recommended).
  Bagisto 2.4 does **not** support PHP 8.5. If your default `php` is newer,
  point the scripts at the right binary: `PHP_BIN=/path/to/php8.3`.
- Composer 2.5+, Node 22+ (only for theme rebuilds), MySQL 8 / MariaDB.

## Fresh-clone setup

The repo tracks only **our** files under `store/` (the `ClassyFashion`
package, seeders, tests and six small documented core edits). Bagisto core is
not committed, so a plain clone cannot run until it is merged in. One script
does that without overwriting anything we track:

```bash
git clone https://github.com/geto-san/classy-fashion-hub.git
cd classy-fashion-hub
scripts/setup-local.sh          # downloads Bagisto 2.4.12, merges it under store/, composer install

cd store
# Edit .env: DB_DATABASE / DB_USERNAME / DB_PASSWORD (an empty database), APP_URL.
# Secrets live ONLY in .env and are never committed.
php artisan bagisto:install --no-interaction --demo-samples
php artisan db:seed --class='Database\Seeders\ClassyFashionSeeder'
php artisan db:seed --class='Database\Seeders\ClassyFashionCatalogSeeder'
php artisan serve --port=8000
```

Project seeders (idempotent, in `store/database/seeders/`):

| Seeder | What |
|---|---|
| `ClassyFashionSeeder` | Worker role (limited ACL) + worker & customer test accounts |
| `ClassyFashionCatalogSeeder` | UGX currency + channel, 19 fashion products with size/colour variants (footwear in EU sizes 38–45), stock, images |
| `DemoOrdersSeeder` | 3 demo orders (pending/paid/delivered) for screenshots & reports |

The catalogue seeder skips when products already exist, so footwear sizes only
appear on a freshly seeded database (see `docs/KNOWN_LIMITATIONS.md`).

## Mobile money sandbox (MTN MoMo)

1. Sign up at **momodeveloper.mtn.com** (email only) → Products →
   subscribe to **Collections** → Profile → Subscriptions → copy the
   **Primary key**.
2. Create an API user and key (see USER_GUIDE for the two curl calls).
3. Put them in `store/.env` (names in `.env.example`, never committed):
   `MTN_SUBSCRIPTION_KEY`, `MTN_API_USER_ID`, `MTN_API_KEY`.
   Sandbox uses `MTN_TARGET_ENV=sandbox`; note the sandbox only accepts
   its test currency (`MTN_CURRENCY`, production uses UGX).
4. Enable the method: admin → Configuration → Sales → Payment Methods →
   Mobile Money → Active. It appears at checkout only when MTN keys exist
   (or a Till number is set for the manual flow).
5. Localhost cannot receive callbacks: demo with the status page
   (“I Have Approved — Check Status”); production needs a public URL
   (ngrok/Render) set as the callback host.

## Accounts

Locally and in tests (`APP_ENV` not `production`) these documented accounts exist:

| Role | Email | Password | Notes |
|---|---|---|---|
| Admin | `admin@example.com` | `admin123` | Installer default |
| Worker | `worker@classy.local` | `worker123` | Catalogue, orders, stock; no settings, deletes or profit |
| Customer | `customer@classy.local` | `customer123` | Shopper with order history |

**On production these passwords are never used.** `artisan classy:secure-accounts`
runs on every boot and replaces any account that still accepts a published
password, using `SEED_ADMIN_PASSWORD` / `SEED_WORKER_PASSWORD` /
`SEED_CUSTOMER_PASSWORD` if you set them, otherwise a random password printed
once in the deploy log. Worker/customer demo accounts are only created with
`SEED_DEMO=true`. See `docs/DEPLOY.md`.

URLs (dev): shop `http://localhost:8000`, admin `http://localhost:8000/admin`,
customer registration `http://localhost:8000/customer/register`.

## Verify

```bash
cd store
php vendor/bin/pest tests/Feature
```

Manual click-paths: log in as worker → Roles/Users/Configuration/Audit Log
return 401; browse `/womens`, open a product, pick size/colour, check out
Cash on Delivery; as admin move the order Pending → Confirmed → Processing →
Dispatched → Delivered with the “Mark as …” buttons.

> After any theme rebuild (`npm run build` in a package), clear rendered-page
> caches or pages keep referencing deleted hashed files (unstyled pages):
> `php artisan optimize:clear && php artisan responsecache:clear`.
> Users also need a hard refresh (Ctrl+Shift+R) after a rebuild.

## Custom code (all outside core except six small edits)

- `store/packages/ClassyFashion/` — payments (MTN MoMo mobile money),
  order statuses and rules, cost/profit, audit log, notifications, admin pages
- `store/database/seeders/ClassyFashion*.php` — roles, accounts, UGX catalogue
- `store/tests/Feature/*Test.php` — role enforcement, checkout, payments,
  order rules, reports, audit, notifications
- Unused Bagisto modules (other payment gateways, social login) are disabled
  and their source removed on setup/deploy - see `docs/KNOWN_LIMITATIONS.md`.
- `deploy/` — Docker image, Render blueprint, boot script; `scripts/` — setup,
  backup, restore

Docs: `docs/USER_GUIDE.md`, `docs/DEMO_SCRIPT.md`, `docs/DEPLOY.md`,
`docs/KNOWN_LIMITATIONS.md`, `docs/COMPLIANCE_AUDIT.md`. Live deploy needs an
external MySQL for Render (free tier has no MySQL); see `docs/DEPLOY.md`.
