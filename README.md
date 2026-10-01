# Classy Fashion Hub — Online Fashion Management & E-Commerce System

3-day university project adapting **Bagisto v2.4.12** (Laravel 12) for a small
Ugandan fashion shop. Requirements: `Blair_Fashion_Hub report.docx`.
Build plan & status: `Classy_Fashion_Hub_Bagisto_Plan.xlsx`.

## Prerequisites

- PHP 8.3 with extensions: calendar, curl, intl, mbstring, openssl, pdo,
  pdo_mysql, tokenizer, ctype, dom, fileinfo, filter, gd, hash, json, pcre,
  session, xml, bcmath, iconv, zip (+ soap recommended).
  On Arch: `php-legacy` + conf.d entries (see commit history). Bagisto 2.4
  does **not** support PHP 8.5 — use the `php-legacy` binary everywhere below.
- Composer 2.5+, Node 22+ (only for theme rebuilds), MySQL 8 / MariaDB.

## Fresh-clone setup

```bash
composer create-project bagisto/bagisto bagisto "2.4.*"
cd bagisto
cp .env.example .env
# Edit .env: APP_NAME="Classy Fashion Hub", APP_URL=http://localhost:8000,
# APP_TIMEZONE=Africa/Kampala, DB_* for your database. Secrets ONLY in .env.
php-legacy artisan key:generate
php-legacy artisan bagisto:install --no-interaction --demo-samples
php-legacy artisan db:seed --class='Database\Seeders\ClassyFashionSeeder'
php-legacy artisan db:seed --class='Database\Seeders\ClassyFashionCatalogSeeder'
php-legacy artisan serve --port=8000
```

Project seeders (idempotent, in `bagisto/database/seeders/`):

| Seeder | What |
|---|---|
| `ClassyFashionSeeder` | Worker role (limited ACL) + worker & customer accounts |
| `ClassyFashionCatalogSeeder` | UGX currency + channel base, 16 fashion products (67 variants), stock, images |
| `DemoOrdersSeeder` | 3 real demo orders (pending/paid/delivered) for screenshots & reports |

## Mobile money sandbox (Flutterwave, MTN/Airtel)

1. Create a free Flutterwave account → sandbox (test) dashboard.
2. Copy test **public key**, **secret key**, and set/​copy the webhook
   **secret hash** (Dashboard → Settings → Webhooks).
3. Put them in `bagisto/.env` (keys from `.env.example`, never committed):
   `FLUTTERWAVE_PUBLIC_KEY`, `FLUTTERWAVE_SECRET_KEY`,
   `FLUTTERWAVE_SECRET_HASH`, `FLUTTERWAVE_SANDBOX=true`.
4. Enable the method: admin → Configuration → Sales → Payment Methods →
   Mobile Money → Active. It appears at checkout only when keys exist.
5. Localhost cannot receive webhooks: demo with the status page
   (“I Have Approved — Check Status”); production needs a public URL
   (ngrok/Render) registered in the Flutterwave dashboard.

## Test accounts

| Role | Email | Password | Notes |
|---|---|---|---|
| Admin | `admin@example.com` | `admin123` | Installer default — **change after submission** |
| Worker | `worker@classy.local` | `worker123` | Custom role: catalogue, orders, no settings/deletes |
| Customer | `customer@classy.local` | `customer123` | Shopper with order history |

URLs (dev): shop `http://localhost:8000`, admin `http://localhost:8000/admin`,
customer registration `http://localhost:8000/customer/register`.

## Verify

```bash
cd bagisto
php-legacy vendor/bin/pest tests/Feature/WorkerAccessTest.php
php-legacy vendor/bin/pest tests/Feature/CheckoutFlowTest.php
```

Manual click-paths: log in as worker → Roles/Users/Configuration return 401;
browse `/womens`, open a product, pick size/colour, check out Cash on Delivery.

> After any theme rebuild (`npm run build` in a package), clear rendered-page
> caches or pages keep referencing deleted hashed files (unstyled pages):
> `php-legacy artisan optimize:clear && php-legacy artisan responsecache:clear`.
> Users also need a hard refresh (Ctrl+Shift+R) after a rebuild.

## Custom code (all outside core)

- `bagisto/database/seeders/ClassyFashion*.php` — roles, accounts, UGX catalog
- `bagisto/tests/Feature/*Test.php` — role enforcement + E2E checkout
- Day-2 package `ClassyFashion` (payment, audit log, profit) — to come

Docs: `docs/USER_GUIDE.md`, `docs/DEMO_SCRIPT.md`, `docs/KNOWN_LIMITATIONS.md`
(Day 3). Live deploy needs an external MySQL for Render (free tier has no
MySQL); see plan notes.
